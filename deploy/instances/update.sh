#!/usr/bin/env bash
# Met toutes les instances à la version voulue, une par une, et s'arrête à la première erreur.
#
#   PHP_IMAGE=… WEB_IMAGE=… update.sh         passe toutes les instances à ces images
#   update.sh                                 reprend la version voulue déjà enregistrée (images.env)
#   update.sh <client> [<client>…]            seulement ces instances
#   update.sh --force …                       met aussi à jour une instance dont un loto est en cours
#   make instances-update [CLIENT=…] [FORCE=1] [PHP_IMAGE=… WEB_IMAGE=…]
#
# Pour chaque instance, dans l'ordre alphabétique :
#   1. déjà à la version voulue : rien à faire ;
#   2. loto en cours : laissée telle quelle (voir plus bas) ;
#   3. sauvegarde de la base, arrêt, migrations, démarrage, sonde de santé ;
#   4. migration ou sonde en échec : retour aux images précédentes, puis arrêt de toute la mise à jour.
# Les migrations doivent rester compatibles avec la version précédente : le retour arrière ne les défait pas.
# Après un échec, la version voulue redevient celle d'avant : une nouvelle instance ne partira pas sur la mauvaise.
# Une instance « en erreur » est reprise même si ses images sont déjà les bonnes.
#
# Un loto est « en cours » si une partie est en cours dans un événement daté d'aujourd'hui ou d'hier, ou si un
# numéro a été tiré depuis moins de BUSY_HOURS heures (6 par défaut).
#
# Codes de sortie : 0 toutes les instances demandées sont à la version voulue ; 1 erreur, la mise à jour s'est
# arrêtée ; 3 aucune erreur, mais des instances ont été laissées de côté (loto en cours) : relancer plus tard.
# Par make, le code 3 devient 2 (celui de make pour toute cible en échec).
#
# Réglages : INSTANCES_ROOT (défaut /srv/quinou), INSTANCES_PREFIX (défaut quinou), BUSY_HOURS.
set -Eeuo pipefail
umask 077

here=$(cd "$(dirname "$0")" && pwd)
root=${INSTANCES_ROOT:-/srv/quinou}
prefix=${INSTANCES_PREFIX:-quinou}
busy_hours=${BUSY_HOURS:-6}
db_container="$prefix-postgres"

log() { printf '[update %s] %s\n' "$(date '+%T')" "$*"; }
die() { printf '[update] ERREUR : %s\n' "$*" >&2; exit 1; }
# shellcheck source=deploy/instances/lib.sh
. "$here/lib.sh"

force=false
only=()
for arg in "$@"; do
    case "$arg" in
        --force) force=true ;;
        -*) echo "Usage : $(basename "$0") [--force] [<client>…]" >&2; exit 2 ;;
        *) only+=("$arg") ;;
    esac
done
[[ "$busy_hours" =~ ^[0-9]+$ ]] || die "BUSY_HOURS doit être un nombre entier d'heures"
[[ -d "$root/instances" ]] || die "aucune instance dans $root"
command -v docker > /dev/null || die "docker introuvable"

# ------------------------------------------------------------------ Version voulue
wanted_before=$(cat "$root/images.env" 2> /dev/null || true)
if [[ -n "${PHP_IMAGE:-}" || -n "${WEB_IMAGE:-}" ]]; then
    [[ -n "${PHP_IMAGE:-}" && -n "${WEB_IMAGE:-}" ]] || die "PHP_IMAGE et WEB_IMAGE vont ensemble"
    # Contrôlées avant d'être enregistrées : une image introuvable ne doit pas devenir la version des instances.
    image_ready "$PHP_IMAGE" || die "image introuvable : $PHP_IMAGE"
    image_ready "$WEB_IMAGE" || die "image introuvable : $WEB_IMAGE"
    printf 'PHP_IMAGE=%s\nWEB_IMAGE=%s\n' "$PHP_IMAGE" "$WEB_IMAGE" > "$root/images.env"
fi
[[ -s "$root/images.env" ]] || die "aucune version voulue : relancer avec PHP_IMAGE=… et WEB_IMAGE=…"
php_image=$(conf "$root/images.env" PHP_IMAGE)
web_image=$(conf "$root/images.env" WEB_IMAGE)
[[ -n "$php_image" && -n "$web_image" ]] || die "$root/images.env est incomplet"
image_ready "$php_image" || die "image introuvable : $php_image"
image_ready "$web_image" || die "image introuvable : $web_image"
wanted=$(printf 'PHP_IMAGE=%s\nWEB_IMAGE=%s' "$php_image" "$web_image")

# ------------------------------------------------------------------ Instances à traiter
clients=()
if (( ${#only[@]} > 0 )); then
    for client in "${only[@]}"; do
        [[ -s "$root/instances/$client/stack.env" ]] || die "instance inconnue : $client"
        clients+=("$client")
    done
else
    for dir in "$root"/instances/*/; do
        [[ -s "${dir}stack.env" ]] || continue
        clients+=("$(basename "$dir")")
    done
fi
(( ${#clients[@]} > 0 )) || die "aucune instance à mettre à jour"

docker exec "$db_container" pg_isready -U postgres > /dev/null 2>&1 \
    || die "le serveur PostgreSQL commun ($db_container) ne répond pas"

lock_instances
trap unlock_instances EXIT

# Vrai si un loto est en cours dans la base donnée. Une requête en échec arrête la mise à jour : dans le doute,
# on ne touche pas à l'instance.
busy() {
    local answer
    answer=$(docker exec "$db_container" psql -X -At -v ON_ERROR_STOP=1 -U postgres -d "$1" -c "
        SELECT (EXISTS (SELECT 1 FROM game g JOIN event e ON e.id = g.event_id
                         WHERE g.status = 'RUNNING' AND e.date::date BETWEEN current_date - 1 AND current_date)
             OR EXISTS (SELECT 1 FROM draw
                         WHERE created_at > (now() AT TIME ZONE 'utc') - interval '$busy_hours hours'))::int") \
        || die "impossible de savoir si un loto est en cours dans la base $1"
    [[ "$answer" == 1 ]]
}

updated=() current_ok=() skipped=() failed="" remaining=()
state() { printf '%s\n' "$2" > "$root/instances/$1/etat"; }

log "version voulue : $php_image $web_image"
index=0
for client in "${clients[@]}"; do
    index=$((index + 1))
    dir="$root/instances/$client"
    cd "$dir"
    current=$(cat images.env 2> /dev/null || true)

    if [[ "$current" == "$wanted" && "$(cat etat 2> /dev/null || true)" == active ]]; then
        log "$client : déjà à jour"
        current_ok+=("$client")
        continue
    fi

    db_name=$(conf stack.env DB_NAME)
    [[ -n "$db_name" ]] || { failed="$client (DB_NAME absent de stack.env)"; break; }

    if [[ "$force" == false ]] && busy "$db_name"; then
        log "$client : loto en cours, instance laissée telle quelle"
        skipped+=("$client")
        continue
    fi

    log "$client : mise à jour"
    quiet_log="$dir/update.log"
    cp "$here/compose.yaml" "$here/compose.local.yaml" "$dir/"
    printf '%s\n' "$wanted" > candidate.env

    # Sauvegarde avant toute migration.
    mkdir -p backups
    backup="backups/$(date -u +%Y%m%dT%H%M%SZ)-avant-mise-a-jour.dump"
    if ! docker exec "$db_container" pg_dump -U postgres -d "$db_name" -Fc > "$backup" || [[ ! -s "$backup" ]]; then
        rm -f "$backup" candidate.env
        failed="$client (sauvegarde en échec, instance non touchée)"
        break
    fi

    state "$client" "en cours"
    images_file=candidate.env
    if ! quiet compose config --quiet; then
        rm -f candidate.env
        state "$client" "active"
        failed="$client (configuration Compose refusée, instance non touchée)"
        break
    fi
    # Les services qui écrivent sont arrêtés pendant les migrations.
    quiet compose stop php web mercure || true
    reason="nouvelle version malsaine"
    if ! migrate; then
        reason="migration en échec, sauvegarde $dir/$backup"
    elif quiet compose up -d --force-recreate --quiet-pull && wait_healthy; then
        [[ -z "$current" ]] || printf '%s\n' "$current" > previous.env
        mv candidate.env images.env
        state "$client" "active"
        printf '%s %s %s %s\n' "$(date '+%F %T')" "$client" "$php_image" "$web_image" >> "$root/updates.log"
        updated+=("$client")
        continue
    fi

    # Échec : retour aux images précédentes. La sortie de la commande fautive est déjà affichée.
    { cat update.log; compose logs --tail 30 php web; } > update-echec.log 2>&1 || true
    rm -f candidate.env
    if [[ -n "$current" && "$current" != "$wanted" ]]; then
        log "$client : $reason, retour à la version précédente"
        images_file=images.env
        if quiet compose up -d --force-recreate --quiet-pull && wait_healthy; then
            state "$client" "active"
            failed="$client ($reason ; version précédente remise en service ; journal $dir/update-echec.log)"
            break
        fi
    fi
    state "$client" "en erreur"
    failed="$client ($reason ; pas de version précédente à remettre en service ; journal $dir/update-echec.log)"
    break
done
unset images_file

if [[ -n "$failed" ]]; then
    for client in "${clients[@]:$index}"; do remaining+=("$client"); done
    # La version qui vient d'échouer ne doit pas servir aux prochaines créations d'instance.
    if [[ -n "$wanted_before" && "$wanted_before" != "$wanted" ]]; then
        printf '%s\n' "$wanted_before" > "$root/images.env"
    fi
fi

# ------------------------------------------------------------------ Bilan
list() { if (( $# > 0 )); then printf '%s' "$*"; else printf 'aucune'; fi; }
echo
echo "Mises à jour      : $(list ${updated[@]+"${updated[@]}"})"
echo "Déjà à jour       : $(list ${current_ok[@]+"${current_ok[@]}"})"
echo "Loto en cours     : $(list ${skipped[@]+"${skipped[@]}"})"
if [[ -n "$failed" ]]; then
    echo "En échec          : $failed"
    echo "Non traitées      : $(list ${remaining[@]+"${remaining[@]}"})"
    [[ -z "$wanted_before" || "$wanted_before" == "$wanted" ]] || echo "Version voulue    : revenue à celle d'avant."
    exit 1
fi
if (( ${#skipped[@]} > 0 )); then
    echo "Relancer la même commande après le loto, ou avec --force."
    exit 3
fi

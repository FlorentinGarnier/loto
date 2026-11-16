# Fonctions communes à provision.sh et update.sh. À charger par « source », pas à lancer.
# Attend : root (dossier des instances), prefix (préfixe des projets Compose).

# Valeur d'une variable d'un fichier d'environnement ; vide si elle est absente.
conf() { { grep -E "^$2=" "$1" 2> /dev/null || true; } | tail -n1 | cut -d= -f2-; }

# Sortie d'une commande gardée dans un journal, affichée seulement en cas d'échec.
quiet_log=/dev/null
quiet() {
    if ! "$@" > "$quiet_log" 2>&1; then
        [[ "$quiet_log" == /dev/null ]] || cat "$quiet_log" >&2
        return 1
    fi
}

# Une image est utilisable si elle est déjà sur la machine ou si elle se télécharge.
image_ready() { docker image inspect "$1" > /dev/null 2>&1 || docker pull --quiet "$1" > /dev/null 2>&1; }

# Une seule opération à la fois sur les instances (création ou mise à jour).
lock_instances() {
    instances_lock="$root/.verrou"
    mkdir "$instances_lock" 2> /dev/null \
        || die "une autre opération est en cours ($instances_lock). Si ce n'est pas le cas : rmdir $instances_lock"
}
unlock_instances() { [[ -z "${instances_lock:-}" ]] || rmdir "$instances_lock" 2> /dev/null || true; }

# Docker Compose pour l'instance du dossier courant. Les images viennent du fichier désigné par images_file
# (par défaut images.env, la version en service de l'instance), et de lui seul : PHP_IMAGE et WEB_IMAGE sont
# retirées de l'environnement, où Compose les lirait en priorité (un retour arrière repartirait sur la nouvelle version).
compose() {
    local images=${images_file:-images.env}
    if [[ -n "$(conf stack.env LOCAL_PORT)" ]]; then
        env -u PHP_IMAGE -u WEB_IMAGE \
            docker compose --env-file stack.env --env-file "$images" -f compose.yaml -f compose.local.yaml "$@"
    else
        env -u PHP_IMAGE -u WEB_IMAGE \
            docker compose --env-file stack.env --env-file "$images" -f compose.yaml "$@"
    fi
}

# Migrations de l'instance du dossier courant, avec les images de images_file.
# Les « || return 1 » sont nécessaires : appelée dans un test (if ! migrate), la fonction n'est plus arrêtée
# par « set -e » et renverrait le code de sa dernière commande.
migrate() {
    quiet compose run --rm --no-deps php php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration \
        || return 1
    if compose run --rm --no-deps php php bin/console list --raw 2> /dev/null | grep '^messenger:setup-transports ' > /dev/null; then
        quiet compose run --rm --no-deps php php bin/console messenger:setup-transports --no-interaction || return 1
    fi
    return 0
}

# Vrai dès que l'instance du dossier courant répond sur /healthz (une minute d'attente au plus).
wait_healthy() {
    local _
    for _ in $(seq 1 30); do
        if compose exec -T web wget -q -O /dev/null http://127.0.0.1/healthz 2> /dev/null; then return 0; fi
        sleep 2
    done
    return 1
}

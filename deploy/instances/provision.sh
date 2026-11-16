#!/usr/bin/env bash
# Crée l'instance d'un client, ou la remet d'aplomb : la commande se relance sans dégât.
#
#   provision.sh <client>                     ex. : provision.sh ape-jules-ferry
#   make provision CLIENT=<client>            même chose, depuis la racine du dépôt
#
# À lancer sur la machine qui porte Docker et Traefik, avec un compte du groupe docker.
#
# Étapes : contrôle de l'identifiant, socle PostgreSQL, rôle et base du client, secrets, migrations,
# démarrage, sonde de santé. L'instance répond ensuite sur https://<client>.<domaine>.
#
# Ce que la commande écrit, sous INSTANCES_ROOT :
#   images.env                     version voulue pour les instances (PHP_IMAGE, WEB_IMAGE)
#   socle/                         serveur PostgreSQL commun et son mot de passe
#   instances/<client>/            compose.yaml, stack.env, app.env, mercure.env, images.env (version en service),
#                                  etat, provision.log, backups/
# Les fichiers de secrets ne sont écrits qu'une fois : une relance garde les mêmes.
#
# Réglages (variables d'environnement) :
#   PHP_IMAGE, WEB_IMAGE           images à servir ; enregistrées dans images.env, puis facultatives
#   INSTANCES_ROOT                 dossier des instances                       (défaut /srv/quinou)
#   INSTANCES_DOMAIN               domaine des sous-domaines clients           (défaut quinou.fr)
#   INSTANCES_PREFIX               préfixe des projets Compose et du socle     (défaut quinou)
#   TRAEFIK_NETWORK, TRAEFIK_ENTRYPOINT, TRAEFIK_CERTRESOLVER                  (défaut proxy, websecure, letsencrypt)
#   LOCAL_PORT                     essai sans Traefik : publie l'instance sur http://localhost:<port>
set -Eeuo pipefail
umask 077

here=$(cd "$(dirname "$0")" && pwd)
client=${1:-}
root=${INSTANCES_ROOT:-/srv/quinou}
domain=${INSTANCES_DOMAIN:-quinou.fr}
prefix=${INSTANCES_PREFIX:-quinou}
traefik_network=${TRAEFIK_NETWORK:-proxy}
traefik_entrypoint=${TRAEFIK_ENTRYPOINT:-websecure}
traefik_certresolver=${TRAEFIK_CERTRESOLVER:-letsencrypt}

# Noms qui ne peuvent pas être ceux d'un client : ils servent, ou serviront, à la plateforme (ORG-7).
reserved="www staging app admin api mail smtp imap pop ftp ns ns1 ns2 mx cdn static assets status aide support
blog docs demo test dev portail portal compte comptes client clients login auth paiement facture factures
quinou socle traefik mercure autodiscover autoconfig cloud"

log() { printf '[provision %s] %s\n' "$(date '+%T')" "$*"; }
die() { printf '[provision] ERREUR : %s\n' "$*" >&2; exit 1; }
secret() { openssl rand -hex 32; }
# shellcheck source=deploy/instances/lib.sh
. "$here/lib.sh"

# ------------------------------------------------------------------ Contrôles
[[ -n "$client" ]] || { echo "Usage : $(basename "$0") <client>" >&2; exit 2; }
[[ "$client" =~ ^[a-z0-9][a-z0-9-]{1,30}[a-z0-9]$ ]] \
    || die "identifiant « $client » refusé : 3 à 32 caractères, lettres minuscules, chiffres et tirets, sans tiret au début ni à la fin"
[[ "$client" != *--* ]] || die "identifiant « $client » refusé : deux tirets à la suite"
for name in $reserved; do
    [[ "$client" != "$name" ]] || die "identifiant « $client » réservé à la plateforme"
done
command -v docker > /dev/null || die "docker introuvable"
docker compose version > /dev/null 2>&1 || die "docker compose introuvable"
command -v openssl > /dev/null || die "openssl introuvable"

project="$prefix-$client"
host="$client.$domain"
base_url="https://$host"
db_network="$prefix-db"
db_container="$prefix-postgres"
# Rôle et base du client : préfixés, pour ne jamais commencer par un chiffre.
db_name="c_${client//-/_}"
dir="$root/instances/$client"

mkdir -p "$root" 2> /dev/null || die "impossible de créer $root : le créer une fois avec les droits root, au nom de ce compte"
[[ -w "$root" ]] || die "$root n'est pas inscriptible par ce compte"

lock_instances
state() { [[ -d "$dir" ]] && printf '%s\n' "$1" > "$dir/etat"; return 0; }
trap unlock_instances EXIT
trap 'failed_line=$LINENO failed_command=$BASH_COMMAND; state "en erreur"; printf "[provision] ERREUR ligne %s : %s\n" "$failed_line" "$failed_command" >&2' ERR

# ------------------------------------------------------------------ Images
if [[ -n "${PHP_IMAGE:-}" || -n "${WEB_IMAGE:-}" ]]; then
    [[ -n "${PHP_IMAGE:-}" && -n "${WEB_IMAGE:-}" ]] || die "PHP_IMAGE et WEB_IMAGE vont ensemble"
    # Contrôlées avant d'être enregistrées : une image introuvable ne doit pas devenir la version des instances.
    image_ready "$PHP_IMAGE" || die "image introuvable : $PHP_IMAGE"
    image_ready "$WEB_IMAGE" || die "image introuvable : $WEB_IMAGE"
    printf 'PHP_IMAGE=%s\nWEB_IMAGE=%s\n' "$PHP_IMAGE" "$WEB_IMAGE" > "$root/images.env"
fi
[[ -s "$root/images.env" ]] || die "aucune image connue : relancer avec PHP_IMAGE=… et WEB_IMAGE=…"
php_image=$(conf "$root/images.env" PHP_IMAGE)
web_image=$(conf "$root/images.env" WEB_IMAGE)
[[ -n "$php_image" && -n "$web_image" ]] || die "$root/images.env est incomplet"
image_ready "$php_image" || die "image introuvable : $php_image"
image_ready "$web_image" || die "image introuvable : $web_image"

# ------------------------------------------------------------------ Réseau de Traefik
if ! docker network inspect "$traefik_network" > /dev/null 2>&1; then
    [[ -n "${LOCAL_PORT:-}" ]] || die "réseau Docker « $traefik_network » absent : Traefik n'est pas en place sur cette machine"
    docker network create "$traefik_network" > /dev/null
    log "réseau $traefik_network créé (essai local)"
fi

# ------------------------------------------------------------------ Socle PostgreSQL
mkdir -p "$root/socle"
cp "$here/socle.compose.yaml" "$root/socle/compose.yaml"
[[ -s "$root/socle/postgres.env" ]] || printf 'POSTGRES_PASSWORD=%s\n' "$(secret)" > "$root/socle/postgres.env"
printf 'POSTGRES_CONTAINER=%s\nDB_NETWORK=%s\n' "$db_container" "$db_network" > "$root/socle/stack.env"
log "socle PostgreSQL"
quiet_log="$root/socle/provision.log"
(cd "$root/socle" && quiet docker compose -p "$prefix-socle" --env-file stack.env -f compose.yaml up -d --wait --quiet-pull)

# ------------------------------------------------------------------ Fichiers de l'instance
mkdir -p "$dir/backups"
state "en cours"
cp "$here/compose.yaml" "$here/compose.local.yaml" "$dir/"
cd "$dir"
quiet_log="$dir/provision.log"

if [[ -n "${LOCAL_PORT:-}" ]]; then
    host=localhost
    base_url="http://localhost:$LOCAL_PORT"
fi

new_instance=false
admin_password=
if [[ ! -s app.env ]]; then
    new_instance=true
    db_password=$(secret)
    mercure_secret=$(secret)
    admin_password=$(openssl rand -base64 18 | tr -d '/+=')
    # Le mot de passe passe par l'environnement, pas par la ligne de commande.
    admin_hash=$(ADMIN_PASSWORD="$admin_password" docker run --rm -e ADMIN_PASSWORD "$php_image" \
        php -r 'echo password_hash(getenv("ADMIN_PASSWORD"), PASSWORD_BCRYPT);')
    [[ "$admin_hash" == \$2y\$* ]] || die "le hachage du mot de passe a échoué"

    cat > app.env << EOF
# Variables de l'application pour l'instance « $client ». Écrit par provision.sh, jamais réécrit ensuite.
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=$(secret)
DATABASE_URL=postgresql://$db_name:$db_password@$db_container:5432/$db_name?serverVersion=16&charset=utf8
DEFAULT_URI=$base_url
TRUSTED_PROXIES=172.16.0.0/12
MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0
MAILER_DSN=null://null
ADMIN_PASSWORD_HASH='$admin_hash'
MERCURE_URL=http://mercure/.well-known/mercure
MERCURE_PUBLIC_URL=$base_url/.well-known/mercure
MERCURE_JWT_SECRET=$mercure_secret
EOF
    cat > mercure.env << EOF
# Hub Mercure de l'instance « $client ». Même clé que MERCURE_JWT_SECRET dans app.env.
MERCURE_PUBLISHER_JWT_KEY=$mercure_secret
MERCURE_SUBSCRIBER_JWT_KEY=$mercure_secret
MERCURE_EXTRA_DIRECTIVES="anonymous
cors_origins $base_url"
EOF
    cat > stack.env << EOF
# Variables lues par Docker Compose pour l'instance « $client ».
COMPOSE_PROJECT_NAME=$project
APP_HOST=$host
TRAEFIK_NETWORK=$traefik_network
TRAEFIK_ENTRYPOINT=$traefik_entrypoint
TRAEFIK_CERTRESOLVER=$traefik_certresolver
DB_NETWORK=$db_network
APP_CPUS=0.5
APP_MEMORY=384m
DB_NAME=$db_name
DB_PASSWORD=$db_password
LOCAL_PORT=${LOCAL_PORT:-}
EOF
else
    log "instance déjà connue : secrets et adresse conservés"
    [[ -s stack.env && -s mercure.env ]] || die "$dir est incomplet (stack.env ou mercure.env manquant)"
    base_url=$(conf app.env DEFAULT_URI)
    db_password=$(conf stack.env DB_PASSWORD)
    [[ -n "$db_password" ]] || die "DB_PASSWORD absent de $dir/stack.env"
fi

# L'instance prend la version voulue : une création, ou une relance, la met à ce niveau.
cp "$root/images.env" images.env
compose config --quiet

# ------------------------------------------------------------------ Rôle et base du client
log "base $db_name"
docker exec -i "$db_container" psql -q -X -v ON_ERROR_STOP=1 -U postgres \
    -v role="$db_name" -v pass="$db_password" -v db="$db_name" > /dev/null << 'SQL'
SELECT format('CREATE ROLE %I LOGIN PASSWORD %L', :'role', :'pass')
    WHERE NOT EXISTS (SELECT FROM pg_roles WHERE rolname = :'role') \gexec
SELECT format('ALTER ROLE %I LOGIN PASSWORD %L', :'role', :'pass') \gexec
SELECT format('CREATE DATABASE %I OWNER %I', :'db', :'role')
    WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = :'db') \gexec
-- Seul le rôle du client se connecte à sa base.
SELECT format('REVOKE ALL ON DATABASE %I FROM PUBLIC', :'db') \gexec
SQL

# ------------------------------------------------------------------ Migrations et démarrage
log "migrations"
migrate
log "démarrage"
quiet compose up -d --force-recreate --quiet-pull

if ! wait_healthy; then
    compose ps
    compose logs --tail 30 php web || true
    die "l'instance ne répond pas sur /healthz ; corriger puis relancer la même commande"
fi

state "active"
printf '%s %s %s %s\n' "$(date '+%F %T')" "$client" "$php_image" "$web_image" >> "$root/provisions.log"

echo
echo "Instance « $client » en service : $base_url"
if [[ "$new_instance" == true ]]; then
    echo "  Compte        : admin"
    echo "  Mot de passe  : $admin_password"
    echo "  Ce mot de passe n'est enregistré nulle part en clair : le transmettre maintenant."
else
    echo "  Compte et mot de passe inchangés."
fi

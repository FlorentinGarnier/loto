#!/usr/bin/env bash
# Sauvegarde la base de chaque instance cliente, ou d'une seule.
#
#   backup.sh                 toutes les instances
#   backup.sh <client>        une seule
#
# Un fichier pg_dump par base, dans instances/<client>/backups/ ; les 30 derniers sont gardés.
# À planifier chaque nuit (cron du compte qui possède INSTANCES_ROOT). Restauration :
#   docker exec -i <préfixe>-postgres pg_restore -U postgres --clean --if-exists -d <base> < fichier.dump
set -Eeuo pipefail
umask 077

root=${INSTANCES_ROOT:-/srv/quinou}
prefix=${INSTANCES_PREFIX:-quinou}
db_container="$prefix-postgres"
only=${1:-}

[[ -d "$root/instances" ]] || { echo "[backup] aucune instance dans $root" >&2; exit 1; }
failed=0
for dir in "$root"/instances/*/; do
    dir=${dir%/}
    client=$(basename "$dir")
    [[ -z "$only" || "$only" == "$client" ]] || continue
    db_name=$({ grep -E '^DB_NAME=' "$dir/stack.env" 2> /dev/null || true; } | tail -n1 | cut -d= -f2-)
    if [[ -z "$db_name" ]]; then
        echo "[backup] $client : DB_NAME introuvable, instance ignorée" >&2
        failed=1
        continue
    fi
    mkdir -p "$dir/backups"
    file="$dir/backups/$(date -u +%Y%m%dT%H%M%SZ).dump"
    if docker exec "$db_container" pg_dump -U postgres -d "$db_name" -Fc > "$file" && [[ -s "$file" ]]; then
        echo "[backup] $client : $file"
        ls -1t "$dir"/backups/*.dump 2> /dev/null | tail -n +31 | xargs rm -f 2> /dev/null || true
    else
        rm -f "$file"
        echo "[backup] $client : sauvegarde en échec" >&2
        failed=1
    fi
done
exit "$failed"

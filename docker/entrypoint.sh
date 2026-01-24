#!/bin/sh
set -e

# Compilation des assets si le dossier est vide
if [ ! -f "/app/public/assets/manifest.json" ]; then
    echo "Compiling assets..."
    php bin/console asset-map:compile
fi

# Démarrage de PHP-FPM
exec php-fpm

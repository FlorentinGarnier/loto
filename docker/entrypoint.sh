#!/bin/sh
set -e

# Compilation des assets au démarrage
echo "Compiling assets..."
php bin/console asset-map:compile --no-interaction

# Démarrage de PHP-FPM
exec php-fpm

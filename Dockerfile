# Dockerfile pour l'application Symfony
FROM php:8.2-fpm-alpine AS base

# Installation des dépendances système
RUN apk add --no-cache \
    postgresql-dev \
    icu-dev \
    libzip-dev \
    git \
    unzip

# Installation des extensions PHP
RUN docker-php-ext-install \
    pdo_pgsql \
    intl \
    opcache \
    zip

# Configuration d'OPcache pour la production
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini \
    && echo "opcache.memory_consumption=256" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini \
    && echo "opcache.max_accelerated_files=20000" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini \
    && echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini

# Installation de Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Configuration du répertoire de travail
WORKDIR /app

# ===== Stage de build =====
FROM base AS build

# Copie des fichiers de dépendances
COPY composer.json composer.lock symfony.lock ./

ENV APP_ENV=prod APP_DEBUG=0

# Installation des dépendances Composer (sans les dev)
RUN composer install --no-dev --no-scripts --no-progress --optimize-autoloader

# Copie du reste de l'application
COPY . .

# Nettoyage
RUN rm -rf .env.dev .env.test tests/ features/ .git/

# ===== Stage de production =====
FROM base AS production

# Variables d'environnement par défaut (peuvent être overridées au runtime)
ENV APP_ENV=prod \
    APP_DEBUG=0

# Copie des fichiers depuis le stage de build
COPY --from=build /app /app

# Configuration des permissions
RUN chown -R www-data:www-data /app/var

# Port exposé (PHP-FPM)
EXPOSE 9000

# Utilisateur non-root
USER www-data

# Commande de démarrage
CMD ["php-fpm"]

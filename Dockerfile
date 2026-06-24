FROM php:8.4-fpm

# libonig-dev is required to build the mbstring extension. pdo_sqlite (used for
# the throwaway PDO handle) is bundled with the base image. The engine's _()
# calls are covered by a global shim (src/Engine/functions.php), so the gettext
# extension is not needed.
RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev unzip \
    && docker-php-ext-install mbstring \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

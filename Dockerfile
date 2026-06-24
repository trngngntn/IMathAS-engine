# Production image for the IMathAS question engine.
#
# The engine has no runtime Composer dependency — classes load via
# src/Engine/autoload.php — so this is a single, plain image: PHP + mbstring +
# the application code. No Composer, no vendor/, no dev dependencies, no build
# stage. (Dev workflow uses Dockerfile.dev, which adds Composer for PHPUnit.)

FROM php:8.4-fpm

# mbstring needs libonig-dev to build. pdo_sqlite (throwaway PDO handle) is
# bundled with the base image. gettext is not needed (the engine's _() calls
# are covered by src/Engine/functions.php).
RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev \
    && docker-php-ext-install mbstring \
    && rm -rf /var/lib/apt/lists/*

# Production OPcache: the image code is immutable, so never stat files to
# revalidate — compile once, serve cached bytecode for the life of the process.
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'opcache.interned_strings_buffer=16'; \
        echo 'opcache.max_accelerated_files=20000'; \
        echo 'opcache.memory_consumption=128'; \
    } > /usr/local/etc/php/conf.d/opcache-prod.ini

WORKDIR /var/www/html
# Application code only — .dockerignore keeps tests/docs/dev artifacts and
# vendor/ out of the image.
COPY . .

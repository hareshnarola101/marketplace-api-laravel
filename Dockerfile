# Development image: PHP 8.3 CLI with the extensions this app needs.
# Serves HTTP with `php artisan serve`; a production image would use php-fpm or FrankenPHP.
FROM php:8.3-cli-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libzip-dev libicu-dev \
    && docker-php-ext-install pdo_mysql bcmath intl pcntl zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ARG UID=1000
ARG GID=1000
RUN groupadd --gid "${GID}" app \
    && useradd --uid "${UID}" --gid app --create-home app

WORKDIR /var/www/html

COPY --chown=app:app composer.json composer.lock ./
USER app
RUN composer install --no-interaction --prefer-dist --no-scripts --no-autoloader

COPY --chown=app:app . .
RUN composer dump-autoload --optimize

COPY --chown=app:app docker/entrypoint.sh /usr/local/bin/entrypoint
ENTRYPOINT ["entrypoint"]

EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

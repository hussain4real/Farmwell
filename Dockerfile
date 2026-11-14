FROM node:22-bookworm-slim AS frontend
WORKDIR /app
COPY package*.json ./
RUN npm ci

FROM composer:2 AS composer-bin

FROM php:8.5-apache-bookworm AS runtime
RUN apt-get update && apt-get install -y --no-install-recommends \
    git libpq-dev libzip-dev libpng-dev unzip \
    && docker-php-ext-install -j2 pdo_pgsql zip exif pcntl gd \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*
RUN sed -i 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
    && printf '<Directory /var/www/html/public>\nAllowOverride All\nRequire all granted\n</Directory>\n' >> /etc/apache2/apache2.conf \
    && printf 'upload_max_filesize=10M\npost_max_size=64M\ndisplay_errors=Off\nlog_errors=On\n' \
    > /usr/local/etc/php/conf.d/farmwell-uploads.ini
WORKDIR /var/www/html

FROM runtime AS build
COPY --from=composer-bin /usr/bin/composer /usr/local/bin/composer
COPY --from=frontend /usr/local/bin/node /usr/local/bin/node
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist
COPY . .
RUN composer dump-autoload --no-dev --optimize --no-scripts \
    && php artisan package:discover --ansi --no-interaction
COPY --from=frontend /app/node_modules /app/node_modules
RUN cd /var/www/html && ln -s /app/node_modules node_modules \
    && php artisan wayfinder:generate --with-form --no-interaction \
    && /app/node_modules/.bin/vite build \
    && rm node_modules
FROM runtime AS app
COPY --from=build --chown=www-data:www-data /var/www/html/app ./app
COPY --from=build --chown=www-data:www-data /var/www/html/bootstrap ./bootstrap
COPY --from=build --chown=www-data:www-data /var/www/html/config ./config
COPY --from=build --chown=www-data:www-data /var/www/html/database ./database
COPY --from=build --chown=www-data:www-data /var/www/html/public ./public
COPY --from=build --chown=www-data:www-data /var/www/html/resources/views ./resources/views
COPY --from=build --chown=www-data:www-data /var/www/html/routes ./routes
COPY --from=build --chown=www-data:www-data /var/www/html/storage ./storage
COPY --from=build --chown=www-data:www-data /var/www/html/vendor ./vendor
COPY --from=build /var/www/html/artisan /var/www/html/composer.json /var/www/html/composer.lock ./
EXPOSE 80
CMD ["apache2-foreground"]

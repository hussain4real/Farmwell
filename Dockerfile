FROM node:22-bookworm-slim AS frontend
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .

FROM composer:2 AS composer-bin

FROM php:8.5-apache-bookworm AS app
RUN apt-get update && apt-get install -y --no-install-recommends \
    git libpq-dev libzip-dev unzip \
    && docker-php-ext-install -j2 pdo_pgsql zip exif pcntl \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer-bin /usr/bin/composer /usr/local/bin/composer
COPY --from=frontend /usr/local/bin/node /usr/local/bin/node
WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist
COPY . .
RUN composer dump-autoload --no-dev --optimize --no-scripts \
    && php artisan package:discover --ansi --no-interaction \
    && sed -i 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
    && printf '<Directory /var/www/html/public>\nAllowOverride All\nRequire all granted\n</Directory>\n' >> /etc/apache2/apache2.conf \
    && chown -R www-data:www-data storage bootstrap/cache
COPY --from=frontend /app/node_modules /app/node_modules
RUN cd /var/www/html && ln -s /app/node_modules node_modules \
    && php artisan wayfinder:generate --with-form --no-interaction \
    && /app/node_modules/.bin/vite build \
    && rm node_modules
EXPOSE 80
CMD ["apache2-foreground"]

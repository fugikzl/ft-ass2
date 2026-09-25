FROM php:8.3-fpm

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && docker-php-ext-install pdo_mysql opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-progress --optimize-autoloader

COPY . .
RUN mkdir -p var/cache/doctrine \
    && composer dump-autoload --no-interaction --optimize \
    && chown -R www-data:www-data /var/www/html

CMD ["php-fpm"]

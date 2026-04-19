FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    icu-dev \
    libzip-dev \
    oniguruma-dev \
    unzip \
    git \
    bash \
  && docker-php-ext-configure intl \
  && docker-php-ext-install -j$(nproc) intl pdo_mysql opcache \
  && docker-php-ext-install -j$(nproc) zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# PHP runtime overrides
COPY docker/php/conf.d/*.ini /usr/local/etc/php/conf.d/

COPY docker/php/entrypoint.sh /usr/local/bin/app-entrypoint
RUN chmod +x /usr/local/bin/app-entrypoint

ENTRYPOINT ["/usr/local/bin/app-entrypoint"]

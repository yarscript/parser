FROM php:8.0-fpm

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

RUN apt-get update && apt-get upgrade -y && apt-get install apt-utils -y \
    && install-php-extensions zip gd xdebug \
    && docker-php-ext-enable xdebug \
    && docker-php-source delete \
    && apt-get autoremove --purge -y \
    && apt-get autoclean -y \
    && apt-get clean -y \

EXPOSE 9000

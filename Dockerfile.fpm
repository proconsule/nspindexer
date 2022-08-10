FROM php:8.1.9-fpm-alpine3.16 AS base

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

FROM base
COPY . /var/www/html/

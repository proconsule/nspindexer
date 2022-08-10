FROM php:8.1.9-apache-bullseye AS base

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
RUN rm /var/log/apache2/access.log && touch /var/log/apache2/access.log

# This is split into two stages to make optimal use of Docker build caches
FROM base
COPY . /var/www/html/
FROM php:8.1.9-apache-bullseye AS base

ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
RUN chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions sockets
RUN rm /var/log/apache2/access.log && touch /var/log/apache2/access.log

# This is split into two stages to make optimal use of Docker build caches
FROM base
COPY . /var/www/html/

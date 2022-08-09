FROM php:8.1.9-fpm-alpine3.16

ADD . /var/www/html/
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
RUN cp /var/www/html/config.default.php /var/www/html/config.php

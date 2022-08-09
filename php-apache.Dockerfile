FROM php:8.1.9-apache-bullseye

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
ADD . /var/www/html/
RUN cp /var/www/html/config.default.php /var/www/html/config.php

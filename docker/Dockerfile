FROM php:8.0-apache

RUN apt update \
    && apt -y upgrade \
    && apt install -y \
       libgmp-dev

RUN docker-php-ext-install gmp

RUN pecl install zstd \
    && docker-php-ext-enable zstd

FROM php:7.3-apache

RUN apt update \
    && apt -y upgrade \
    && apt install -y \
       libgmp-dev

RUN docker-php-ext-install gmp

RUN apt install -y \
       git \
       libzstd-dev \
       libzstd1

RUN git clone --recursive --depth=1 https://github.com/proconsule/php-ext-yazstd && cd php-ext-yazstd && phpize && ./configure && make && make install && cp yazstd.ini /usr/local/etc/php/conf.d


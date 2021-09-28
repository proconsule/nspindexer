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

RUN apt-cache search php-cpp

RUN git clone https://github.com/CopernicaMarketingSoftware/PHP-CPP && cd PHP-CPP && make && make install

RUN git clone https://github.com/proconsule/php-ugly-zstd && cd php-ugly-zstd && make && make install

RUN ldconfig

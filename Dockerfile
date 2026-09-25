FROM php:8.2-apache

RUN docker-php-ext-install mysqli \
    && a2enmod rewrite

COPY docker/php/deepcheck.ini /usr/local/etc/php/conf.d/deepcheck.ini
COPY docker/apache/zz-deepcheck.conf /etc/apache2/conf-available/zz-deepcheck.conf
RUN a2enconf zz-deepcheck

WORKDIR /var/www/html

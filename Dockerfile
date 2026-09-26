FROM php:8.2-apache

RUN docker-php-ext-install mysqli \
    && a2enmod rewrite

# Python para o script de envio de e-mail das NCs (scripts/enviar_email.py),
# chamado pelo PHP. smtplib vem na biblioteca padrão; reportlab gera o PDF.
RUN apt-get update \
    && apt-get install -y --no-install-recommends python3 python3-reportlab \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php/deepcheck.ini /usr/local/etc/php/conf.d/deepcheck.ini
COPY docker/apache/zz-deepcheck.conf /etc/apache2/conf-available/zz-deepcheck.conf
RUN a2enconf zz-deepcheck

WORKDIR /var/www/html

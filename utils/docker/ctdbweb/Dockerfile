FROM php:7.2-apache
RUN apt-get update && apt-get install -y \
        postgresql-client \
        libpq-dev \
        libzip-dev \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-configure pgsql \
    && docker-php-ext-install -j$(nproc) pgsql \
    && docker-php-ext-configure zip --with-libzip \
    && docker-php-ext-install zip
RUN pear install channel://pear.php.net/XML_Serializer-0.21.0
COPY db.cue.tools/ /var/www/html/
RUN mkdir /var/www/html/parity && chown www-data.www-data /var/www/html/parity
RUN curl -sS https://getcomposer.org/installer | php && php composer.phar require aws/aws-sdk-php


# ===========================================
# Base Stage - Common Dependencies
# ===========================================
FROM php:8.4-apache AS base

# Install system packages and PHP extensions
RUN apt-get update && apt-get install -y \
        postgresql-client \
        libpq-dev \
        libzip-dev \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-configure pgsql \
    && docker-php-ext-install -j$(nproc) pgsql \
    && docker-php-ext-install zip

# Install PEAR XML_Serializer
RUN pear install channel://pear.php.net/XML_Serializer-0.21.0 \
    && rm -rf /tmp/pear

# Install Composer and AWS SDK globally
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && cd /tmp \
    && composer require aws/aws-sdk-php \
    && mkdir -p /usr/local/lib/php-vendor \
    && mv vendor /usr/local/lib/php-vendor/ \
    && rm -rf /tmp/*

# Configure Apache RemoteIP
COPY remoteip.conf /etc/apache2/conf-available/
RUN ln -s ../mods-available/remoteip.load /etc/apache2/mods-enabled/ \
    && ln -s ../conf-available/remoteip.conf /etc/apache2/conf-enabled/

# ===========================================
# Development Stage
# ===========================================
FROM base AS development

# Create parity directory
RUN mkdir -p /var/www/html/parity && chown www-data:www-data /var/www/html/parity

# Create symlink for vendor directory to use global installation
RUN ln -s /usr/local/lib/php-vendor/vendor /var/www/html/vendor

# Source code will be mounted as volume at runtime

# ===========================================
# Production Stage
# ===========================================
FROM base AS production

# Copy application source
COPY db.cue.tools/ /var/www/html/
COPY ctdbcfg.php /var/www/html/

# Create parity directory (if not exists) and set permissions
RUN mkdir -p /var/www/html/parity && chown www-data:www-data /var/www/html/parity

# Create symlink for vendor directory to use global installation
RUN ln -s /usr/local/lib/php-vendor/vendor /var/www/html/vendor

# Default to production
FROM production


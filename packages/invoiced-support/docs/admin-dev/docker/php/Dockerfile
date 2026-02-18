FROM php:8.3-fpm-alpine AS base
ARG PHP_ENVIRONMENT=production

# Install desired PHP extensions
RUN  curl -sSLf \
            -o /usr/local/bin/install-php-extensions \
            https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions && \
     chmod +x /usr/local/bin/install-php-extensions && \
     install-php-extensions intl opcache pdo_mysql zip apcu xsl && \
     rm /usr/local/bin/install-php-extensions

# Use the default PHP configuration (production or development)
RUN cp "$PHP_INI_DIR/php.ini-$PHP_ENVIRONMENT" "$PHP_INI_DIR/php.ini"
COPY docker/php/$PHP_ENVIRONMENT/* "$PHP_INI_DIR/conf.d/"
COPY docker/php/php-fpm.conf "/usr/local/etc/php-fpm.d/zzz-custom.conf"

# Install php-fpm healthcheck tool
RUN apk add --no-cache fcgi
RUN set -xe && echo "pm.status_path = /status" >> /usr/local/etc/php-fpm.d/zz-docker.conf
COPY docker/php/php-fpm-healthcheck /usr/local/bin/

# Copy application into final image as separate stage
FROM base
COPY . /var/www/html
RUN APP_ENV=prod APP_DEBUG=0 /var/www/html/bin/console cache:clear && \
    chown -R www-data:www-data /var/www/html && \
    chmod -R 777 /var/www/html
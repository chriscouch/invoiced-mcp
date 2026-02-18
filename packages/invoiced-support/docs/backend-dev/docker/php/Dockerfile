FROM php:8.3-fpm-bookworm AS base
ARG PHP_ENVIRONMENT=production
ARG CRON=0
ARG XDEBUG=0
ARG REDIS_HOST="cache:6379"
ARG APP_ENV=production

# Install desired PHP extensions
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions intl opcache pdo_mysql soap bcmath zip gd apcu pcntl xsl sockets igbinary redis-5.3.7 imagick/imagick@master mailparse-3.1.6
RUN if [ "$PHP_ENVIRONMENT" = "development" ]; then install-php-extensions xdebug; fi

# Use the default PHP configuration (production or development)
RUN cp "$PHP_INI_DIR/php.ini-$PHP_ENVIRONMENT" "$PHP_INI_DIR/php.ini"
COPY docker/php/$PHP_ENVIRONMENT/* "$PHP_INI_DIR/conf.d/"
RUN sed -i -e "s/redis_host/$REDIS_HOST/g" "$PHP_INI_DIR/conf.d/session.ini"
COPY docker/php/php-fpm.conf "/usr/local/etc/php-fpm.d/zzz-custom.conf"

# Install wkhtmltopdf
RUN apt update && apt install -y fontconfig libfreetype6 libjpeg62-turbo libpng16-16 libx11-6 libxcb1 libxext6 libxrender1 xfonts-75dpi xfonts-base
RUN if [ `uname -m` = "aarch64" ] || [ `uname -m` = "arm64" ]; then ARCH=arm64; else ARCH=amd64; fi \
    && curl -L https://github.com/wkhtmltopdf/packaging/releases/download/0.12.6.1-3/wkhtmltox_0.12.6.1-3.bookworm_$ARCH.deb > wkhtmltox.deb \
    && dpkg -i wkhtmltox.deb

# Install ghostscript
RUN apt update && apt install -y ghostscript

# Install ps for queue worker
RUN apt update && apt install -y procps

# Install cron
RUN if [ "$CRON" = "1" ]; then \
    apt update && apt install -y cron \
    && crontab -l | { cat; echo "* * * * * /usr/local/bin/php php -d memory_limit=-1 /var/www/html/bin/console cron:run -vvv 2>/proc/1/fd/2"; } | crontab -; \
    fi

# Xdebug is disabled by default until this is fixed https://github.com/docker/for-mac/issues/6235
RUN if [ "$XDEBUG" = "1" ]; then echo -e "xdebug.start_with_request=yes\n" >> /usr/local/etc/php/conf.d/xdebug.ini; fi

# Install php-fpm healthcheck tool
RUN apt update && apt install -y libfcgi-bin
RUN set -xe && echo "pm.status_path = /status" >> /usr/local/etc/php-fpm.d/zz-docker.conf
COPY docker/php/php-fpm-healthcheck /usr/local/bin/

# Copy application into final image as separate stage
FROM base
COPY . /var/www/html
RUN APP_DEBUG=0 php -d memory_limit=-1 /var/www/html/bin/console cache:clear && \
    chown -R www-data:www-data /var/www/html && \
    chmod -R 777 /var/www/html

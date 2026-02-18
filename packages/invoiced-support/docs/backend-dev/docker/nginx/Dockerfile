FROM nginx:mainline-alpine AS base
ARG PHP_FPM_HOST=localhost

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
RUN sed -i "s/php-fpm/$PHP_FPM_HOST/g" /etc/nginx/conf.d/default.conf
COPY docker/nginx/fastcgi_params /etc/nginx/fastcgi_params

# Copy application into final image as separate stage
FROM base
WORKDIR /var/www/html
COPY ./public /var/www/html/public
RUN chown -R nginx:nginx /var/www/html \
    && chmod -R 770 /var/www/html
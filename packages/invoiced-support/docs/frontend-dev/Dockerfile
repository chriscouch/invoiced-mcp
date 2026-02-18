FROM nginx:mainline-alpine AS base
ARG API_URL=https:\\/\\/api.invoiced.com\\/
ARG API_HOST=api.invoiced.com

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
RUN sed -i "s/API_URL/$API_URL/g" /etc/nginx/conf.d/default.conf
RUN sed -i "s/API_HOST/$API_HOST/g" /etc/nginx/conf.d/default.conf

# Copy application into final image as separate stage
FROM base
WORKDIR /var/www/html
COPY ./build /var/www/html
RUN chown -R nginx:nginx /var/www/html \
    && chmod -R 770 /var/www/html
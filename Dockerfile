FROM composer:2.10 AS vendor

WORKDIR /app

COPY composer.json composer.lock* ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-plugins \
    --optimize-autoloader \
    --prefer-dist \
    --ignore-platform-reqs

FROM php:8.5-fpm-alpine

LABEL org.opencontainers.image.source=https://github.com/potatoenergy/telegram-ai-replier
LABEL org.opencontainers.image.description="A modular Telegram bot using AI for business replies"
LABEL org.opencontainers.image.licenses=MIT

RUN apk add --no-cache curl nginx

RUN echo "opcache.enable=1" > /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini \
    && echo "opcache.memory_consumption=128" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini \
    && echo "opcache.max_accelerated_files=10000" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini \
    && echo "opcache.revalidate_freq=2" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini

WORKDIR /app

COPY --from=vendor /app/vendor ./vendor
COPY . .

COPY docker/nginx.conf /etc/nginx/http.d/default.conf

RUN mkdir -p /run/nginx /var/lib/nginx /var/log/nginx \
    && chown -R www-data:www-data /app /var/lib/nginx /run/nginx /var/log/nginx

EXPOSE 80

CMD ["sh", "-c", "nginx -g 'daemon off;' & php-fpm"]
FROM php:8.2-cli-alpine

LABEL org.opencontainers.image.source=https://github.com/potatoenergy/telegram-ai-replier    
LABEL org.opencontainers.image.description="A modular Telegram bot using AI for business replies"
LABEL org.opencontainers.image.licenses=MIT

RUN apk add --no-cache \
    git \
    unzip \
    php82 \
    php82-curl \
    php82-json \
    php82-openssl \
    php82-mbstring \
    php82-tokenizer \
    php82-fileinfo \
    php82-zip \
    && ln -sf /usr/bin/php82 /usr/bin/php \
    && ln -sf /usr/bin/php-config82 /usr/bin/php-config \
    && ln -sf /usr/bin/phpize82 /usr/bin/phpize

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json ./

RUN composer install --no-dev --no-interaction --prefer-dist

COPY . .

RUN mkdir -p data && touch data/db.json data/rate_limit.json && chmod 666 data/db.json data/rate_limit.json

CMD ["php", "-S", "0.0.0.0:80", "bot.php"]
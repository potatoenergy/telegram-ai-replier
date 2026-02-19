FROM php:8.5.3-cli-alpine

LABEL org.opencontainers.image.source=https://github.com/potatoenergy/telegram-ai-replier    
LABEL org.opencontainers.image.description="A modular Telegram bot using AI for business replies"
LABEL org.opencontainers.image.licenses=MIT

RUN apk add --no-cache \
    git \
    unzip \
    php85 \
    php85-curl \
    php85-json \
    php85-openssl \
    php85-mbstring \
    php85-tokenizer \
    php85-fileinfo \
    php85-zip \
    && ln -sf /usr/bin/php85 /usr/bin/php \
    && ln -sf /usr/bin/php-config85 /usr/bin/php-config \
    && ln -sf /usr/bin/phpize85 /usr/bin/phpize

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json ./

RUN composer install --no-dev --no-interaction --prefer-dist

COPY . .

RUN mkdir -p data && touch data/db.json data/rate_limit.json && chmod 666 data/db.json data/rate_limit.json

CMD ["php", "-S", "0.0.0.0:80", "bot.php"]
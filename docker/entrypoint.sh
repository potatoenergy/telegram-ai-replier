#!/bin/sh
set -e

UPDATE_MODE=${UPDATE_MODE:-webhook}

mkdir -p /run/nginx /var/lib/nginx /var/log/nginx
chown -R www-data:www-data /app /var/lib/nginx /run/nginx /var/log/nginx 2>/dev/null || true

case "$UPDATE_MODE" in
    webhook)
        echo "Starting in WEBHOOK mode..."
        echo "Nginx + PHP-FPM will handle webhook requests"
        nginx -g 'daemon off;' &
        exec php-fpm
        ;;
    polling)
        echo "Starting in POLLING mode..."
        echo "Nginx will serve status page only"
        echo "Bot will poll Telegram in background"

        nginx
        php-fpm -D

        exec php /app/bot.php
        ;;
    *)
        echo "Invalid UPDATE_MODE: $UPDATE_MODE"
        echo "Must be 'webhook' or 'polling'"
        exit 1
        ;;
esac
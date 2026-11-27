#!/bin/sh
# Первичная настройка приложения при каждом старте контейнера.
# Всё идемпотентно: повторный запуск ничего не ломает.
set -e

cd /var/www/html

# APP_KEY генерируется один раз при первом старте и пишется в .env (volume).
if grep -q '^APP_KEY=$' .env 2>/dev/null; then
    echo "APP_KEY пуст — генерирую ключ..."
    php artisan key:generate --force --ansi
fi

# Подкаталоги storage: volume приходит с хоста и может быть пустым.
mkdir -p \
    storage/framework/sessions \
    storage/framework/views \
    storage/framework/cache \
    storage/logs \
    bootstrap/cache \
    database

# SQLite-файл локальной БД (сессии/кеш в файлах, но миграции нужны).
touch database/database.sqlite

# Симлинк public/storage (аватары, загрузки).
php artisan storage:link --force >/dev/null 2>&1 || true

# Идемпотентные миграции локальной БД.
php artisan migrate --force --ansi

# Права на записываемые директории (владелец пула php-fpm).
chown -R www-data:www-data storage bootstrap/cache database || true

exec supervisord -c /etc/supervisord.conf

# Деплой на Yandex Cloud

Приложение упаковано в один Docker-контейнер (nginx + php-fpm +
supervisord), поэтому на сервере нужен только Docker — PHP, Composer
и прочее ставить не надо.

## 1. Виртуальная машина

В консоли Yandex Cloud (Compute Cloud) создайте ВМ:

- **ОС**: Ubuntu 24.04 LTS
- **Ресурсы**: 2 vCPU / 4 ГБ RAM (для начала хватит 2 ГБ, но API
  платформы медленный — лучше с запасом)
- **Диск**: 20–30 ГБ
- **Доступ**: SSH-ключ (публичные ключи команды добавить при создании)
- **Публичный IP**: да
- **Security group**: входящие 22 (SSH), 80 (HTTP), 443 (HTTPS — позже)

Регион выбирайте ближе к стенду API платформы, чтобы сетевые задержки
не росли.

## 2. Docker на ВМ

Зайдите по SSH и выполните:

```bash
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker $USER
```

Перелогиньтесь (выйти/войти по SSH), проверьте: `docker version`.

Docker Compose плагин ставится тем же скриптом (`docker compose version`).

## 3. Код и .env

Репозиторий публичный, ключа не требуется:

```bash
git clone https://github.com/mmmPotat/X2609-492-unimice-frontend.git
cd X2609-492-unimice-frontend
git checkout main

cp .env.example .env
nano .env   # или vim
```

Заполните в `.env`:

```ini
APP_NAME=UniMICE
APP_ENV=production
APP_DEBUG=false
APP_URL=http://<публичный-IP-ВМ>

# Сессии и кеш — в файлы, локальная БД SQLite только под миграции.
DB_CONNECTION=sqlite
SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync

# Доступ к API платформы UniMICE.
UNIMICE_API_BASE_URL=https://<адрес-стенда>
UNIMICE_API_EMAIL=<учётка>
UNIMICE_API_PASSWORD=<пароль>
```

`APP_KEY` оставьте пустым — контейнер сгенерирует его при первом старте
и допишет в этот же `.env`.

> Важно: `.env` на сервере — это секреты. В репозиторий он не попадает
> (в `.gitignore`), в образ при сборке тоже не копируется (`.dockerignore`).

## 4. Запуск

```bash
docker compose up -d --build
docker compose logs -f app
```

Первый старт: сборка образа (~5–10 мин) + генерация ключа + миграции.
Дождитесь строк вида `INFO success: php-fpm entered RUNNING state`.

## 5. Админка

Сайт доступен по `http://<IP-ВМ>/admin`. Корень (`/`) редиректит в админку.

Создайте пользователя MoonShine (интерактивно спросит имя, email, пароль):

```bash
docker compose exec app php artisan moonshine:user
```

Пароль храните только у команды; на демо можно временный.

## 6. Обновление после пуша в main

```bash
git pull
docker compose up -d --build
docker compose exec app php artisan migrate --force   # если были миграции
```

## 7. Полезные команды

```bash
docker compose ps                 # статус
docker compose logs -f app      # логи (nginx + php-fpm)
docker compose exec app sh      # зайти в контейнер
docker compose restart app      # перезапуск
docker compose down             # остановить (VM можно выключать — грант экономится)
```

## 8. HTTPS (когда будет домен)

1. A-запись домена → IP ВМ.
2. Выпустите сертификат (папка `certbot-webroot` уже раздана в nginx):

   ```bash
   docker run --rm \
     -v $(pwd)/certbot-webroot:/webroot \
     -v $(pwd)/certbot-conf:/etc/letsencrypt \
     certbot/certbot certonly --webroot -w /webroot \
     -d unimice.<ваш-домен> --email <почта> --agree-tos
   ```

3. Добавьте в `docker/nginx/default.conf` сервер на 443 (ssl-сертификаты
   из `/etc/letsencrypt/live/<домен>/`), примонтируйте `./certbot-conf` в
   контейнер и пробросьте порт `443:443` в `docker-compose.yml`.

## Экономия гранта

- Неиспользуемую ВМ можно останавливать: `docker compose down` + остановка
  ВМ в консоли (за остановленную ВМ плата только за диск).
- Следите за расходом в разделе «Биллинг» консоли, поставьте бюджетный
  алерт на 50–80% гранта.
- Регулярные снимки диска (снапшоты) тоже тратят грант — не злоупотребляйте.

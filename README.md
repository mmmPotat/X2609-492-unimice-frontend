# X2609-492 UniMICE Frontend

Фронтенд-оболочка (клиентская админка, server-rendered) для работы с мероприятиями
Цифровой платформы корпоративных мероприятий UniMICE.
Заменяет Appsmith-интерфейс; вся логика на бэке, без ручного HTML/JS.

## Стек

- PHP 8.2+, Laravel 11+, [MoonShine 3](https://getmoonshine.app/)
- Бэкенд UniMICE (Symfony) вызывается только по REST API — своей БД у приложения нет

## Окружения API

| Контур | Base URL | Назначение |
|---|---|---|
| Master (разработка) | `https://mice.raketa.online/api` | связан со стейдж-приложением Appsmith |
| Prod | `https://mice-api.ibc.ru/api` | боевой контур |

Авторизация: `POST /api/auth/login`, тело `{"email": "...", "password": "..."}`,
ответ `{"token": "eyJ…"}`; далее заголовок `Authorization: Bearer <token>`.
Токен живёт ~24 часа.

Формат ответов API: конверт `{"status", "data", "message", "errors", "timestamp"}`,
коллекции в `data.items`.

## Структура

```
app/
├── MoonShine/            # ресурсы, страницы, кастомная авторизация
└── Services/UniMice/     # клиентский слой к API (HttpClient, DTO)
```

## Разработка

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan serve
```

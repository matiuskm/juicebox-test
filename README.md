# Juicebox API

A RESTful API built with **Laravel 13**, **MySQL** and **Laravel Sanctum**: posts, users, token auth, a queued welcome email, and cached Perth weather from a public API.

## Requirements

- PHP 8.3+ with `pdo_mysql`
- Composer
- MySQL 8+

## Setup

```bash
git clone <repo-url> juicebox-api && cd juicebox-api
composer install
cp .env.example .env
php artisan key:generate
```

Create a database, then set the credentials in `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=juicebox
DB_USERNAME=root
DB_PASSWORD=secret
```

```bash
mysql -u root -p -e "CREATE DATABASE juicebox"
php artisan migrate --seed     # seeds test@example.com / password plus sample posts
php artisan serve              # http://localhost:8000
```

### Queue worker

Queue, cache and sessions all use the `database` driver by default, so you don't need Redis. Run the worker in a separate terminal:

```bash
php artisan queue:work --tries=3
```

Mail goes to the log by default (`MAIL_MAILER=log`), so welcome emails show up in `storage/logs/laravel.log`. Use [Mailpit](https://mailpit.axllent.org) or real SMTP settings if you want actual email.

### Scheduler (weather refresh)

```bash
php artisan schedule:work      # local
# production cron:
# * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

### Manually dispatch the welcome email job

```bash
php artisan app:send-welcome-email 1                    # by user ID
php artisan app:send-welcome-email test@example.com     # by email
```

The command queues the job, so the queue worker has to be running to process it.

### Tests

```bash
php artisan test
```

Tests run on in-memory SQLite (see `phpunit.xml`) and all external HTTP calls are faked, so you don't need MySQL or network access.

## Weather API (Open-Meteo)

The app uses [Open-Meteo](https://open-meteo.com), a free weather API that needs **no API key and no sign-up**. Perth's coordinates (`-31.9523, 115.8613`) and timezone (`Australia/Perth`) are set in `App\Services\WeatherService`.

| Variable | Default | Description |
|---|---|---|
| `WEATHER_BASE_URL` | `https://api.open-meteo.com/v1` | API base URL (change it to point at a proxy or mock server) |
| `WEATHER_CACHE_TTL` | `900` | Cache lifetime in seconds (15 min) |

How it works:

1. `GET /api/weather` reads from the cache (`weather:perth`). The upstream API is only called on a cache miss.
2. The `RefreshWeather` job runs every 15 minutes through the scheduler and overwrites the cache, so user requests rarely wait on the upstream call.
3. Upstream calls use a 5s timeout and 2 attempts. If they still fail, the endpoint serves the **last known good** reading with `"stale": true`. If there has never been a successful reading, it returns `503`.

---

## API Documentation

Base URL: `http://localhost:8000/api`

Send `Accept: application/json` on every request. For protected endpoints, also send `Authorization: Bearer <token>`. You get the token from `/register` or `/login`.

### Summary

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| POST | `/register` | – | Register a user (throttled 10/min) |
| POST | `/login` | – | Log in and get a token (throttled 10/min) |
| POST | `/logout` | ✓ | Revoke the current token |
| GET | `/users` | ✓ | List users (paginated) |
| GET | `/users/{id}` | ✓ | Get a user |
| GET | `/posts` | – | List posts (paginated) |
| GET | `/posts/{id}` | – | Get a post |
| POST | `/posts` | ✓ | Create a post |
| PATCH | `/posts/{id}` | ✓ owner | Update a post (partial) |
| DELETE | `/posts/{id}` | ✓ owner | Delete a post |
| GET | `/weather` | – | Current weather in Perth |

### Errors

| Status | When | Body |
|---|---|---|
| 401 | Missing/invalid token, bad login | `{"message": "..."}` |
| 403 | Not the post owner | `{"message": "This action is unauthorized."}` |
| 404 | Unknown resource | `{"message": "Resource not found."}` |
| 422 | Validation failed | `{"message": "...", "errors": {"field": ["..."]}}` |
| 429 | Rate limit hit | `{"message": "Too Many Attempts."}` |
| 503 | Weather API down, nothing cached | `{"message": "Weather service is currently unavailable..."}` |

### Auth

**POST /register**

```json
{ "name": "Jane", "email": "jane@example.com", "password": "secret-password", "password_confirmation": "secret-password" }
```

`201 Created`:

```json
{ "data": { "id": 1, "name": "Jane", "email": "jane@example.com", "created_at": "..." }, "token": "1|abc..." }
```

This also queues the welcome email job.

**POST /login**: `{ "email": "...", "password": "..." }` returns `200` with the same shape as register. Bad credentials return `401`.

**POST /logout**: `204 No Content`. Only the token used for this request is revoked.

### Users

**GET /users?page=1&per_page=15** returns a paginated list:

```json
{
  "data": [{ "id": 1, "name": "Jane", "posts_count": 3, "created_at": "..." }],
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." },
  "meta": { "current_page": 1, "per_page": 15, "total": 42, "last_page": 3 }
}
```

**GET /users/{id}** returns `{ "data": { "id", "name", "posts_count", "created_at" } }`. The `email` field is only included when you request your own profile.

### Posts

**GET /posts?page=1&per_page=15** returns a paginated list, newest first. `per_page` is capped at 100.

```json
{ "data": [{ "id": 1, "title": "...", "content": "...", "author": { "id": 1, "name": "Jane" }, "created_at": "...", "updated_at": "..." }], "links": {}, "meta": {} }
```

**GET /posts/{id}** returns `{ "data": { ...post } }`.

**POST /posts** takes `{ "title": "required|max:255", "content": "required|max:10000" }` and returns `201 Created`.

**PATCH /posts/{id}** takes any subset of `title` and `content` and returns `200`. Only the author can do this; anyone else gets `403`.

**DELETE /posts/{id}** returns `204 No Content`. Only the author can do this; anyone else gets `403`.

### Weather

**GET /weather**

```json
{
  "data": {
    "location": "Perth, Australia",
    "observed_at": "2026-11-02T14:15",
    "temperature": 19.4,
    "feels_like": 18.8,
    "humidity": 60,
    "wind_speed": 15.8,
    "weather_code": 2,
    "today": { "min": 15.4, "max": 19.4, "precipitation_probability": 61 },
    "units": { "temperature": "°C", "wind_speed": "km/h", "humidity": "%" },
    "fetched_at": "2026-11-02T06:27:15+00:00"
  }
}
```

`weather_code` uses the [WMO weather interpretation codes](https://open-meteo.com/en/docs#weathervariables). If the response includes `"stale": true`, the upstream API failed and you're getting the last successful reading.

### Quick try with curl

```bash
TOKEN=$(curl -s -X POST localhost:8000/api/login -H 'Accept: application/json' \
  -d email=test@example.com -d password=password | php -r 'echo json_decode(stream_get_contents(STDIN))->token;')

curl -s localhost:8000/api/posts -H 'Accept: application/json'
curl -s -X POST localhost:8000/api/posts -H 'Accept: application/json' -H "Authorization: Bearer $TOKEN" \
  -d title='Hello' -d content='World'
curl -s localhost:8000/api/weather -H 'Accept: application/json'
```

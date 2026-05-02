# Multi-Provider Currency Exchange API

This service answers a simple question: *how much is an amount worth in other currencies?* You choose an amount, a starting currency, and one or more targets—optionally for a specific day. It gathers quotes using **Frankfurter** and **ExchangeRatesAPI.io** in parallel, returns converted amounts per provider together with differences between quotes where that matters, and highlights the best quote per currency in one **`POST /convert`** response.

Symfony application source lives in **`app/`**; Docker tooling sits at the repository root.

Tokens can be **generated locally** with `app:jwt:generate` (demo/evaluation only); production systems would normally obtain JWTs from an IdP.

---

## Tech stack

- **PHP** — 8.3 (Docker image); Composer requires `>=8.2`
- **Symfony** — 7.4 (`symfony/framework-bundle`, micro‑kernel style)
- **Web** — PHP‑FPM + **Nginx** (Alpine), **Docker Compose**
- **HTTP client** — `symfony/http-client` (async-friendly provider calls)
- **Security** — `symfony/security-bundle` + **firebase/php-jwt** (JWT decode)
- **Validation** — `symfony/validator`
- **Tests** — PHPUnit 12, `symfony/browser-kit`, `symfony/phpunit-bridge`

---

## Symfony bundles

| Bundle | Purpose |
|--------|---------|
| **FrameworkBundle** | Kernel, routing, DI, HTTP foundation |
| **SecurityBundle** | Firewall + JWT authenticator on `/convert` |

Other Symfony packages in use (not separate bundles): Console, Dotenv, Runtime, Yaml, HttpClient, Validator, Flex (recipe tooling).

---

## Local prerequisites

- **Docker** ≥ 24.x and **Docker Compose** v2 (`docker compose`)
- **Git** (clone/checkout)
- Ports **8080** free (Nginx → host)

Optional without Docker: PHP matching `composer.json`, Composer 2, and a configured web server pointing to `app/public/` — the intended path is Docker.

---

## Quick start (Docker)

From the repository root:

```bash
cp app/.env app/.env.local
# Set APP_SECRET, JWT_SECRET, EXCHANGERATES_API_KEY in app/.env.local

docker compose build
docker compose up -d
docker compose exec php composer install
```

API base URL: **`http://localhost:8080`**

---

## Environment variables

Configure secrets and overrides in **`app/.env.local`** (copy from `app/.env`). Real processes may also inject these via the environment.

### Rate provider documentation

Upstream APIs used by this application:

| Provider | Documentation |
|----------|----------------|
| **Frankfurter** | [Frankfurter v1 API](https://www.frankfurter.app/v1/) |
| **Exchange Rates API** (ExchangeRatesAPI.io) | [APILayer — Exchange Rates API](https://docs.apilayer.com/exchangeratesapi/docs/getting-started) |

| Variable | Required | Description |
|----------|----------|-------------|
| `APP_SECRET` | Yes | Symfony secret string (random, unique per env). |
| `JWT_SECRET` | Yes | Shared secret for **HS256** JWT verification and for **`bin/console app:jwt:generate`**. Use **≥ 32 characters** (firebase/php-jwt key-length rule). |
| `EXCHANGERATES_API_KEY` | Yes* | Access key for Exchange Rates API (**`access_key`** query parameter). Obtain it from the APILayer account/dashboard linked in the table above. *Without it, that provider fails (Frankfurter may still work). |
| `FRANKFURTER_BASE_URL` | No | Frankfurter API base URL (default in `app/.env`). |
| `EXCHANGERATES_BASE_URL` | No | Exchange Rates API base URL (default `https://api.exchangeratesapi.io/v1` in `app/.env`). |
| `HTTP_CLIENT_TIMEOUT` | No | Reserved default for outbound HTTP configuration. |
| `APP_ENV`, `DEFAULT_URI` | No | Standard Symfony defaults from `app/.env`. |

---

## API example (`POST /convert`)

### 1. Generate a JWT (demo / evaluation)

The console command builds and signs a token with **`JWT_SECRET`** — the same secret the API uses to validate requests.

**Docker** (from repo root):

```bash
docker compose exec php php bin/console app:jwt:generate
docker compose exec php php bin/console app:jwt:generate --ttl=7200
```

**Local** (from `app/` after `composer install`):

```bash
cd app
php bin/console app:jwt:generate
php bin/console app:jwt:generate --ttl=7200
```

Options:

| Option | Default | Description |
|--------|---------|-------------|
| `--ttl` | `3600` | Lifetime in seconds |
| `--sub` | `dev-client` | JWT `sub` claim |

The command prints the raw JWT string (no surrounding quotes). Pipe or copy it into `TOKEN` for `curl`.

### 2. Request

```bash
TOKEN="<paste-jwt-here>"

curl -s -X POST http://localhost:8080/convert \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "amount": 100,
    "from": "USD",
    "symbols": ["EUR", "PEN"],
    "date": "2026-05-01"
  }'
```

### 3. Example JSON body (request)

```json
{
  "amount": 100,
  "from": "USD",
  "symbols": ["EUR", "PEN"],
  "date": "2026-05-01"
}
```

`date` is optional (defaults to today, `YYYY-MM-DD` when sent).

### 4. Example JSON body (response, illustrative)

Exact numbers depend on live provider data.

```json
{
  "base": "USD",
  "date": "2026-05-01",
  "results": {
    "EUR": {
      "providers": {
        "frankfurter": { "rate": 0.92, "converted": 92.0 },
        "exchangeratesapi": { "rate": 0.921, "converted": 92.1 }
      },
      "difference": { "absolute": 0.001, "percentage": 0.11 },
      "best_provider": "exchangeratesapi"
    },
    "PEN": {
      "providers": {
        "frankfurter": { "rate": 3.65, "converted": 365.0 },
        "exchangeratesapi": { "rate": 3.651, "converted": 365.1 }
      },
      "difference": { "absolute": 0.001, "percentage": 0.03 },
      "best_provider": "exchangeratesapi"
    }
  },
  "meta": {
    "providers_used": ["frankfurter", "exchangeratesapi"],
    "timestamp": "2026-05-01T12:00:00+00:00"
  }
}
```

---

## HTTP responses

### Success (JWT accepted)

All success responses use the same JSON shape: `base`, `date`, `results`, `meta`.

| Code | Meaning |
|------|---------|
| **200** | At least one provider succeeded and **`meta.errors`** is absent. |
| **206** | At least one provider succeeded; **`meta.errors`** lists failures per provider key. |
| **502** | No provider succeeded (**`meta.providers_used`** empty); **`results`** typically empty. |

### Errors

JSON responses always set **`Content-Type: application/json`**.

| Code | `error` | Body |
|------|---------|------|
| **401** | `unauthorized` | `{ "error": "unauthorized", "message": "<reason>" }` — missing/invalid Bearer token, expired JWT, empty `JWT_SECRET`, or entry point. |
| **400** | `invalid_json` | `{ "error": "invalid_json", "message": "<detail>" }` — malformed JSON or root value not an object. |
| **422** | `validation_failed` | `{ "error": "validation_failed", "violations": [ { "property": "<path>", "message": "<text>" } ] }` — Symfony Validator or invalid `date` format. |

---

## Tests

```bash
docker compose exec php ./vendor/bin/phpunit
```

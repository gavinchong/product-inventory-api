# Product Inventory Management API

## Overview

A Laravel 11 REST API for managing products, categories, and supplier relationships. Built for Stage 1 Question 2, with token authentication, validated input, consistent JSON resources, and isolated feature tests.

## Features

- Authenticated product CRUD, including PUT and PATCH updates.
- Category, inclusive price range, and stock range filters that work together.
- Pagination with eager-loaded categories and suppliers.
- Sanctum bearer-token login and logout.
- Product soft deletes and globally unique SKUs.
- Transactional product writes and supplier syncing.
- Factories, modest demo data, and PHPUnit feature coverage.

## Tech Stack

- Laravel **11.56.1**, PHP, Eloquent, and Laravel Sanctum 4.
- SQLite for host development, MariaDB 11.4 for Docker, and in-memory SQLite for tests.
- PHPUnit 11 and Laravel Pint.

## Requirements

- **PHP 8.4+** for the supplied dependency lock file (development verified on PHP 8.4.14).
- Composer 2.
- PHP extensions required by Laravel, including PDO, `pdo_sqlite`, SQLite3, Mbstring, OpenSSL, XML, DOM, Ctype, Fileinfo, Tokenizer, and cURL.
- Git to clone the submission.

The host setup below needs no Node build, Docker, Redis, or queue worker. Alternatively, use Docker Setup with only Docker Engine/Desktop and Docker Compose installed.

Laravel 11.x is retained as required by the assessment. Composer currently reports three advisory records affecting the framework. Run `composer audit` for details; no persistent security-check bypass is configured.

## Installation

After cloning this repository, open its root directory:

```bash
composer install
cp .env.example .env
php artisan key:generate
```

The checked-in `composer.lock` pins the reviewed dependencies; use `install`, not `update`, to reproduce the submission.

## Database Setup

The example environment already selects SQLite. With `DB_DATABASE` unset, Laravel uses `database/database.sqlite`:

```bash
touch database/database.sqlite
php artisan migrate --seed
php artisan serve
```

The API is available at `http://127.0.0.1:8000/api`. Only the API endpoints require testing; the scaffold welcome page is unchanged.

For a clean rebuild of **this application's disposable local database**:

```bash
php artisan migrate:fresh --seed
```

This deletes the selected database's existing tables. The demo seeder targets an empty database; do not repeatedly run it against existing seed data.

## Docker Setup

From a fresh clone, run these commands in the repository root:

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
```

The image includes PHP 8.4-FPM, Composer, and the locked application dependencies, including development dependencies for tests. Composer installation happens during the build; no host PHP or Composer installation is required. Nginx serves the API at **http://localhost:8000/api**. Use the demo credentials below to log in.

Compose creates three services: `app`, `nginx`, and `db` (MariaDB 11.4). The app connects to `db:3306`; the database is not exposed on the host. Database data persists in the project-scoped `db-data` volume.

The `DOCKER_*` values in `.env.example` provide local development defaults. Compose supplies MySQL connection settings inside the app container, so leave `DB_CONNECTION=sqlite` for host development. The `.env` file is mounted into the app; application code and dependencies are copied into the image. Rebuild with `docker compose up -d --build` after code changes. The image excludes `.env`, local databases, and host dependencies.

If port 8000 is occupied, set `DOCKER_APP_PORT=8002` in `.env` before starting; the API will then be at `http://localhost:8002/api`. Database credentials are development-only defaults; changing them after initialization does not update users in an existing database volume.

Run the complete test suite and formatter check inside Docker:

```bash
docker compose exec app php artisan test
docker compose exec app ./vendor/bin/pint --test
```

Tests continue to use isolated **in-memory SQLite**, not the MariaDB database. Run migrations and seeders on the first startup only; the demo seeder expects an empty database.

Stop and remove this project's containers while preserving database data:

```bash
docker compose down
```

For an intentional complete database reset, `docker compose down -v` also deletes this project's database volume. Start again and rerun migrations/seeding afterward.

## Seed Data / Demo Credentials

| Email | Password |
| --- | --- |
| `demo@example.com` | `password` |

Seeding creates one demo user, three categories (Electronics, Office, Accessories), three suppliers, and 18 products, each with two suppliers. IDs 1–3 are available for categories and suppliers immediately after a fresh seed. Product prices and stock levels vary, including out-of-stock examples.

Demo credentials are for local assessment use only. There is no registration endpoint.

## Authentication

`POST /api/login` accepts email and password. A successful response is HTTP 200:

```json
{
  "data": {
    "user": { "id": 1, "name": "Demo User", "email": "demo@example.com" },
    "token": "<issued-token>",
    "token_type": "Bearer"
  }
}
```

Send the returned token on every protected request:

```http
Authorization: Bearer <token>
Accept: application/json
Content-Type: application/json
```

`POST /api/logout` returns HTTP 204 and deletes only the current token. Other tokens belonging to the same user remain valid. Tokens use Sanctum's default non-expiring lifetime until revoked; stored tokens are hashed. Missing or invalid tokens return HTTP 401. Invalid login credentials return HTTP 422 with a generic validation message.

## Rate Limiting

The API includes rate limiting using Laravel's built-in rate limiter:

- Login: 5 requests per minute per email/IP combination.
- Authenticated API: 60 requests per minute per authenticated user.
- Requests exceeding the limit return HTTP 429 Too Many Requests.

## API Endpoints

| Method | Endpoint | Authentication | Success |
| --- | --- | --- | --- |
| POST | `/api/login` | Public | 200, user and token |
| POST | `/api/logout` | Bearer | 204 |
| GET | `/api/products` | Bearer | 200, paginated collection |
| POST | `/api/products` | Bearer | 201, created product |
| GET | `/api/products/{product}` | Bearer | 200, product |
| PUT / PATCH | `/api/products/{product}` | Bearer | 200, updated product |
| DELETE | `/api/products/{product}` | Bearer | 204, no body |

Category and Supplier models are related resources; standalone CRUD endpoints for them are outside the assessment scope.

Product responses use a `data` envelope with `id`, `name`, `sku`, `description`, `price`, `stock`, `stock_status`, nested `category`, `suppliers`, `created_at`, and `updated_at`. Collections also include Laravel's `links` and `meta` pagination fields. Prices are two-decimal **strings**, for example `"7999.00"`; timestamps use ISO 8601.

Missing and soft-deleted products return HTTP 404. Validation errors return HTTP 422 with `message` and `errors`. API errors are rendered as JSON even when the client omits `Accept`.

### Product validation and update semantics

- Create requires `name`, `sku`, `category_id`, `price`, and `stock`.
- Name is at most 255 characters; SKU is at most 100 and remains unique across active and soft-deleted products.
- Description is nullable, up to 10,000 characters.
- Category must exist.
- Price is nonnegative, has at most two decimal places, and cannot exceed `9999999999.99` (`DECIMAL(12,2)`).
- Stock is an integer from 0 through 4,294,967,295.
- `supplier_ids` is an optional array of up to 100 distinct, existing supplier IDs. Null is invalid; use `[]` for no suppliers.
- Both PUT and PATCH accept partial updates. Omitted fields retain their values. Included mandatory fields cannot be null.
- Omitting `supplier_ids` on update preserves relationships; providing IDs replaces them; providing `[]` clears them.
- Unknown fields are never mass-assigned.

## Filtering and Pagination

All filters can be combined with AND semantics. Bounds are inclusive.

| Parameter | Meaning |
| --- | --- |
| `category_id` | Existing category ID |
| `min_price` | Minimum price, nonnegative |
| `max_price` | Maximum price, at least `min_price` if both supplied |
| `min_stock` | Minimum stock, nonnegative integer |
| `max_stock` | Maximum stock, at least `min_stock` if both supplied |
| `page` | Positive page number, default 1 |
| `per_page` | Page size, default 15, minimum 1, maximum 100 |

Invalid values return HTTP 422 rather than being silently clamped. Results are ordered by ID for deterministic pagination. Pagination links retain validated filters. An empty result set returns HTTP 200 with an empty `data` array. `max_stock=0` finds out-of-stock products.

```text
GET /api/products?category_id=1&min_price=100&max_price=5000&min_stock=1&per_page=15
```

## Example Requests

Log in:

```bash
curl -sS -X POST http://127.0.0.1:8000/api/login \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"email":"demo@example.com","password":"password"}'
```

Copy `data.token` into a shell variable:

```bash
TOKEN='<token returned by login>'
```

Create a product:

```bash
curl -sS -X POST http://127.0.0.1:8000/api/products \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"name":"MacBook Pro","sku":"MBP-001","description":"14-inch MacBook Pro","category_id":1,"price":7999.00,"stock":10,"supplier_ids":[1,2]}'
```

List filtered products:

```bash
curl -sS 'http://127.0.0.1:8000/api/products?category_id=1&min_price=100&max_price=5000&min_stock=1&per_page=15' \
  -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json'
```

Use the product ID returned by creation:

```bash
PRODUCT_ID='<created product ID>'

curl -sS "http://127.0.0.1:8000/api/products/$PRODUCT_ID" \
  -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json'

curl -sS -X PATCH "http://127.0.0.1:8000/api/products/$PRODUCT_ID" \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"stock":0,"supplier_ids":[2,3]}'

curl -i -X DELETE "http://127.0.0.1:8000/api/products/$PRODUCT_ID" \
  -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json'

curl -i -X POST http://127.0.0.1:8000/api/logout \
  -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json'
```

## Running Tests

```bash
php artisan config:clear
php artisan test
vendor/bin/pint --test
php artisan route:list --path=api
```

`phpunit.xml` forces an in-memory SQLite database. Feature tests use `RefreshDatabase` and factories, never the demo database or seeded IDs. Coverage includes authentication on all CRUD routes, real bearer-token login/revocation, pagination, create/show/update/delete, validation boundaries, relationship replacement/preservation/clearing, soft-delete visibility, independent and combined filters, and invalid query parameters.

## Design Decisions

- Standard controllers, Eloquent models, Form Requests, and API Resources; no repository, service, or DTO layers.
- `Product::scopeFilter()` owns category, price, and stock query constraints. Controllers eager-load related resources to avoid per-product relationship queries.
- Laravel's Attribute API implements `stock_status`: positive stock is `in_stock`, zero is `out_of_stock`.
- Products belong to a category and to many suppliers. Categories have many products; suppliers belong to many products. The pivot includes timestamps and a composite primary key to prevent duplicates.
- Category deletion is restricted while products reference it. Hard deletion of a product or supplier cascades its pivot rows. Soft deletion preserves relationships and reserves the SKU; default queries and route binding hide deleted products.
- Price, stock, and soft-delete columns are indexed. Foreign keys enforce referential integrity. API validation enforces nonnegative values and storage bounds, including on SQLite, which does not enforce unsigned integers or decimal scale like MySQL.
- Product writes and supplier syncing share a database transaction.
- All authenticated users can manage the shared inventory; per-user ownership and roles were not requested.
- Docker is the only implemented bonus: PHP-FPM, Nginx, and MariaDB. Swagger/OpenAPI, caching, and rate limiting remain deferred. The standalone Compose setup does not require Laravel Sail.
- Commit the lock file for reproducibility. `.env`, local SQLite data, dependencies, logs, and local Composer tooling are excluded from Git. No credentials or access tokens need to be committed.

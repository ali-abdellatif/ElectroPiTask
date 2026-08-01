# Task Management API

RESTful API for a simple Task Management System, built with Laravel and Sanctum.

Users register, manage their own projects, and manage tasks within each project with
filtering, search, and a summary dashboard. Every record is scoped to its owner: one user
can never read or change another user's projects or tasks.

## Tech Stack

| | |
|---|---|
| Framework | Laravel 12 (latest stable) |
| Language | PHP 8.2+ |
| Auth | Laravel Sanctum (API tokens) |
| Database | MySQL 8 (SQLite in-memory for tests) |
| Testing | PHPUnit — 88 tests |
| Style | Laravel Pint |

## Features

- Token authentication: register, login, logout, current user
- Projects CRUD with `active` / `completed` / `archived` status
- Tasks CRUD nested under projects, plus a cross-project listing
- Filter by status and priority, search by title — all combinable
- Dashboard with aggregated figures, including a correct overdue count
- Soft deletes on projects and tasks
- Form Request validation and API Resource responses throughout
- Policies for ownership, consistent JSON errors, and correct HTTP status codes
- Factories and a seeder with realistic sample data

## Requirements

- PHP >= 8.2 with the `pdo_mysql`, `mbstring`, `openssl` and `sqlite3` extensions
- Composer 2
- MySQL 8 (or MariaDB 10.6+)

## Installation

```bash
# 1. Clone and enter the project
git clone https://github.com/ali-abdellatif/ElectroPiTask.git
cd ElectroPiTask

# 2. Install PHP dependencies
composer install

# 3. Create the environment file and generate the application key
cp .env.example .env
php artisan key:generate

# 4. Create an empty database, then point .env at it (see below)
#    MySQL: CREATE DATABASE electropi_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# 5. Run the migrations and load the sample data
php artisan migrate --seed

# 6. Serve
php artisan serve
```

The API is then available at `http://127.0.0.1:8000/api/v1`.

> On Windows the `cp` above is `copy .env.example .env`.

## Environment Setup

The only values you normally need to touch are the database credentials:

```dotenv
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=electropi_app
DB_USERNAME=root
DB_PASSWORD=
```

Set `APP_DEBUG=false` outside local development. With debug off, an unexpected failure
returns `{"message": "Server error."}` instead of a stack trace.

Tests need no configuration — `phpunit.xml` points them at an in-memory SQLite database, so
running them never touches your MySQL data.

## Sample Data

`php artisan migrate --seed` creates five users. One has fixed credentials for reviewing:

```
email:    demo@example.com
password: password
```

That account owns one project per status, with tasks covering every status and priority,
tasks with and without due dates, overdue tasks, and tasks that are past due but already
finished — the case an overdue count is most likely to get wrong.

## Running Tests

```bash
php artisan test                       # whole suite
php artisan test --filter=DashboardTest
```

```bash
./vendor/bin/pint --test               # check code style
./vendor/bin/pint                      # fix it
```

## Database Schema

Three domain tables — `users`, `projects`, `tasks` — with
`User hasMany Project hasMany Task`. Full diagram, columns, indexes and the reasoning
behind them: [docs/ERD.md](docs/ERD.md).

Tasks carry no `user_id`; ownership is derived through the parent project, so an owner
mismatch is impossible.

---

# API Documentation

Base URL: `http://127.0.0.1:8000/api/v1`

## Conventions

**Authentication.** Every endpoint except `register` and `login` requires a bearer token:

```http
Authorization: Bearer 2|PeGG2059MU1V7T1mjot9xU3RBFtx55PLlhWcgJ0Qf77fe50c
Accept: application/json
```

**Responses.** Single records come back under a `data` key; collections add `links` and
`meta`. Auth endpoints also include a `message`.

**Errors.** Always JSON, always the same shape:

```json
{ "message": "The name field is required.", "errors": { "name": ["The name field is required."] } }
```

`errors` is present only for validation failures (422).

**Status codes.**

| Code | Meaning |
|---|---|
| 200 | OK |
| 201 | Created |
| 204 | Deleted, no body |
| 401 | Missing, invalid, or revoked token |
| 403 | Authenticated, but the record belongs to someone else |
| 404 | No such record, or no such route |
| 405 | Method not allowed |
| 422 | Validation failed |
| 429 | Rate limit exceeded (login only: 6 attempts per minute) |

**Pagination.** Every listing accepts `?page=` and `?per_page=` (default 15, maximum 100).
A non-numeric `per_page` falls back to the default.

**Enums.**

| Field | Values |
|---|---|
| project `status` | `active`, `completed`, `archived` |
| task `priority` | `low`, `medium`, `high` |
| task `status` | `todo`, `in_progress`, `done` |

---

## Authentication

### `POST /register`

| Field | Rules |
|---|---|
| `name` | required, string, max 255 |
| `email` | required, valid email, unique, max 255 |
| `password` | required, min 8, must match `password_confirmation` |

```json
// request
{ "name": "Jane Doe", "email": "jane@example.com",
  "password": "secret-password", "password_confirmation": "secret-password" }
```

```json
// 201 Created
{
  "message": "Registration successful.",
  "data": {
    "user": { "id": 6, "name": "Jane Doe", "email": "jane@example.com",
              "created_at": "2026-08-01T16:11:00.000000Z" },
    "token": "1|tQ4kI42d6nBrbNcid75IX5bne8RJiQFBjJpriziH423ff222"
  }
}
```

### `POST /login`

| Field | Rules |
|---|---|
| `email` | required, valid email |
| `password` | required |

```json
// 200 OK
{
  "message": "Login successful.",
  "data": {
    "user": { "id": 1, "name": "Demo User", "email": "demo@example.com",
              "created_at": "2026-08-01T16:10:53.000000Z" },
    "token": "2|PeGG2059MU1V7T1mjot9xU3RBFtx55PLlhWcgJ0Qf77fe50c"
  }
}
```

Bad credentials return **401** with a single generic message for both an unknown email and a
wrong password, so the endpoint cannot be used to discover which addresses are registered.
The route is rate limited to 6 attempts per minute.

### `POST /logout`

Revokes only the token used for the request, so the user's other devices stay signed in.

```json
// 200 OK
{ "message": "Logged out successfully." }
```

### `GET /me`

```json
// 200 OK
{ "data": { "id": 1, "name": "Demo User", "email": "demo@example.com",
            "created_at": "2026-08-01T16:10:53.000000Z" } }
```

---

## Projects

### `GET /projects`

Lists the authenticated user's projects, newest first. Query: `page`, `per_page`.

```json
// 200 OK  (meta.links omitted for brevity)
{
  "data": [
    {
      "id": 1,
      "name": "Persevering 4thgeneration middleware",
      "description": "Sed tempora aperiam ut eum...",
      "status": "active",
      "tasks_count": 14,
      "created_at": "2026-08-01T16:10:53.000000Z",
      "updated_at": "2026-08-01T16:10:53.000000Z"
    }
  ],
  "links": { "first": ".../projects?page=1", "last": ".../projects?page=2",
             "prev": null, "next": ".../projects?page=2" },
  "meta": { "current_page": 1, "from": 1, "last_page": 2, "path": ".../projects",
            "per_page": 2, "to": 2, "total": 3 }
}
```

### `POST /projects`

| Field | Rules |
|---|---|
| `name` | required, string, max 255 |
| `description` | optional, string, max 5000, nullable |
| `status` | optional, one of the project statuses (defaults to `active`) |

```json
// request
{ "name": "Website Redesign", "description": "Rebuild the marketing site.", "status": "active" }
```

```json
// 201 Created
{ "data": { "id": 16, "name": "Website Redesign", "description": "Rebuild the marketing site.",
            "status": "active", "tasks_count": 0,
            "created_at": "2026-08-01T16:11:01.000000Z",
            "updated_at": "2026-08-01T16:11:01.000000Z" } }
```

### `GET /projects/{id}`

**200** with the same object as above. **403** if it belongs to another user, **404** if it
does not exist.

### `PUT|PATCH /projects/{id}`

Every field is optional, so partial updates work — but a field that *is* sent must be valid;
`name` and `status` cannot be blanked.

```json
// request
{ "status": "completed" }
```

Returns **200** with the updated project.

### `DELETE /projects/{id}`

Soft deletes the project. Returns **204** with no body. Its tasks are left intact in the
database, so the project remains restorable; both disappear from the API.

---

## Tasks

### `GET /projects/{id}/tasks`

Tasks of one project, newest first.

| Query | Description |
|---|---|
| `status` | `todo`, `in_progress`, `done` |
| `priority` | `low`, `medium`, `high` |
| `search` | matches part of the title, case-insensitive |
| `page`, `per_page` | pagination |

All filters combine: `?status=todo&priority=high&search=api` narrows on all three.
An unknown value returns **422** rather than an empty list. An empty value
(`?status=`) is treated as no filter.

```json
// 200 OK  (links and meta omitted)
{
  "data": [
    {
      "id": 11,
      "project_id": 1,
      "title": "Et fugiat totam consequatur.",
      "description": null,
      "priority": "high",
      "status": "in_progress",
      "due_date": "2026-07-30",
      "is_overdue": true,
      "created_at": "2026-08-01T16:10:54.000000Z",
      "updated_at": "2026-08-01T16:10:54.000000Z"
    }
  ]
}
```

`is_overdue` is true when the due date has passed **and** the task is not `done`. A task due
today is not yet overdue.

### `POST /projects/{id}/tasks`

| Field | Rules |
|---|---|
| `title` | required, string, max 255 |
| `description` | optional, string, max 5000, nullable |
| `priority` | optional, one of the priorities (defaults to `medium`) |
| `status` | optional, one of the task statuses (defaults to `todo`) |
| `due_date` | optional date, nullable — past dates are allowed |

```json
// request
{ "title": "Write the API docs", "description": "Cover every endpoint.",
  "priority": "high", "status": "todo", "due_date": "2026-12-31" }
```

```json
// 201 Created
{ "data": { "id": 111, "project_id": 1, "title": "Write the API docs",
            "description": "Cover every endpoint.", "priority": "high", "status": "todo",
            "due_date": "2026-12-31", "is_overdue": false,
            "created_at": "2026-08-01T16:11:02.000000Z",
            "updated_at": "2026-08-01T16:11:02.000000Z" } }
```

### `GET /tasks`

Every task the user owns, across all their projects — same filters as above, plus the owning
project embedded in each row.

```json
// 200 OK  (links and meta omitted)
{
  "data": [
    {
      "id": 111,
      "project_id": 1,
      "title": "Write the API docs",
      "description": "Cover every endpoint.",
      "priority": "high",
      "status": "todo",
      "due_date": "2026-12-31",
      "is_overdue": false,
      "project": { "id": 1, "name": "Persevering 4thgeneration middleware",
                   "description": "Sed tempora aperiam ut eum...", "status": "active",
                   "created_at": "2026-08-01T16:10:53.000000Z",
                   "updated_at": "2026-08-01T16:10:53.000000Z" },
      "created_at": "2026-08-01T16:11:02.000000Z",
      "updated_at": "2026-08-01T16:11:02.000000Z"
    }
  ]
}
```

Tasks whose project has been deleted do not appear here.

### `GET /tasks/{id}`

**200** with the task. **403** if it sits in another user's project, **404** if it does not
exist.

### `PUT|PATCH /tasks/{id}`

Partial updates, same rules as creation. `due_date` may be sent as `null` to clear the
deadline; the other fields cannot be blanked. Sending `project_id` is ignored — a task
cannot be moved between projects this way.

### `DELETE /tasks/{id}`

Soft deletes the task. Returns **204** with no body.

---

## Dashboard

### `GET /dashboard`

```json
// 200 OK
{
  "data": {
    "total_projects": 3,
    "active_projects": 1,
    "total_tasks": 22,
    "completed_tasks": 10,
    "pending_tasks": 12,
    "overdue_tasks": 3
  }
}
```

| Figure | Definition |
|---|---|
| `total_projects` | all of the user's projects |
| `active_projects` | projects with status `active` |
| `total_tasks` | tasks across all their projects |
| `completed_tasks` | tasks with status `done` |
| `pending_tasks` | everything not `done` |
| `overdue_tasks` | `due_date` before today **and** status not `done` |

`overdue_tasks` uses the same definition as the `is_overdue` flag in the task listings, so
the two can never disagree.

---

## Error Examples

```json
// 422 — validation
{ "message": "The name field is required. (and 1 more error)",
  "errors": { "name": ["The name field is required."],
              "status": ["The selected status is invalid."] } }
```

```json
// 422 — unknown filter value
{ "message": "The selected status is invalid.",
  "errors": { "status": ["The selected status is invalid."] } }
```

```json
// 401                                  // 403
{ "message": "Unauthenticated." }       { "message": "This action is unauthorized." }
```

```json
// 404                                  // 500 (with APP_DEBUG=false)
{ "message": "Resource not found." }    { "message": "Server error." }
```

The 404 message is deliberately generic — it never names the model class behind the route.

## Postman Collection

Import [docs/postman_collection.json](docs/postman_collection.json). It covers every
endpoint and stores the token from a login into a collection variable, so the authenticated
requests work immediately afterwards.

## License

Released under the [MIT License](https://opensource.org/licenses/MIT).

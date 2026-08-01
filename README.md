# Task Management API

RESTful API for a simple Task Management System, built with Laravel and Sanctum.

Users can register, manage their own projects, and manage tasks within each project
with filtering, search, and a summary dashboard.

## Tech Stack

| | |
|---|---|
| Framework | Laravel 12 (latest stable) |
| Language | PHP 8.2+ |
| Auth | Laravel Sanctum (API tokens) |
| Database | MySQL 8 (SQLite supported for testing) |
| Testing | PHPUnit |
| Style | Laravel Pint |

## Features

- Token-based authentication (register / login / logout)
- Projects CRUD, scoped per user, with `active` / `completed` / `archived` status
- Tasks CRUD nested under projects, with priority, status, and due date
- Filtering by status and priority, plus search by title
- Dashboard endpoint with aggregated statistics
- Soft deletes on projects and tasks
- Form Request validation and API Resource responses
- Consistent JSON error responses with proper HTTP status codes
- Factories and seeders with realistic sample data

## Installation

> TODO — completed in Phase 7.

## Environment Setup

> TODO — completed in Phase 7.

## Running Tests

> TODO — completed in Phase 7.

## Database Schema

> TODO — ERD added in Phase 1 under `docs/`.

## API Documentation

> TODO — full endpoint reference (parameters, example requests and responses)
> completed in Phase 7. The table below is the planned surface.

| Method | Endpoint | Description |
|---|---|---|
| POST | `/api/v1/register` | Register a new user |
| POST | `/api/v1/login` | Authenticate and receive a token |
| POST | `/api/v1/logout` | Revoke the current token |
| GET | `/api/v1/projects` | List the authenticated user's projects |
| POST | `/api/v1/projects` | Create a project |
| GET | `/api/v1/projects/{project}` | View a project |
| PUT | `/api/v1/projects/{project}` | Update a project |
| DELETE | `/api/v1/projects/{project}` | Delete a project |
| GET | `/api/v1/projects/{project}/tasks` | List tasks of a project |
| POST | `/api/v1/projects/{project}/tasks` | Create a task |
| GET | `/api/v1/tasks` | List all tasks (filter and search) |
| GET | `/api/v1/tasks/{task}` | View a task |
| PUT | `/api/v1/tasks/{task}` | Update a task |
| DELETE | `/api/v1/tasks/{task}` | Delete a task |
| GET | `/api/v1/dashboard` | Aggregated statistics |

## Postman Collection

> TODO — exported to `docs/postman_collection.json` in Phase 7.

## License

Released under the [MIT License](https://opensource.org/licenses/MIT).

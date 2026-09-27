# School Management System

A school management platform built with **Laravel 13**, **Livewire 4** and **Bootstrap 5**. It covers students, the course catalog, classes, enrollment, grading, attendance and role-based access control, plus a versioned **REST API** (`/api/v1`) for mobile applications.

- **Web UI:** responsive, keyboard accessible, server rendered with Livewire. Includes search, filters, sorting, pagination, loading states, empty states and confirmations for destructive actions.
- **REST API:** Sanctum bearer tokens, API Resources, consistent JSON errors, filtering, sorting, pagination and rate limiting. The OpenAPI 3.1 specification is in [`docs/openapi.json`](docs/openapi.json) and served at `/docs/api`.
- **Security:** policies enforced in both the UI and the API, brute-force throttling, no public self-registration, private file storage, and privacy-conscious API responses and logs.
- **Operations:** Docker (development and production), health and readiness probes, JSON logging with request IDs, queue worker, scheduler, and a GitHub Actions CI pipeline.

---

## Contents

1. [Features](#features)
2. [Tech stack](#tech-stack)
3. [Quick start with Docker](#quick-start-with-docker)
4. [Local installation without Docker](#local-installation-without-docker)
5. [Demo accounts](#demo-accounts)
6. [Docker reference](#docker-reference)
7. [Testing and quality checks](#testing-and-quality-checks)
8. [REST API](#rest-api)
9. [Architecture](#architecture)
10. [Security and privacy](#security-and-privacy)
11. [Deployment](#deployment)
12. [Troubleshooting](#troubleshooting)
13. [Assumptions and known limitations](#assumptions-and-known-limitations)

Operational runbooks (deployment, backups, rollback, retention) are in [`docs/operations.md`](docs/operations.md).

---

## Features

| Module | Highlights |
|---|---|
| **Authentication** | Login with throttling (5 attempts/min per email+IP), logout, password reset by email, profile and password management, deactivated accounts blocked immediately |
| **Roles and permissions** | `admin`, `teacher` and `student` roles (spatie/laravel-permission). Policies add ownership rules: teachers act only on their own classes, students see only their own records |
| **Users** | Admins create staff accounts (a password setup link is emailed), change roles, and deactivate or reactivate accounts. Deactivating also revokes API tokens |
| **Students** | Auto-generated student numbers (`S2026-0001`), contact and guardian details, grade level, admission date and status (active, inactive, suspended, graduated, withdrawn) with reasoned status history. Also: private photos, an optional login account, soft delete with a retention period, search, filters, sorting and pagination |
| **Courses** | Catalog with code, title, department and credits. Prerequisites reject self-references and cycles. Courses with classes can't be deleted |
| **Academic terms** | Term dates plus an enrollment window; exactly one term is marked current |
| **Classes (sections)** | A course offered in a term, with teacher (must hold the teacher role), room, schedule, capacity and status. Codes are unique per course and term. Capacity can't drop below the enrolled count; cancelling a class drops its enrollments |
| **Enrollment** | Enforces active student status, open class status, the term's enrollment window, passed prerequisites, no duplicate class, no second class of the same course in a term, no retaking a passed course, and capacity. The class row is locked during enrollment to prevent overbooking. Drop, re-enroll and complete, each with status history. Students are notified by queued email |
| **Grading** | Assessments (exam, quiz, assignment, project, participation) with max score and weight (total ≤ 100%). A gradebook grid with validation and a weighted current score. Letter grades use a configurable scale. Per-assessment and class averages and a grade distribution. Completion freezes the final score. Students see only their own results |
| **Attendance** | A register per class and date: present, absent, late or excused, with remarks. Duplicates are impossible (the DB has a unique constraint and saving again updates the record). Future dates, dates outside the term and students not enrolled are rejected. A report with filters, per-student rates (excused absences excluded) and a low-attendance flag |
| **Dashboards** | Admin overview (counts, nearly full classes, recent activity). Teachers see their classes and today's attendance status. Students see their classes with current grade and attendance rate |

## Tech stack

| Layer | Choice |
|---|---|
| Runtime | PHP 8.4, Laravel 13 |
| UI | Livewire 4 (class-based components), Bootstrap 5.3, Bootstrap Icons, Vite |
| Auth | Laravel Fortify (web), Laravel Sanctum (API tokens), spatie/laravel-permission |
| API | API Resources, spatie/laravel-query-builder (filters/sorts/includes), Scramble (OpenAPI) |
| Database | PostgreSQL 17 (recommended/production), SQLite in-memory for tests |
| Cache / queue / sessions | Redis 7 (database drivers also supported) |
| Quality | PHPUnit 12, Pint (Laravel preset), Larastan level 6, ESLint |
| Delivery | Docker multi-stage images, Docker Compose, GitHub Actions |

---

## Quick start with Docker

Prerequisites: Docker Engine 24+ with Docker Compose v2, and Git. You don't need PHP, Composer or Node on the host.

```bash
git clone <repository-url> school-management-system
cd school-management-system
cp .env.example .env                      # defaults target the Docker stack

docker compose build                      # development PHP image
docker compose up -d db redis mailpit     # infrastructure first

docker compose run --rm app composer install
docker compose run --rm app php artisan key:generate
docker compose run --rm app php artisan migrate --seed      # schema + roles + demo data
docker compose run --rm node sh -c "npm ci && npm run build" # front-end assets

docker compose up -d                      # app, web, queue, scheduler
```

Open:

| URL | What |
|---|---|
| http://localhost:8080 | Application (sign in with a [demo account](#demo-accounts)) |
| http://localhost:8080/docs/api | Interactive API documentation |
| http://localhost:8025 | Mailpit, which catches outgoing mail (password resets, enrollment notices) |
| http://localhost:8080/health/ready | Readiness probe (JSON) |

Change `APP_PORT`, `FORWARD_DB_PORT` (default `5433`) or `FORWARD_MAILPIT_UI_PORT` in `.env` if those ports are busy.

## Local installation without Docker

Prerequisites: PHP 8.3+ (8.4 recommended) with `pdo_pgsql` (or `pdo_sqlite`), `intl`, `bcmath`, `zip`, `gd` and `redis` (optional); Composer 2; Node 22+; PostgreSQL 15+ (or SQLite).

```bash
cp .env.example .env
# Edit .env: DB_HOST=127.0.0.1, REDIS_HOST=127.0.0.1, MAIL_MAILER=log
# (or CACHE_STORE=database, SESSION_DRIVER=database, QUEUE_CONNECTION=database without Redis)
composer setup            # install, key:generate, migrate --seed, npm ci, npm run build
php artisan serve         # http://127.0.0.1:8000
php artisan queue:work    # in a second terminal (emails, notifications)
php artisan schedule:work # in a third terminal (maintenance tasks)
```

To use SQLite, set `DB_CONNECTION=sqlite`, remove the other `DB_*` lines and create `database/database.sqlite`.

## Demo accounts

The demo seeder (`DemoDataSeeder`, **never run in production**) creates a current and a previous term, 9 courses with prerequisites, 15 classes, 45 students (36 with logins), grades and 10 days of attendance.

| Role | Email | Password |
|---|---|---|
| Administrator | `admin@school.test` | `password` |
| Teacher | `teacher@school.test` | `password` |
| Student | `student@school.test` | `password` |

In production, only roles and permissions are seeded. Create the first administrator with:

```bash
php artisan school:create-admin head@school.example.org --name="Head Teacher"       # prompts for a password
php artisan school:create-admin head@school.example.org --send-reset-link            # emails a setup link instead
```

---

## Docker reference

**Development stack** ([`compose.yaml`](compose.yaml)):

| Service | Purpose |
|---|---|
| `app` | PHP-FPM 8.4 (dev image with Composer and PCOV); source bind-mounted, `vendor/` in a named volume |
| `web` | Nginx serving `public/` and proxying PHP to `app` |
| `queue` | `php artisan queue:work` (Redis) |
| `scheduler` | `php artisan schedule:work` |
| `db` | PostgreSQL 17 (data in the `db-data` volume) |
| `redis` | Redis 7 (append-only file persistence) |
| `mailpit` | SMTP catcher with a web UI |
| `node` | One-off front-end tooling (`--profile tools` / `docker compose run --rm node ...`) |

All long-running services define health checks.

Common commands:

```bash
docker compose ps                                        # status and health
docker compose logs -f app queue                         # follow logs
docker compose exec app php artisan tinker               # REPL
docker compose exec app php artisan migrate:fresh --seed # reset demo data
docker compose exec app composer check                   # lint + static analysis + tests
docker compose run --rm node npm run dev                 # Vite (rebuild on change)
docker compose down                                      # stop (keeps volumes)
docker compose down -v                                   # stop and DELETE database/redis/vendor volumes
```

**Production images** ([`docker/php/Dockerfile`](docker/php/Dockerfile)) are multi-stage:

| Stage | Contents |
|---|---|
| `base` | PHP-FPM + extensions, hardened `php.ini`, FPM health check |
| `dev` | `base` plus Composer and PCOV |
| `vendor` | Production Composer dependencies with an optimized, authoritative class map (no dev packages, no tests) |
| `assets` | Vite build on Node 22 |
| `production` | Code, vendor and built assets. Runs as `www-data`; the entrypoint builds config/route/view caches from runtime environment variables |
| `web` | Nginx with the public assets and security headers |

Secrets are **never** baked into images: `.env` files are excluded by [`.dockerignore`](.dockerignore) and removed in the image. See [Deployment](#deployment) for [`compose.prod.yaml`](compose.prod.yaml).

---

## Testing and quality checks

The suite has **unit tests** for the grade calculator and domain enums, **service tests** for every enrollment, grading and attendance rule, **Livewire feature tests** for the web workflows, validation, uploads and authorization (including IDOR attempts), **API tests** for authentication, permissions, validation, response formats and workflows, **operations tests**, and a **smoke test** that renders every page for every role against the demo data set.

```bash
composer test            # php artisan test (SQLite in memory, ~180 tests)
composer test:coverage   # with coverage, fails under 80% (PCOV/Xdebug required)
composer lint            # Pint (code style)
composer format          # Pint (fix)
composer analyse         # Larastan level 6
composer check           # lint + analyse + test
npm run lint             # ESLint
npm run build            # production assets
```

With Docker, prefix these with `docker compose exec app` (`docker compose run --rm node` for npm).

To run the suite against PostgreSQL instead of SQLite, export real environment variables (they take precedence over `phpunit.xml`):

```bash
DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_DATABASE=school_testing DB_USERNAME=... DB_PASSWORD=... php artisan test
```

### Continuous integration

[`.github/workflows/ci.yml`](.github/workflows/ci.yml) runs on every push to `main` and every pull request. Jobs run in parallel, with Composer, npm and Docker layer caching:

| Job | Steps |
|---|---|
| **Code quality** | `composer validate`, PHP syntax check, Pint, Larastan (GitHub annotations), `composer audit` |
| **Tests (SQLite)** | Unit suite, then the full suite with PCOV coverage (min 80%); JUnit and Clover reports uploaded as artifacts |
| **Tests (PostgreSQL)** | Disposable PostgreSQL 17 service: `migrate:fresh --seed` (verifies migrations and seeders), `migrate:refresh` (verifies rollbacks), full suite |
| **Front-end** | `npm ci`, ESLint, Vite build, `npm audit` of production dependencies |
| **Docker** | Builds the `production` and `web` image targets (after code quality passes) |

Actions are pinned to major versions, and runs for superseded commits on the same branch are cancelled.

---

## REST API

- **Base URL:** `{APP_URL}/api/v1`
- **Documentation:** `/docs/api` (UI) and `/docs/api.json`. It's open in the `local` environment and limited to signed-in administrators elsewhere. A static copy lives at [`docs/openapi.json`](docs/openapi.json); regenerate it with `composer docs:api`.

### Authentication

```bash
curl -s -X POST http://localhost:8080/api/v1/auth/token \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"email":"teacher@school.test","password":"password","device_name":"my-phone"}'
```

```json
{ "data": { "token": "1|Xq…", "token_type": "Bearer", "expires_at": "2026-10-04T12:00:00+00:00",
            "user": { "id": 2, "name": "Grace Hopper", "email": "teacher@school.test", "role": "teacher", "is_active": true, "student_id": null } } }
```

Send the token as `Authorization: Bearer <token>`. Tokens expire after `API_TOKEN_EXPIRATION` minutes (default 7 days). `DELETE /auth/token` revokes the current token, and deactivating a user revokes all of theirs.

### Endpoints

| Area | Endpoints |
|---|---|
| Auth | `POST /auth/token`, `GET /auth/me`, `DELETE /auth/token` |
| Students | `GET/POST /students`, `GET/PUT/PATCH/DELETE /students/{id}`, `GET /students/{id}/photo` |
| Courses | `GET/POST /courses`, `GET/PUT/PATCH/DELETE /courses/{id}` |
| Terms | `GET/POST /terms`, `GET/PUT/PATCH/DELETE /terms/{id}` |
| Classes | `GET/POST /sections`, `GET/PUT/PATCH/DELETE /sections/{id}`, `GET /sections/{id}/grade-summary`, `GET /sections/{id}/attendance-summary`, `POST /sections/{id}/attendance` (whole register) |
| Enrollments | `GET/POST /enrollments`, `GET /enrollments/{id}`, `POST /enrollments/{id}/drop`, `POST /enrollments/{id}/complete` |
| Grades | `GET/POST /assessments`, `GET/PUT/PATCH/DELETE /assessments/{id}`, `GET/POST /grades` (POST upserts), `GET/PUT/PATCH/DELETE /grades/{id}` |
| Attendance | `GET/POST /attendance`, `GET/PUT/PATCH/DELETE /attendance/{id}` |

What each role can do:

| | Admin | Teacher | Student |
|---|---|---|---|
| Students | full access | read students in own classes (no contact/guardian fields) | read self |
| Courses and terms | full access | read | read |
| Classes | full access | read own classes | read enrolled classes |
| Enrollments | full access | read own classes | read own |
| Assessments and grades | full access | manage for own classes | read own grades |
| Attendance | full access | manage for own classes | read own records |

### Conventions

- **Pagination:** `?page=2&per_page=50` (default 15, max 100). Responses include `data`, `links` and `meta` (`current_page`, `per_page`, `total`, …).
- **Filtering:** `?filter[status]=active&filter[search]=smith`. Allowed filters are listed per endpoint in the docs. Unknown filters, sorts or includes return **400**.
- **Sorting:** `?sort=-last_name` (a `-` prefix means descending).
- **Includes:** `?include=course,term,teacher` where documented.
- **Partial updates:** `PUT` and `PATCH` validate only the fields you send.
- **Status codes:** `200` OK, `201` created, `204` deleted, `400` bad query, `401` unauthenticated, `403` forbidden, `404` not found, `405` method not allowed, `422` validation or business-rule error, `429` rate limited.
- **Errors** always look like this:

  ```json
  { "message": "Class MATH101-A is full (25 seats).", "errors": { "section_id": ["Class MATH101-A is full (25 seats)."] } }
  ```

- **Rate limits:** `API_RATE_LIMIT` requests per minute per user (default 60) and 5 token requests per minute per email+IP. Responses carry `X-RateLimit-*` headers, and `429` responses carry `Retry-After`.

Example: enroll a student, then record a grade.

```bash
TOKEN=...   # admin token
curl -s -X POST http://localhost:8080/api/v1/enrollments -H "Authorization: Bearer $TOKEN" \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"student_id": 12, "section_id": 3, "reason": "Registrar request"}'

curl -s -X POST http://localhost:8080/api/v1/grades -H "Authorization: Bearer $TOKEN" \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"assessment_id": 7, "enrollment_id": 41, "score": 18.5, "feedback": "Well argued"}'
```

---

## Architecture

```
app/
├── Enums/               Role, Permission, StudentStatus, EnrollmentStatus, SectionStatus, AssessmentType, AttendanceStatus
├── Models/              Eloquent models; visibleTo() scopes; RecordsStatusHistory trait
├── Services/            Domain logic shared by web and API
│   ├── EnrollmentService    eligibility rules, locking, enroll/drop/complete, events
│   ├── GradebookService     assessments, grade recording, class summaries
│   ├── GradeCalculator      pure weighted-average / letter-grade maths (unit tested)
│   ├── AttendanceService    registers, duplicate protection, summaries
│   ├── CatalogService       courses (prerequisite cycles), terms, classes (capacity, cancellation)
│   ├── StudentService       lifecycle, accounts, photos, deletion
│   └── UserService          staff accounts, activation, token revocation
├── Validation/          Field rules shared by Livewire forms and API form requests
├── Policies/            Authorization (role permission + ownership)
├── Livewire/            Web UI components (Students, Courses, Terms, Sections, Grades, Attendance, Users, Dashboard)
├── Http/
│   ├── Controllers/Api/V1   Thin API controllers
│   ├── Requests/Api/V1      API validation (authorize() runs before validation)
│   ├── Resources/V1         JSON representations
│   └── Middleware           EnsureUserIsActive, AssignRequestId
├── Events, Listeners, Notifications   StudentEnrolled / EnrollmentDropped → queued email
└── Exceptions/          DomainRuleException (rendered as 422), EnrollmentException
```

Key decisions:

- **One implementation of each business rule.** Livewire components and API controllers are thin; both call the same services, validation rule classes and policies. A violated rule throws a `DomainRuleException` carrying a field name, which surfaces as a form error in the UI and a `422` in the API.
- **Defence in depth for data integrity.** Unique constraints back up the rules on enrollments `(student, class)`, grades `(assessment, enrollment)`, attendance `(class, student, date)` and class codes `(course, term, code)`. Enrollment runs in a transaction with a row lock on the class to prevent overbooking.
- **Authorization happens server-side on every request.** Livewire actions re-authorize (they are separate HTTP calls), IDs are resolved through `visibleTo()` scopes, and the API authorizes before validation so unauthorized callers learn nothing from validation errors.
- **History instead of destructive updates.** Student and enrollment status changes are recorded with actor and reason. Re-enrolling after a drop reactivates the same row, keeping its history.
- **"Classes" in the UI are `sections` in code and in the API**, to avoid the reserved word `class`.

The data model is in [`database/migrations`](database/migrations). Domain settings (grade scale, passing score, attendance threshold, upload limits, retention) live in [`config/school.php`](config/school.php) and can be overridden through `.env`.

---

## Security and privacy

- **Mass assignment:** models declare fillable attributes, and only validated data reaches them. Foreign keys such as `user_id` can't be set through forms or the API.
- **CSRF:** Laravel's CSRF middleware covers all web forms and Livewire requests. The API is stateless with bearer tokens.
- **XSS:** all output is escaped with Blade `{{ }}`, the toast script inserts text with `textContent`, SVG uploads are rejected, and photos are served with `nosniff` and a restrictive CSP. There's a test for escaped output.
- **SQL injection:** Eloquent and the query builder with bound parameters; sort and filter fields come from allow-lists.
- **Brute force:** web and API logins are throttled per email+IP, and API traffic is rate limited per user.
- **Insecure direct object references:** policies check ownership, `visibleTo()` scopes every list, and tests try to access other users' records.
- **Passwords:** bcrypt, minimum 10 characters with mixed case and a number. Admins never set passwords for others; users receive a setup link.
- **Files:** student photos are validated as JPG, PNG or WebP (≤ 2 MB), stored under random names on the private disk, and streamed only after an authorization check.
- **Sensitive data:** contact, guardian, birth-date and notes fields are visible only to admins and the student themselves (UI and API). Logs record IDs only; no names or emails.
- **Retention:** deleting a student is a soft delete (active enrollments dropped, login disabled). After `STUDENT_RETENTION_DAYS` (default 365) the scheduled `model:prune` job permanently removes the record, enrollments, grades, attendance, history and photo. Choose a period that satisfies your jurisdiction's education-record rules.

## Deployment

A condensed checklist; the full runbook is in [`docs/operations.md`](docs/operations.md).

1. **Configuration:** copy [`.env.production.example`](.env.production.example) to `.env.production` (or use your platform's secret manager). Set `APP_ENV=production` and `APP_DEBUG=false`, a unique `APP_KEY`, strong `DB_PASSWORD` and `REDIS_PASSWORD`, real SMTP settings and `LOG_CHANNEL=stderr_json`.
2. **HTTPS:** terminate TLS at a reverse proxy or load balancer (Caddy, Traefik, Nginx or a cloud LB) in front of the `web` service. Set `APP_URL=https://…`, `SESSION_SECURE_COOKIE=true` and `TRUSTED_PROXIES`, and enable HSTS at the proxy.
3. **Build and migrate:**
   ```bash
   docker compose --env-file .env.production -f compose.prod.yaml build
   docker compose --env-file .env.production -f compose.prod.yaml run --rm migrate
   docker compose --env-file .env.production -f compose.prod.yaml run --rm app php artisan db:seed --class=RolesAndPermissionsSeeder --force
   docker compose --env-file .env.production -f compose.prod.yaml run --rm app php artisan school:create-admin you@school.org
   docker compose --env-file .env.production -f compose.prod.yaml up -d
   ```
4. **Storage:** uploads and logs live in the `app-storage` volume. PHP containers run as `www-data` on a read-only root filesystem with `no-new-privileges`.
5. **Workers:** the `queue` and `scheduler` services must run. After each deploy, restart the queue workers (`php artisan queue:restart` or recreate the container) so they load the new code.
6. **Monitoring:** use `/up` for liveness and `/health/ready` for readiness (database, cache, queue; returns 503 when degraded). Every response carries an `X-Request-Id` for log correlation.
7. **Rollback:** redeploy the previous image tag. Migrations are additive where possible; if one must be reverted, run `php artisan migrate:rollback --step=1` **after restoring a backup** (see the runbook).

## Troubleshooting

| Symptom | Fix |
|---|---|
| `502 Bad Gateway` after recreating `app` | Nginx cached the old container IP: `docker compose restart web` |
| `Vite manifest not found` | Build assets: `docker compose run --rm node sh -c "npm ci && npm run build"` |
| `No application encryption key` | `docker compose exec app php artisan key:generate`, then restart `app`, `queue` and `scheduler` |
| Database connection refused | Check `docker compose ps db` is healthy and that `.env` has `DB_HOST=db` (Docker) or `127.0.0.1` (host) |
| `password authentication failed` after changing `DB_PASSWORD` | Postgres only applies credentials when the volume is first created: `docker compose down -v` (deletes data) or change the password inside Postgres |
| Emails not arriving | Look in Mailpit at http://localhost:8025, and make sure the `queue` service is running (notifications are queued) |
| Changes to PHP config not applied | `docker compose build app && docker compose up -d` |
| Permission errors on `storage/` (non-Docker) | Make `storage/` and `bootstrap/cache/` writable by the web server user |
| Port already in use | Change `APP_PORT`, `FORWARD_DB_PORT` or `FORWARD_MAILPIT_UI_PORT` in `.env` |
| Slow tests on Windows/macOS | Expected with bind mounts; `vendor/` already lives in a named volume. Run specific tests with `--filter` |
| Stale config or routes in production | `php artisan optimize:clear` then restart the containers (the entrypoint rebuilds caches) |

## Assumptions and known limitations

- **Enrollment is staff-driven.** Administrators (registrars) enroll students; students don't self-enroll. Teachers can view their rosters but can't change enrollments.
- **One teacher per class.** Co-teaching and timetable clash detection aren't modelled; `schedule` is free text.
- **Grades are weighted averages** of graded assessments, normalised by the weight graded so far. Letter-grade boundaries and the passing score are configurable in `config/school.php`.
- **Parents and guardians** are contact details on the student record, not user accounts.
- **Not yet implemented:** two-factor authentication and passkeys (Fortify supports both; the UI isn't built), end-to-end browser tests (Laravel Dusk; critical journeys are covered by Livewire feature tests instead), audit logging beyond status history, and localisation (English only).
- **Student photos use the local private disk by default.** For multi-server deployments, set `STUDENT_PHOTO_DISK` to an S3-compatible disk.

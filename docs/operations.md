# Operations runbook

This runbook covers production deployment, secrets, HTTPS, workers, monitoring, backups, rollback and data retention for the School Management System. The commands assume the Docker Compose production stack ([`compose.prod.yaml`](../compose.prod.yaml)); adapt them to your orchestrator (Kubernetes, ECS, Nomad…) as needed.

```bash
alias dcp='docker compose --env-file .env.production -f compose.prod.yaml'
```

---

## 1. Environments and secrets

- Configuration is entirely environment-driven. Start from [`.env.production.example`](../.env.production.example).
- **Never commit** `.env` or `.env.production`; both are git-ignored and excluded from the Docker build context.
- Prefer a secret manager (Vault, AWS Secrets Manager, Doppler, GitHub/GitLab environment secrets) that injects variables at runtime. The images read everything from the environment, and the entrypoint builds Laravel's config cache at container start.
- Required secrets: `APP_KEY`, `DB_PASSWORD`, `REDIS_PASSWORD` and the SMTP credentials.
- Generate `APP_KEY` once per environment with `php artisan key:generate --show` and keep it stable. Rotating it invalidates sessions and anything encrypted with it. To rotate safely, set the old key in `APP_PREVIOUS_KEYS` first.
- The containers refuse to start without `APP_KEY`.

## 2. HTTPS and proxies

The `web` service speaks plain HTTP on port 80 inside the network. Terminate TLS in front of it:

- A managed load balancer, or a reverse proxy such as Caddy, Traefik or Nginx with Let's Encrypt.
- Set `APP_URL=https://your-domain` and `SESSION_SECURE_COOKIE=true`.
- Set `TRUSTED_PROXIES` to the proxy address or CIDR (or `*` when the app is reachable only through the proxy). Laravel then generates `https` URLs and sees real client IPs, which rate limiting and login throttling need.
- Enable HSTS (`Strict-Transport-Security: max-age=31536000; includeSubDomains`) at the TLS terminator.
- Only publish the proxy's ports publicly. Keep `db` and `redis` unexposed (the production compose file doesn't publish them).

## 3. First deployment

```bash
dcp build                                   # or pull tagged images from your registry
dcp up -d db redis
dcp run --rm migrate                        # php artisan migrate --force
dcp run --rm app php artisan db:seed --class=RolesAndPermissionsSeeder --force
dcp run --rm app php artisan school:create-admin head@school.example.org --send-reset-link
dcp up -d
curl -fsS https://your-domain/health/ready
```

`DemoDataSeeder` never runs when `APP_ENV=production`.

## 4. Routine deployments

1. Build and tag images with the git SHA, e.g. `APP_IMAGE=registry/school-app:<sha>` and `WEB_IMAGE=registry/school-web:<sha>`.
2. **Back up the database** (see section 7).
3. Run migrations: `dcp run --rm migrate`. Write migrations to be backwards compatible, i.e. additive (expand first, contract in a later release), so the previous release keeps working while the new one rolls out.
4. Roll out: `dcp up -d`. The entrypoint runs `php artisan optimize` in each container.
5. Restart the workers so they pick up new code: `dcp up -d --force-recreate queue scheduler` (or `php artisan queue:restart`).
6. Verify `/health/ready`, sign in, and check the logs for errors.

## 5. Queue and scheduler

| Process | Command | Notes |
|---|---|---|
| Queue worker | `php artisan queue:work --tries=3 --backoff=10 --max-time=3600 --memory=256` | Sends enrollment notifications and password-setup emails. Scale by adding replicas. `--max-time` recycles workers hourly |
| Scheduler | `php artisan schedule:work` (or cron: `* * * * * php artisan schedule:run`) | Run **exactly one** instance. Tasks use `onOneServer()`, which relies on the shared Redis cache |

Scheduled tasks ([`routes/console.php`](../routes/console.php)):

| Task | When | Purpose |
|---|---|---|
| `model:prune` | daily 02:00 | Permanently purge students soft-deleted more than `STUDENT_RETENTION_DAYS` days ago |
| `sanctum:prune-expired --hours=24` | daily | Remove expired API tokens |
| `auth:clear-resets` | every 15 min | Remove expired password-reset tokens |
| `queue:prune-failed --hours=168` | daily | Keep failed jobs for a week |

Inspect failed jobs with `php artisan queue:failed` and retry them with `php artisan queue:retry all`.

## 6. Monitoring and logging

- **Liveness:** `GET /up` returns 200 once the app boots.
- **Readiness:** `GET /health/ready` checks the database, cache and queue connection. It returns `200 {"status":"ok"}` or `503 {"status":"degraded"}` with per-check `ok`/`fail`. Failure details go to the logs only.
- **Logs:** `LOG_CHANNEL=stderr_json` writes one JSON object per line to stderr, ready for Loki, ELK, CloudWatch or Datadog. Every request is tagged with `request_id` (also returned as the `X-Request-Id` header; a well-formed incoming header is reused), `method`, `path` and `user_id`. Personal data such as names and emails is **not** logged.
- **Container health:** PHP-FPM, Nginx, Postgres, Redis, the queue and the scheduler all define Docker health checks.
- Alert on 5xx rate, `/health/ready` failures, queue backlog (`php artisan queue:monitor redis:default --max=100`) and failed-job count.

## 7. Backups and restore

**What to back up:**

| Data | Where | How |
|---|---|---|
| Database | `db-data` volume / managed Postgres | `pg_dump` (logical) and/or provider snapshots with point-in-time recovery |
| Uploaded photos and logs | `app-storage` volume (`storage/app/private/student-photos`) | Volume snapshot or `tar`; on S3, use bucket versioning |
| Configuration | Secret manager | Keep `APP_KEY` safe: encrypted data and sessions depend on it |

Redis holds only cache, sessions and pending jobs; it doesn't need backups.

**Database backup** (compressed custom format, run nightly and before every migration):

```bash
dcp exec -T db sh -c 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" --format=custom --no-owner' \
  > backups/school-$(date +%Y%m%d-%H%M%S).dump
```

**Storage backup:**

```bash
docker run --rm -v school-management-prod_app-storage:/data:ro -v "$PWD/backups":/backup alpine \
  tar czf /backup/storage-$(date +%Y%m%d-%H%M%S).tar.gz -C /data .
```

Encrypt backups at rest, copy them off-site, keep for example 7 daily, 4 weekly and 12 monthly copies, and **test restores regularly**. Backups contain student records, so protect and retire them in line with your retention policy.

**Restore** into a stopped application:

```bash
dcp stop app web queue scheduler
dcp exec -T db sh -c 'dropdb -U "$POSTGRES_USER" --if-exists "$POSTGRES_DB" && createdb -U "$POSTGRES_USER" "$POSTGRES_DB"'
dcp exec -T db sh -c 'pg_restore -U "$POSTGRES_USER" -d "$POSTGRES_DB" --no-owner' < backups/school-YYYYMMDD-HHMMSS.dump
docker run --rm -v school-management-prod_app-storage:/data -v "$PWD/backups":/backup alpine \
  sh -c 'tar xzf /backup/storage-YYYYMMDD-HHMMSS.tar.gz -C /data'
dcp up -d
```

## 8. Rollback strategy

1. **Application only** (no schema change, or additive changes only): redeploy the previous image tags (`APP_IMAGE=…:<previous-sha> dcp up -d`), then restart the workers.
2. **Migration must be undone:** stop the app, then either run `php artisan migrate:rollback --step=N` (every migration has a `down()`; CI verifies rollbacks with `migrate:refresh`) or, for destructive changes, **restore the pre-deployment backup**. Then deploy the previous images.
3. Put the app into maintenance mode during risky operations with `php artisan down --secret=<token>` and bring it back with `php artisan up`.

## 9. Storage and permissions

- Production PHP containers run as `www-data` (uid 82) on a read-only root filesystem, with `no-new-privileges`.
- Only `storage/` (named volume) and `bootstrap/cache` (tmpfs) are writable.
- Student photos are on the private `local` disk (`storage/app/private`) and are never publicly addressable; they're streamed after an authorization check. For multiple app servers, use an S3-compatible disk (`STUDENT_PHOTO_DISK=s3` plus the `AWS_*` variables) and make the bucket private.
- Uploads are limited to JPG, PNG and WebP up to `STUDENT_PHOTO_MAX_KB` (default 2 MB). Nginx caps request bodies at 10 MB.

## 10. Data protection and retention

- **Minimisation:** the API omits contact, guardian and birth-date fields unless the caller is an administrator or the student. Teachers see only students in their classes.
- **Soft delete, then purge:** deleting a student drops active enrollments, disables the login and soft-deletes the record. After `STUDENT_RETENTION_DAYS` (default 365) `model:prune` permanently deletes the student with their enrollments, grades, attendance, status history and photo.
  - **Set this period to match your legal obligations.** Many jurisdictions require academic records to be kept for years; others require deletion on request. For immediate erasure requests, soft-delete the student and run `php artisan model:prune --model="App\Models\Student"` with a temporarily shortened retention, or delete via a reviewed database procedure.
- **Access removal:** deactivating a user signs them out on their next request and revokes all their API tokens.
- **Logs:** they contain request IDs, user IDs, paths and entity IDs but no personal data. Set log retention (`LOG_DAILY_DAYS` for file logs, or your aggregator's policy) accordingly.
- **Backups** contain personal data; encrypt them, restrict access and expire them.

## 11. Security checklist

- [ ] `APP_ENV=production` and `APP_DEBUG=false`
- [ ] HTTPS enforced, `SESSION_SECURE_COOKIE=true`, HSTS enabled
- [ ] Strong unique passwords for Postgres and Redis; neither is exposed publicly
- [ ] `composer audit` and `npm audit` clean (both run in CI)
- [ ] First admin created with `school:create-admin`; demo data absent
- [ ] Backups scheduled, encrypted and restore-tested
- [ ] Log shipping and alerting configured
- [ ] Only the proxy is reachable from the internet

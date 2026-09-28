# Deploying to Render (free plan) with a Neon database

This guide deploys the application as a **single free Render web service** with a **free Neon PostgreSQL** database. The free plan has no shell, background workers or cron jobs, so the image in [`docker/render`](../docker/render) runs Nginx, PHP-FPM, the queue worker and the scheduler in one container. On every start it runs migrations, syncs roles and creates the first administrator.

| Piece | Service | Free-plan notes |
|---|---|---|
| Web app, queue, scheduler | Render web service (Docker) | Sleeps after about 15 minutes idle; the first request after that takes roughly 30–60 s |
| Database | Neon PostgreSQL | About 0.5 GB storage; doesn't expire |
| Email (optional) | Brevo, Resend or any SMTP | Needed for password-reset and enrollment emails |

> **Limitations of the free plan:** uploaded student photos are stored on the container's disk and are **lost on every redeploy or restart**, because the free plan has no persistent disk. Use an S3-compatible bucket (for example Cloudflare R2) with `STUDENT_PHOTO_DISK=s3` if photos matter. Scheduled tasks only run while the service is awake.

---

## 1. Create the database on Neon

1. Sign up at <https://neon.tech> and create a project (choose the region closest to your Render region).
2. On the project dashboard, open **Connection details**. Pick the **direct** connection (turn *Connection pooling* off) and copy the connection string. It looks like this:
   ```
   postgresql://neondb_owner:AbCd1234@ep-cool-name-123456.eu-central-1.aws.neon.tech/neondb?sslmode=require
   ```
   This is your `DB_URL`. Keep it secret.

## 2. Generate an application key

Run this locally (Docker stack running):

```bash
docker compose exec app php artisan key:generate --show
```

Copy the whole output, including the `base64:` prefix. That's your `APP_KEY`. Use a new key for production; don't reuse the one in your local `.env`.

## 3. Create the service on Render

1. Sign up at <https://render.com> and connect your GitHub account.
2. Click **New +** → **Blueprint**, select the `school-management-system` repository, and choose the branch to deploy (normally `main`).
3. Render reads [`render.yaml`](../render.yaml) and asks for the secret values:

   | Variable | Value |
   |---|---|
   | `APP_KEY` | The key from step 2 |
   | `APP_URL` | `https://school-management.onrender.com`. If the name is taken, Render shows the real URL after creation; update this variable then |
   | `DB_URL` | The Neon connection string from step 1 |
   | `ADMIN_EMAIL` | Your administrator email |
   | `ADMIN_PASSWORD` | A strong password (10+ characters, upper and lower case, a number) |
   | `MAIL_HOST`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` | Leave empty for now if you have no SMTP provider |

4. Click **Apply**. The first build takes about 5–10 minutes. Follow it under **Logs**; you should see migrations run and `Administrator created.`.
5. Open the URL and sign in with `ADMIN_EMAIL` / `ADMIN_PASSWORD`.

**Without Blueprints:** choose **New +** → **Web Service**, pick the repository, set *Language* to **Docker**, *Dockerfile path* to `./docker/render/Dockerfile`, *Instance type* to **Free** and *Health check path* to `/up`, then add the variables listed in `render.yaml` by hand.

## 4. After the first deploy

- **Security:** once you've signed in, delete `ADMIN_PASSWORD` from the Render environment. The account already exists, and the start script skips creation when the email is registered.
- **Demo data (optional, for a showcase):** set `SEED_DEMO_DATA=true` and redeploy. It only loads into an **empty** database and creates demo accounts with the password `password`, so never enable it for a real school. Set it back to `false` afterwards.
- **Email:** to enable password resets and notifications, create a free SMTP account (Brevo: *SMTP & API → SMTP*). Set `MAIL_MAILER=smtp`, `MAIL_HOST` (`smtp-relay.brevo.com`), `MAIL_PORT=587`, `MAIL_USERNAME`, `MAIL_PASSWORD` and a verified `MAIL_FROM_ADDRESS`.
- **Custom domain:** in Render, go to *Settings → Custom Domains*, then update `APP_URL`.
- **Automatic deploys:** every push to the deployed branch triggers a new deploy (`autoDeploy: true`).

## Troubleshooting

| Symptom | Cause / fix |
|---|---|
| Deploy fails with `APP_KEY is not set` | Add `APP_KEY` in *Environment* and redeploy |
| `SQLSTATE[08006] ... connection refused` or SSL errors | Check `DB_URL`: it must be the **direct** Neon URL and include `?sslmode=require` |
| Links or forms use `http://`, or you get "page expired" on login | `APP_URL` must be the `https://` URL; keep `TRUSTED_PROXIES=*` |
| First page load is slow | The free instance was asleep; later requests are fast |
| `Administrator created` never appears | `ADMIN_PASSWORD` failed the password rules; the log shows the reason |
| Photos disappear after a deploy | Expected on the free plan (no persistent disk); use S3/R2 storage |

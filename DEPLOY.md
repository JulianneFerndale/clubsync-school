# Deploying ClubSync to Render

ClubSync is a Laravel app. Its database (Supabase Postgres) and auth (Firebase)
are already hosted, so deploying means hosting **only the PHP app** on a
permanent HTTPS URL. Once live, the site is installable on iPhone/Android via
the browser's **Add to Home Screen** (it's a PWA with offline support).

The repo already contains everything Render needs:

| File | Purpose |
|------|---------|
| `Dockerfile` | Builds the image: compiles Vite assets, installs Composer deps, runs nginx + php-fpm |
| `docker/entrypoint.sh` | Renders the nginx port, runs migrations, caches config/views, starts the server |
| `docker/default.conf.template` | nginx site config (listens on Render's `$PORT`) |
| `render.yaml` | Render Blueprint — declares the web service + which env vars to set |
| `.dockerignore` | Keeps secrets/artifacts out of the build |

---

## 1. Push the code to GitHub

Render deploys from your GitHub repo (`JulianneFerndale/clubsync`). Make sure
the latest code — including these deploy files — is committed and pushed to the
branch you'll deploy (e.g. `main`).

## 2. Get an APP_KEY

Run locally and copy the output (starts with `base64:`):

```bash
php artisan key:generate --show
```

## 3. Create the service on Render

1. Go to <https://dashboard.render.com> → **New +** → **Blueprint**.
2. Connect your GitHub and select the `clubsync` repo.
3. Render reads `render.yaml` and shows the service + a list of env vars to fill
   (the ones marked `sync: false`). Fill them in (see table below).
4. Click **Apply** / **Create**. The first build takes a few minutes.

## 4. Environment variables to set

Non-secret values are already baked into `render.yaml`. You must supply these
(copy from your local `.env`):

| Variable | Value |
|----------|-------|
| `APP_KEY` | output of `php artisan key:generate --show` |
| `APP_URL` | leave blank for now; set in step 5 |
| `DB_HOST` | your Supabase pooler host (e.g. `aws-1-…pooler.supabase.com`) |
| `DB_DATABASE` | `postgres` |
| `DB_USERNAME` | `postgres.<project-ref>` |
| `DB_PASSWORD` | your Supabase DB password |
| `FIREBASE_API_KEY` | from your local `.env` |
| `FIREBASE_PROJECT_ID` | from your local `.env` |
| `FIREBASE_DATABASE_URL` | from your local `.env` |
| `GEMINI_API_KEY` | from your local `.env` |

## 5. Set APP_URL and redeploy

After the first deploy, Render gives you a URL like
`https://clubsync.onrender.com`. Set the `APP_URL` env var to that exact URL,
then trigger a redeploy (**Manual Deploy → Deploy latest commit**). This makes
asset links, the manifest, and the service worker resolve correctly.

## 6. Install on your phone

Open the Render URL in Safari (iPhone) or Chrome (Android), then
**Share → Add to Home Screen**. To test offline: load it once online, then turn
on Airplane Mode — you'll get the offline page / "No internet" banner.

---

## Notes & limitations

- **Free tier sleeps.** Render free web services spin down after ~15 min idle;
  the next request takes ~30–60s to wake. Fine for a demo.
- **Shared database.** This uses the same Supabase DB as local dev, so they
  share data and sessions. Create a separate Supabase project if you want an
  isolated production DB.
- **AI queue jobs.** `QUEUE_CONNECTION=database`, but the free web service does
  not run a queue worker, so AI announcement drafting won't process in the
  background until you add a worker (a paid Render "Background Worker" running
  `php artisan queue:work`). Login, browsing, and the PWA/offline features do
  not need it.
- **Uploaded files are ephemeral.** `FILESYSTEM_DISK=local` lives on the
  container's disk and is wiped on restart. Profile/club photos that rely on
  Firebase Storage URLs are unaffected; switch to S3/Firebase for any
  locally-stored uploads if you need persistence.
- **HTTPS detection.** `bootstrap/app.php` trusts proxy headers, so the app
  correctly generates `https://` URLs behind Render's load balancer.

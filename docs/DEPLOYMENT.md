# ClubSync — On-Premise Deployment Runbook (SCC Server)

Target: a dedicated Ubuntu Server 22.04 LTS machine controlled by Saint Columban College,
running **PHP 8.3**, **PostgreSQL 15+** (installed locally on the same box), **Nginx**, and
three long-running background processes (queue worker, scheduler, Reverb websocket server).
Firebase Authentication and the Google Gemini API remain cloud dependencies — an internet
connection is still required for those, even though the app and database are fully on-prem.

This is the concrete, copy-paste version of the deployment plan in
[`TASK/Activity_Output.md`](../TASK/Activity_Output.md) §2. Ready-to-use config files referenced
below live in [`deploy/`](../deploy/).

---

## 0. Before you start

- [ ] A Firebase project exists with `FIREBASE_API_KEY`, `FIREBASE_PROJECT_ID`, `FIREBASE_DATABASE_URL`.
- [ ] A Google Gemini API key exists (`GEMINI_API_KEY`) with billing/credits active.
- [ ] A domain name (or internal hostname) is decided for `APP_URL`.
- [ ] SSH access to the target Ubuntu server, with sudo.

---

## 1. System preparation

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y software-properties-common curl git unzip

# Firewall: only SSH, HTTP, HTTPS
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
```

## 2. Install PHP 8.3

```bash
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.3-fpm php8.3-cli php8.3-common \
  php8.3-pgsql php8.3-mbstring php8.3-xml php8.3-curl \
  php8.3-gd php8.3-zip php8.3-bcmath php8.3-intl php8.3-opcache
```

`php8.3-pgsql` provides the `pdo_pgsql` driver the app requires. `php8.3-gd`/`php8.3-zip` are
needed for profile-photo processing and the Excel exports (`maatwebsite/excel`).

## 3. Install PostgreSQL locally

```bash
sudo apt install -y postgresql postgresql-contrib
sudo systemctl enable --now postgresql
```

Create the application database and a least-privilege role — see
[`deploy/postgres-setup.sql`](../deploy/postgres-setup.sql):

```bash
sudo -u postgres psql -f deploy/postgres-setup.sql
```

**Edit the generated password in that file before running it** — do not keep the placeholder.

Confirm PostgreSQL only listens locally (default is fine — do not open port 5432 on the
firewall; the app connects over `127.0.0.1` or the Unix socket, never from outside the box).

## 4. Install Node.js (for the asset build) and Composer

```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs

curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

## 5. Install Nginx

```bash
sudo apt install -y nginx
```

## 6. Deploy the application code

```bash
sudo mkdir -p /var/www/clubsync
sudo chown -R $USER:www-data /var/www/clubsync
git clone <your-repo-url> /var/www/clubsync
cd /var/www/clubsync
```

## 7. Configure `.env`

```bash
cp .env.example .env
php artisan key:generate
```

Then edit `.env` for production. The critical changes from the defaults:

| Variable | Set to |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` — **never `true` in production**; it leaks stack traces and `.env` values to any visitor who triggers an error. |
| `APP_URL` | `https://<your-scc-domain>` |
| `DB_CONNECTION` | `pgsql` |
| `DB_HOST` | `127.0.0.1` |
| `DB_PORT` | `5432` |
| `DB_DATABASE` | `clubsync` (or whatever you created in step 3) |
| `DB_USERNAME` / `DB_PASSWORD` | the role created in step 3 — **not** the Supabase credentials |
| `SESSION_SECURE_COOKIE` | `true` (HTTPS-only session cookie) |
| `FIREBASE_API_KEY`, `FIREBASE_PROJECT_ID`, `FIREBASE_DATABASE_URL` | your Firebase project |
| `GEMINI_API_KEY` | your Gemini key |
| `GEMINI_MODEL` | leave as `gemini-flash-latest` (tracks Google's current model; a pinned version can be silently retired — this already happened once this year) |
| `REVERB_APP_ID` / `REVERB_APP_KEY` / `REVERB_APP_SECRET` | **generate new random values** — do not reuse the ones from local development, they are dev-only secrets |
| `REVERB_HOST` | your domain (no scheme) |
| `REVERB_PORT` | `443` |
| `REVERB_SCHEME` | `https` |

## 8. Install dependencies and build assets

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

## 9. Run migrations and seed initial data

```bash
php artisan migrate --force
php artisan db:seed --class=SeedAdmin
php artisan storage:link
```

## 10. File permissions

```bash
sudo chown -R www-data:www-data /var/www/clubsync
sudo find /var/www/clubsync -type d -exec chmod 755 {} \;
sudo find /var/www/clubsync -type f -exec chmod 644 {} \;
sudo chmod -R 775 storage bootstrap/cache
```

## 11. Configure Nginx

Copy [`deploy/nginx-clubsync.conf`](../deploy/nginx-clubsync.conf) to
`/etc/nginx/sites-available/clubsync`, edit `server_name` and the SSL certificate paths, then:

```bash
sudo ln -s /etc/nginx/sites-available/clubsync /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

## 12. SSL certificate

If the server has a public domain name:

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d your-scc-domain.edu.ph
```

If this is an internal-only campus hostname with no public DNS, use the school's internal CA
or a self-signed certificate instead — HTTPS is still required (session cookies, the PWA
service worker, and Reverb's `wss://` all depend on it).

## 13. Background processes (queue worker + Reverb)

Copy the two systemd unit files:

```bash
sudo cp deploy/clubsync-queue.service /etc/systemd/system/
sudo cp deploy/clubsync-reverb.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now clubsync-queue
sudo systemctl enable --now clubsync-reverb
```

Verify both are running: `sudo systemctl status clubsync-queue clubsync-reverb`

## 14. Scheduler (cron)

The app schedules three jobs (see `routes/console.php`): a nightly semester-presence reminder,
a nightly club-violation detection scan (both 08:00), and the data-retention check (02:00).
Laravel's scheduler needs exactly one cron entry to drive all of them:

```bash
sudo crontab -u www-data -e
```

Add:

```
* * * * * cd /var/www/clubsync && php artisan schedule:run >> /dev/null 2>&1
```

## 15. Verification checklist

- [ ] `curl -I https://your-domain/ping` returns **204** (database reachable).
- [ ] Registration → login → dashboard works end-to-end for a test account.
- [ ] An officer can post an announcement and request an AI draft (confirms Gemini + queue worker).
- [ ] A real-time notification arrives without a page refresh (confirms Reverb + wss).
- [ ] `sudo systemctl status clubsync-queue clubsync-reverb` both show `active (running)`.
- [ ] The next day, confirm the 02:00/08:00 scheduled jobs ran: `grep schedule storage/logs/laravel.log`.
- [ ] `APP_DEBUG=false` — trigger a deliberate error and confirm it shows a generic error page, not a stack trace.

## 16. Backups

Even fully on-premise, keep an independent backup — a single-server failure should not mean
total data loss.

```bash
# /etc/cron.d/clubsync-backup
0 3 * * * postgres pg_dump -U clubsync_app clubsync | gzip > /var/backups/clubsync/db-$(date +\%F).sql.gz
```

Retain at least the audit-log minimum window (one academic year per POLICY.md) and copy backups
off the box periodically (external drive or the school's existing backup process).

## 17. Updating the deployed app

```bash
cd /var/www/clubsync
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
sudo systemctl restart clubsync-queue clubsync-reverb
sudo systemctl reload php8.3-fpm
```

---

## Notes specific to this app

- **Local disk storage, not S3.** Profile photos, approval letters, and semester archive exports
  are all written to local disk (`storage/app/public` and `storage/app/private`). This is exactly
  why a plain ephemeral-filesystem host (e.g. Heroku dynos) would silently lose these files — it
  is not a concern here since the disk is a real, persistent disk on hardware SCC owns.
- **Session driver is `file`.** Fine for a single server. If SCC ever adds a second app server
  behind a load balancer, sessions must move to `database` or `redis` first, or logins will
  randomly drop.
- **Internet is still required**, even fully on-prem: Firebase Authentication and the Gemini API
  are both external HTTPS calls. A campus internet outage will block login and AI drafting even
  though the database itself stays up (existing sessions and non-AI features keep working).

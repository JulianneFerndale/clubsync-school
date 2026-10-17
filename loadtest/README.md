# Load tests

Measures whether ClubSync meets its availability targets: 95% of pages in ≤ 3 s and
under 1% errors, at up to 800 concurrent users. Scripts run with
[k6](https://grafana.com/docs/k6/) (`winget install GrafanaLabs.k6`).

## Step 1 — baseline on the live site (`baseline-public.js`)

Finds how many requests per second the deployment serves before it slows past 3 s.

```powershell
k6 run -e BASE_URL=https://<service>.onrender.com loadtest/baseline-public.js
```

- **Public pages only:** it sends `GET /login` (70%) and `GET /ping` (30%). There are
  no logins, no forms, and nothing is written except the ordinary session each visitor gets.
- **Checks the target first:** it refuses to run unless `/login` returns the ClubSync
  page, so a mistyped `BASE_URL` can't load-test someone else's site.
- **Stops itself:** it ends once a fifth of requests fail or the slowest 5% pass 15 s,
  which keeps any slowdown for real users to a few minutes.
- **Reading the result:** each step (`rps_0_5`, `rps_1`, `rps_2`, …) gets its own
  `p(95)` and error rate. The last step still passing both checks is the app's capacity.
- **Adjustable:** `-e STEPS=1,2,4 -e STEP_SECONDS=60` changes the steps.

### If the site times out from your network

Some networks can't reach every Render edge server. For example, on 2026-09-23 this
network reached `216.24.57.1` but not `216.24.57.16`/`.18`. Check which one works, then
send traffic to it:

```powershell
Test-NetConnection 216.24.57.1 -Port 443
k6 run -e BASE_URL=https://<service>.onrender.com -e EDGE_IP=216.24.57.1 loadtest/baseline-public.js
```

## Steps 2–3 — 800 signed-in students on a rehearsal of the school server

`docker-compose.yml` runs the production image and PostgreSQL 17 side by side, the way
the SCC server would, filled with **fake students only**. No real student data is
copied (RA 10173), and nothing connects to Supabase, Firebase or Gemini.

```powershell
docker compose -f loadtest/docker-compose.yml up -d --build
docker compose -f loadtest/docker-compose.yml exec app php artisan db:seed --force
docker compose -f loadtest/docker-compose.yml exec app php artisan db:seed --class=LoadTestSeeder --force
docker compose -f loadtest/docker-compose.yml exec app php artisan loadtest:sessions --out=/loadtest-out/sessions.csv

k6 run -e SCENARIO=smoke loadtest/member-journeys.js     # 1-minute check first
k6 run -e SCENARIO=load  loadtest/member-journeys.js     # 800 students
```

- **The data:** `LoadTestSeeder` adds 1,000 fake students (42 of them club presidents)
  with memberships, announcements with likes and comments, activities with attendance,
  fees with payments and receipts, and notifications.
- **Signing in:** `loadtest:sessions` signs them in without Firebase and writes their
  session cookies to `loadtest/out/sessions.csv`.
- **Built-in safety:** both tools refuse to run unless `LOADTEST=true`, which only this
  compose file sets. The session command only ever signs in `@loadtest.invalid`
  accounts (see `tests/Feature/LoadTestToolsTest.php`).
- **How students behave:** `member-journeys.js` makes each virtual user one student who
  opens a page, reads it for 20–40 s, and moves on. Every 20th user is a president on
  the officer pages. `SCENARIO` picks `smoke`, `load`, `spike`, `stress` or `soak`,
  and `USERS` / `HOLD` size them.
- **Server size:** `APP_CPUS`, `APP_MEM`, `DB_CPUS`, `DB_MEM` and `PHP_FPM_WORKERS`
  stand in for the server's specs, e.g.
  `$env:APP_CPUS=6; docker compose -f loadtest/docker-compose.yml up -d`.
- **Cleanup:** `docker compose -f loadtest/docker-compose.yml down -v` removes the
  containers and the fake database.

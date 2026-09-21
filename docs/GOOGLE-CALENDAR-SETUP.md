# Google Calendar Sync — Setup Checklist

ClubSync copies scheduled records into the **personal Google Calendar of each SCC user they concern**:

| Record | Lands on the calendar of | Removed when |
|---|---|---|
| Confirmed club activity (internal meeting, or DSA-approved external) | the club's active members it targets by year level, its officers, its adviser | rejected, reset to pending, retargeted, member leaves, adviser replaced |
| Fee due date (all-day) | members who still owe the fee | the payment is recorded |
| Semester start / end (two all-day events) | every user | — (moved when the dates change) |

It works through one Google Cloud **service account with domain-wide delegation**, which writes events *as* each `@sccpag.edu.ph` user. Nothing is synced until the steps below are done and `GOOGLE_CALENDAR_ENABLED=true`.

---

## Part A — SCC Google Workspace super-admin

> **Security note:** with this delegation, whoever holds the JSON key can create, edit and delete calendar events of **any** `@sccpag.edu.ph` account. Treat the key like a password and scope the delegation exactly as below.

### A1. Google Cloud project
- [ ] Sign in to <https://console.cloud.google.com> with an SCC account and create (or pick) a project owned by the SCC organization, e.g. `scc-clubsync`.
- [ ] **APIs & Services → Library →** enable **Google Calendar API**.

### A2. Service account and key
- [ ] **IAM & Admin → Service Accounts → Create service account**, e.g. `clubsync-calendar`. Grant it **no** project roles.
- [ ] Open the service account and copy its **Unique ID**, a long number. This is the *Client ID* for step A3.
- [ ] **Keys → Add key → Create new key → JSON**. The file downloads once; keep it safe.
  - If key creation is blocked, the organization policy `iam.disableServiceAccountKeyCreation` is on. An Organization Policy Administrator must allow it for this project.

### A3. Domain-wide delegation
- [ ] Sign in to <https://admin.google.com> as a super-admin.
- [ ] **Security → Access and data control → API controls → Manage Domain Wide Delegation → Add new**.
  - **Client ID:** the Unique ID from A2
  - **OAuth scopes:** `https://www.googleapis.com/auth/calendar.events`. Only this scope; ClubSync needs nothing broader.
- [ ] **Authorize.** It can take from a few minutes up to 24 hours to take effect.

### A4. Calendar must be on for users
- [ ] **Apps → Google Workspace → Calendar → Service status** is **ON** for every org unit with students, advisers and staff. Users with Calendar turned off cannot receive events.

### A5. Hand-over
- [ ] Give the JSON key to the ClubSync maintainer through a restricted channel, such as a restricted Shared Drive file. **Not** by email or chat.

---

## Part B — ClubSync maintainer (on the server)

- [ ] Place the key at `storage/app/google/service-account.json`. It is already git-ignored by `storage/app/.gitignore`; never commit it. Alternatively, set `GOOGLE_SERVICE_ACCOUNT_JSON` to its absolute path.
- [ ] In `.env`:
  ```dotenv
  GOOGLE_CALENDAR_ENABLED=true
  GOOGLE_WORKSPACE_DOMAIN=sccpag.edu.ph
  GOOGLE_CALENDAR_TIMEZONE=Asia/Manila
  ```
- [ ] `php artisan config:clear && php artisan migrate`. This creates `google_calendar_events` and drops the unused `club_activities.google_event_id`.
- [ ] Keep a queue worker running (`php artisan queue:work`). Every sync happens in the background.
- [ ] Push existing records once: `php artisan google-calendar:sync`. Add `--only=activities`, `--only=fees` or `--only=semesters` to limit it. It is safe to re-run.
- [ ] Watch `storage/logs/laravel.log` for `Google Calendar sync failed` / `gave up` lines. A failure never blocks the action in ClubSync. Each user is retried up to 3 times, 5 and 10 minutes apart.

### Revoking
Delete the key under **IAM → Service Accounts → Keys**, and remove the Client ID under **Manage Domain Wide Delegation**. ClubSync stops syncing immediately. Set `GOOGLE_CALENDAR_ENABLED=false` to stop it from trying.

---

## Part C — End-to-end demonstration (after A and B)

Run against a **local throwaway database**, not Supabase:

1. Copy `.env` to `.env.demo-backup`. Point `DB_CONNECTION` at a local database, then run `php artisan migrate --seed`. Start the queue worker.
2. Using Playwright, register or sign in to ClubSync as `julianneferndale.flores@sccpag.edu.ph` and make the account an active member of a club.
3. As that club's officer, create an internal meeting and a fee. As the DSA, create a semester.
4. Open <https://calendar.google.com> in the same browser. The account owner types their own Google password; it is never stored in a script. Then confirm that:
   - the meeting appears at its date, time and venue
   - the fee appears as an all-day "Fee due: …" event
   - the semester's "begins" and "ends" events appear
5. Edit the meeting's venue and check the event updates. Mark the fee paid and check it disappears.
6. Restore `.env` from `.env.demo-backup`.

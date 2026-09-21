# ClubSync — Authentication Security Test Log

**Scope:** Login & Registration (email/password flow, POLICY.md compliance)
**Date:** 2026-09-07
**Time (initial vulnerability testing):** ~12:21 PM – 12:27 PM (UTC+8, Asia/Manila)
**Time (fixes applied & re-verified):** ~12:36 PM – 12:42 PM (UTC+8, Asia/Manila)
**Method:** Live functional/security testing with Playwright against `http://clubsyncing.test`, cross-checked against source (`LoginController`, `RegisterController`, `AuthManager`, `SessionManager`, `FirebaseTokenMiddleware`, `routes/web.php`, `.env`) and `workflows/POLICY.md`.
**Test data:** Accounts created during testing were removed from the Supabase dev DB afterward (see Cleanup).

---

## Verdict

Not approved as found. 1 authentication-bypass bug and 4 POLICY.md violations were confirmed live, then fixed and re-verified in this same session (see Fixes Applied). 3 lower-severity items remain open (see Deferred).

---

## Bugs & Violations Found

### 1. Institutional-email domain check bypassable — **High**
- **Where:** `app/Http/Controllers/Auth/RegisterController.php:38`
- **Bug:** validation rule was `'ends_with:sccpag.edu.ph'` (no leading `@`), so any domain merely *ending in* `sccpag.edu.ph` passed — e.g. `evilsccpag.edu.ph`, `notsccpag.edu.ph`.
- **Evidence:** registered live with `attacker@evilsccpag.edu.ph` — accepted, full account created, auto-enrolled as pending applicant in 2 real academic clubs (SYBORG, PSITS), logged in successfully.
- **Policy:** POLICY.md § Authentication & Passwords — "Registration requires an official SCC institutional email (`@sccpag.edu.ph`). No other domain is accepted."

### 2. Password complexity not enforced server-side — **High**
- **Where:** `RegisterController.php:39`
- **Bug:** validation was only `Password::min(8)` — no uppercase/number/symbol requirement.
- **Evidence:** registered and logged in live with password `password` (8 lowercase letters, no digit/symbol/uppercase) — accepted both times.
- **Policy:** POLICY.md — "minimum 8 characters, at least one uppercase letter, one number, one special character."

### 3. No rate limiting on `/login`, `/register`, `/forgot-password` — **High**
- **Where:** `routes/web.php` (no `throttle` middleware anywhere in the file).
- **Bug:** no brute-force/credential-stuffing protection.
- **Evidence:** fired 10 consecutive failed logins programmatically — every one returned `200` with the identical generic error, no lockout, no `429`, no CAPTCHA, no increasing delay.

### 4. Session inactivity timeout misconfigured — **Medium**
- **Where:** `.env` (`SESSION_LIFETIME`)
- **Bug:** set to `120` minutes instead of the required `30`. Compounded by `SessionManager::store()` stashing the Firebase refresh token in the plain session on *every* login (not just Remember-Me), letting `FirebaseTokenMiddleware` silently refresh the ID token for the full idle window.
- **Policy:** POLICY.md — "Session tokens expire after 30 minutes of inactivity."

### 5. Plaintext password held server-side during registration wizard — **Medium**
- **Where:** `RegisterController::storeStep1()` stores `register.password` in the session in plaintext; carried across the Department and Course steps until `storeCourse()` consumes it.
- **Bug:** `.env` had `SESSION_ENCRYPT=false`, so the plaintext password sat unencrypted in the session store (file/DB depending on environment) for as long as the session lived, including if the user abandoned the wizard mid-flow.

---

## Fixes Applied (this session)

| # | Fix | File(s) |
|---|---|---|
| 1 | `'ends_with:sccpag.edu.ph'` → `'ends_with:@sccpag.edu.ph'` | `app/Http/Controllers/Auth/RegisterController.php` |
| 2 | `Password::min(8)` → `Password::min(8)->mixedCase()->numbers()->symbols()` | `app/Http/Controllers/Auth/RegisterController.php` |
| 3 | Added `throttle:5,1` middleware around the login, register (all 3 steps), and forgot-password POST routes; GET routes left unthrottled | `routes/web.php` |
| 4 | `SESSION_LIFETIME` `120` → `30` | `.env`, `.env.example` |
| 5 | `SESSION_ENCRYPT` `false` → `true` (encrypts the whole session payload at rest, including the in-flight registration password) | `.env`, `.env.example` |

Config cache cleared (`php artisan config:clear`) after `.env` changes. `composer run test` re-run afterward — all 11 existing tests still pass, no regressions.

### Verification of fixes (live, post-fix)

- Re-attempted registration with `student2@evilsccpag.edu.ph` → rejected: *"Only Saint Columban College institutional emails (@sccpag.edu.ph) are accepted."*
- Re-attempted registration with password `password` → rejected: *"The password field must contain at least one uppercase and one lowercase letter."* A compliant password (`Str0ng!Pass`) was accepted and proceeded to the Department step.
- Fired 8 rapid login attempts → first 5 returned `200`, the 6th onward returned `429 Too Many Requests` with `Retry-After: 54`.
- Confirmed `.env` now reads `SESSION_LIFETIME=30`, `SESSION_ENCRYPT=true`; app still loads and logs in normally with encryption on (old unencrypted sessions are treated as fresh by Laravel, no errors).

---

## Deferred (found, not fixed — lower severity)

| # | Issue | Notes |
|---|---|---|
| 6 | `Secure` flag on the "Remember Me" refresh-token cookie vs. plain-HTTP local deployment | Correct for production; on `http://clubsyncing.test` (HTTP) the browser silently drops the cookie, so Remember-Me won't actually persist locally. No user-facing warning. |
| 7 | Registration leaks account existence | `email.unique` returns "An account with this email already exists" — minor enumeration vector. |
| 8 | Stack fingerprinting via response headers | `X-Powered-By: PHP/8.4.24`, `Server: nginx/1.25.2` disclosed on every response. |

---

## Controls Verified Passing (no action needed)

- CSRF tokens required and enforced on login/register/logout forms.
- Passwords hashed via Eloquent `'password' => 'hashed'` cast — never stored plaintext in the `users` table.
- Failed-login error is the exact POLICY-mandated generic text ("The email or password you entered is incorrect.") — no field-level user enumeration.
- Suspended-account message matches POLICY.md wording (verified in code).
- Stored-XSS attempt (`<script>alert(1)</script>` injected into first/last name at registration) rendered as escaped text (`&lt;script&gt;...`) everywhere it appeared (nav bar, dashboard) — Blade auto-escaping intact, no script execution.
- Pending-applicant academic-club gating works per POLICY: a newly registered member saw "Membership request pending" and a "Members only" content wall, not the club's activities/announcements.
- Course/department IDs are cross-validated server-side in `storeCourse()` — a course whose `department_id` doesn't match the session's selected department is rejected.
- `$request->session()->regenerate()` called on both login and registration (session-fixation mitigation).
- `forgot-password` always returns success regardless of whether the email exists (anti-enumeration).
- Session cookie is HttpOnly — not readable via `document.cookie` in the browser console.

---

## Cleanup

The test account created while proving the domain bypass (`attacker@evilsccpag.edu.ph`, `users.id = 54`) and its 2 `club_members` pending-application rows were deleted from the Supabase dev database after testing. Note: this account also created a real Firebase Authentication identity via `AuthManager::registerWithFirebase()`; the codebase has no `deleteUser()` helper, so that Firebase-side identity was **not** removed and should be deleted manually from the Firebase console if desired.

---

# Test Session 2 — RBAC, Auto-Enrollment, Role Change, Password Cooldown

**Date:** 2026-09-07
**Time:** ~1:10 PM – 1:25 PM (UTC+8, Asia/Manila)
**Method:** Live Playwright testing against `http://clubsyncing.test`, using the artisan fixtures `php artisan app:seed-admin` (DSA) and `php artisan app:seed-club-president` (Officer/President), plus a deterministically-derived password for one existing seeded Adviser account (`AdviserSeeder::passwordFor()` formula), confirmed by direct Firebase sign-in before use in the browser.

## Test 1 — Only `@sccpag.edu.ph` is accepted at registration

**Result: PASS.** Verified live against 6 domain variants in one batch:

| Email domain tried | Outcome |
|---|---|
| `newstudent@sccpag.edu.ph` (exact) | Accepted — proceeded to `/register/department` |
| `newstudent@gmail.com` | Rejected — "Only Saint Columban College institutional emails (@sccpag.edu.ph) are accepted." |
| `newstudent@evilsccpag.edu.ph` | Rejected (confirms the domain-bypass fix from Session 1 holds) |
| `newstudent@sccpag.edu.ph.evil.com` | Rejected |
| `newstudent@mail.sccpag.edu.ph` (subdomain) | Rejected |
| `newstudent@SCCPAG.EDU.PH` (uppercase) | Rejected — stayed on `/register` (confirmed via final redirect URL, not the error-banner text, which used different markup for this case) |

**Minor note (not a security issue):** the uppercase-domain case is rejected because `ends_with:@sccpag.edu.ph` is case-sensitive. Since email domains are case-insensitive per RFC, a legitimate SCC student typing `STUDENT@SCCPAG.EDU.PH` would be wrongly told their email isn't an institutional address. Low-priority fix: lowercase the email in a `prepareForValidation()` step before the `ends_with` check.

## Test 2 — Successful registration auto-enrolls into the course-mapped academic club

**Result: PASS.** Registered a new account (`newstudent@sccpag.edu.ph`) selecting Department = College of Criminology, Course = BS Criminology (id 27). Verified directly in the database after registration:

```
user id=56 course_id=27 department_id=3 role=member
club_member: club_id=19 club=Avant-Garde status=pending
total club_member rows: 1
```

Exactly one `club_members` row was created, correctly mapped to "Avant-Garde" (the club actually mapped to BS Criminology — confirmed via `Club::where('club_type','Academic')->whereHas('courses', ...)`), with `status = pending` — not active. Matches POLICY.md ("student is registered as a pending applicant ... Enrollment is NOT automatic"). Consistent with the SYBORG/PSITS result observed for a Computer-Science course in Session 1 — mapping is course-specific, not a blanket enrollment.

## Test 3 — Each role is routed to its correct dashboard on login

**Result: PASS for DSA and Adviser; PASS-BY-DESIGN for Officer (not a distinct "dashboard" route, see note); PASS for Member.**

| Role | Account used | Redirect after login |
|---|---|---|
| DSA | `dsa.admin@sccpag.edu.ph` | `/dsa/dashboard` ✓ (renders "Dashboard — DSA") |
| Adviser | `bangued.adviser@sccpag.edu.ph` | `/adviser/dashboard` ✓ |
| Officer (President) | `manchester@sccpag.edu.ph` | `/member/dashboard` — see note below |
| Member | newly registered `newstudent@sccpag.edu.ph` | `/member/dashboard` ✓ |

**Note on Officer routing:** `SessionManager::redirectByRole()` (`app/Services/SessionManager.php:43-51`) only special-cases `'dsa'` and `'adviser'`; every other role (`president`/`treasurer`/`mmo`/`member`) falls into the `default` branch and lands on `/member/dashboard`. This is intentional by the code comment ("Officers navigate like members; club-admin tools surface inside their own club") — verified live: the president's `/member/dashboard` shows an extra **"Clubs You Manage"** section (with an "OFFICER" badge) linking into `/member/clubs/{id}`, which itself shows a full **"Club Management"** panel (Activities, Announcements, Members, Officers, Fee Collection, Presence, Compliance, Churn Risk). So functionally the officer does reach role-appropriate tooling — it's just not a separately-branded "officer dashboard" landing page, even though a real `officer.dashboard` route exists at `/officer/dashboard` and is never used as a post-login redirect target. Flagging as a UX/naming inconsistency, not a broken feature.

## Test 4 — Member session gets HTTP 403 on an Officer/DSA-restricted route (Churn Risk Engine)

**Result: PASS.** As the Officer/President account, clicked the "Churn Risk" link (`/churn-risk`) from the club-management panel — loaded successfully (200, "Churn Risk Engine"). Logged out, logged in as the plain Member account, and navigated directly to `/churn-risk`: **received `403 Forbidden`** (page title "Forbidden"). Matches `routes/web.php:241` (`role:dsa,president,treasurer,mmo` middleware) and POLICY.md ("Churn Risk data ... must never be visible to Student Members ... return 403 on direct URL access"). No Churn Risk link is exposed anywhere in the plain member's UI either.

## Test 5 — Admin role change takes effect and is written to the audit log

**Original result (2026-09-07, Session 2, ~1:10–1:25 PM): NOT TESTABLE — feature did not exist.** Confirmed via three independent checks:
1. Code search: no controller anywhere in `app/Http/Controllers/Admin/` (only `DashboardController`, `ClubController`, `StorageController` exist) writes to `User->role`, and no `AuditLog::record()` call site relates to role changes.
2. `grep -rniE "role.*update|updateRole"` across `routes/web.php` and `app/Http/Controllers` returned no matches.
3. Live UI check: logged in as the DSA/Admin account and opened `/admin/dashboard` — the Admin Panel navigation only offers **Dashboard** and **Storage & Retention**; there is no Users/Roles management screen anywhere in the app.

The only places a user's `role` is ever set are at registration (`'member'`), the `AdviserSeeder` (`'adviser'`), and the two artisan seed commands (`'dsa'`, `'president'`) — all one-time provisioning, not a runtime admin action. This was a real feature gap, not a passed/failed security test.

**Update (2026-09-07, ~4:15 PM – 4:35 PM, UTC+8/Asia/Manila): feature built and verified live.** Implemented:
- `app/Http/Controllers/Admin/UserController.php` — `index()` (searchable, paginated user list) and `updateRole()` (validates the new role against the DB enum `dsa,adviser,president,treasurer,mmo,member`, blocks an admin from changing their own role, writes the change, then calls `AuditLog::record('admin.user.role_changed', $user, ['target_email', 'old_role', 'new_role'])`).
- Routes added under the existing `admin` (is_admin-gated) group: `GET /admin/users` (`admin.users.index`), `PATCH /admin/users/{user}/role` (`admin.users.role.update`).
- `resources/views/admin/users/index.blade.php` — search box, per-user role `<select>` + Update button with a JS confirm prompt, flash messages.
- Nav link ("Manage Users") added to `resources/views/layouts/app-admin.blade.php` (desktop sidebar + mobile drawer).
- Automated coverage: `tests/Feature/AdminRoleChangeTest.php` (4 tests — role change + audit write, invalid role rejected with no audit write, self-role-change blocked, non-admin session gets 403). Full suite: 15/15 passing.

**Live Playwright verification:**
1. Logged in as `dsa.admin@sccpag.edu.ph`, opened `/admin/users`, searched for a throwaway test user (`roletest@sccpag.edu.ph`, seeded `member`), changed role to `treasurer` via the dropdown, confirmed the JS dialog. UI showed `Role for Role TestUser changed from "member" to "treasurer".` and the badge updated to "Treasurer".
2. Verified via `tinker`: `users.role` = `treasurer`; a new `audit_logs` row (`action = admin.user.role_changed`, `resource_type = App\Models\User`, `resource_id` = the target's id) recorded `actor_id` = the DSA admin's own user id, `metadata = {"target_email":"roletest@sccpag.edu.ph","old_role":"member","new_role":"treasurer"}`, and a `hash` correctly chained to the prior row's `previous_hash` — matches POLICY.md's tamper-evident audit trail.
3. Searched the admin's own account on `/admin/users` — no role selector is shown, only "(your account)", confirming the self-role-change guard.
4. Logged out, logged in as the non-admin Officer/President account, navigated directly to `/admin/users` — **received `403 Forbidden`**, confirming `role:` isn't enough on its own here; the route is correctly gated by the same `admin` (is_admin) middleware as the rest of the Admin Panel.
5. Cleanup: the throwaway `roletest@sccpag.edu.ph` user was deleted after verification; its audit-log entry was left in place (append-only, and `resource_id` isn't a foreign key, so it stays valid history pointing at a now-deleted test record).

**Result: PASS (post-fix).** Role changes now take effect immediately and are written to the tamper-evident audit log, matching what Test 5 was originally checking for.

## Test 6 — Password update blocked within the 7-day cooldown, permitted after

**Original result (2026-09-07, Session 2, ~1:10–1:25 PM): NOT TESTABLE — feature did not exist.** Confirmed via three independent checks:
1. Code search: `grep -rn "last_password_changed" app/` shows the column is only ever set once, at adviser-seed time (`AdviserSeeder.php:103`); no controller reads it, computes a day-difference, or rejects a password change because of it.
2. Route search: `php artisan route:list --path=password` shows only the two guest `forgot-password` routes (`ForgotPasswordController@show`/`@send`) — there is no authenticated "change password" route at all.
3. Live UI check: logged in and opened `/profile` — the page only offers profile-photo upload; there is no password-change form or link anywhere in the authenticated app.

`ForgotPasswordController::send()` just triggers Firebase's e-mail-based reset flow and always returns success (good anti-enumeration behavior, see Session 1), but it never touches `last_password_changed` and has no cooldown check. This was a real feature gap: POLICY.md's 7-day password-change cooldown was entirely unenforced because the change-password feature it's supposed to gate hadn't been built yet.

**Update (2026-09-09, ~11:50 AM – 12:10 PM, UTC+8/Asia/Manila): feature built.** Implemented:
- `app/Models/User.php` — `PASSWORD_COOLDOWN_DAYS = 7`, `passwordChangeAvailableAt()` and `canChangePassword()`. Single source of truth shared by the controller, the view, and the tests. A null `last_password_changed` (never changed) permits the first change.
- `app/Http/Controllers/Auth/PasswordController.php` — authenticated change-password handler. Order of operations: **cooldown check first** (so a user inside the window always gets the date, not a complexity complaint) → validation (`min:8` + mixed case + number + symbol, matching registration) → re-authenticate the *current* password against Firebase → Firebase password update → write local hash + `last_password_changed = now()` → audit log.
- Route `POST /profile/password` (`profile.password.update`) inside the authenticated profile group, middleware `web → firebase.token → throttle:5,1` (the endpoint verifies a password against Firebase, so it is brute-forceable without the throttle).
- `resources/views/profile/show.blade.php` — a "Change Password" card. Inside the cooldown window the form is replaced by a notice naming the last change date and the next available date, satisfying POLICY's "Display the next available change date on rejection."
- `app/Services/AuthManager.php` — `updatePassword()` (previously orphaned, never called) now returns Firebase's response instead of `void`. Firebase revokes all existing tokens on a password change, so the fresh `idToken`/`refreshToken` are stored back into the session; the stale `clubsync_refresh` "remember me" cookie is force-forgotten because `FirebaseTokenMiddleware` prefers the cookie over the session and would otherwise refresh with a revoked token.
- Audit: writes `auth.password.changed` (actor = the user themselves) with the e-mail only — no password material in the metadata (asserted in a test).

**Verification — automated only (`tests/Feature/PasswordCooldownTest.php`, 7 tests, Firebase HTTP faked):**
1. Change allowed when `last_password_changed` is null → hash updated, timestamp set, audit entry written with no password material.
2. Change 3 days in → **rejected**, error text contains the exact next-available date, password unchanged, audit empty, and `Http::assertNothingSent()` confirms Firebase was never contacted.
3. Change 8 days in → allowed.
4. Boundary: exactly 7 days → allowed (`>=` comparison, not `>`).
5. Weak new password (`alllowercase`) → rejected, no cooldown started.
6. Wrong current password → rejected, password unchanged, **no cooldown started** (a failed attempt must not lock the user out for 7 days).
7. `GET /profile` inside the window renders the cooldown notice with the date and does *not* render the form.

Full suite after the change: **22/22 passing.** Route + middleware stack confirmed via `php artisan route:list --path=profile/password -v`.

**Live Playwright verification (2026-09-09, ~12:12 PM – 12:16 PM):** the Playwright MCP server would not connect this session (cached connection failure), so Playwright was driven directly from Node instead — the npx-cached `playwright` package pointed at the installed `chromium_headless_shell-1223` binary via `executablePath`. Logged in as `dsa.admin@sccpag.edu.ph`:
1. `/profile` renders the **Change Password** card with all three fields and the policy text ("...only be changed once every 7 days"). Screenshot: `cooldown-1-form.png`.
2. Wrong current password → `The password you entered is incorrect.` — the real Firebase re-auth rejected it, so the change never proceeded.
3. New password `alllowercase` → `The password field must contain at least one uppercase and one lowercase letter.`
4. `last_password_changed` then set to 3 days ago (simulating an active cooldown). `/profile` reloaded: **zero** `current_password` inputs on the page, replaced by the notice `You changed your password on September 6, 2026. You can change it again on September 13, 2026.`
5. **Bypass attempt** — injected and submitted a POST to `/profile/password` directly from the page (CSRF token read from the `<meta>` tag), as if the hidden form never existed. Rejected server-side: `You may only change your password once every 7 days. You can change it again on September 13, 2026.` This is the important one: the cooldown is enforced in the controller, not merely by hiding the form.
6. State restored — `last_password_changed` set back to `NULL`, and a Firebase sign-in confirmed `dsa.admin@sccpag.edu.ph` still uses its documented fixture password. `audit_logs` holds **0** `auth.password.changed` rows, confirming no password was actually changed at any point during testing.

No real account password was modified, so every fixture password in the table below remains valid.

**Known gap (not fixed, out of scope):** the cooldown is bypassable via the guest "Forgot Password" flow. That reset happens entirely inside Firebase via the e-mailed link; the app never observes it, so `last_password_changed` is not updated and the 7-day window is not applied. Closing it needs either the Firebase Admin SDK or an in-app reset-link handler.

## Test-account fixtures created this session

| Role | Email | Password | Origin |
|---|---|---|---|
| DSA | `dsa.admin@sccpag.edu.ph` | `Admin@ClubSync1` | `php artisan app:seed-admin` (idempotent, intended as a standing fixture — not deleted) |
| Officer/President | `manchester@sccpag.edu.ph` | `clubmanchester` | `php artisan app:seed-club-president` (idempotent, intended as a standing fixture — not deleted) |
| Adviser | `bangued.adviser@sccpag.edu.ph` | `Bangued334%` | Pre-existing `AdviserSeeder` account; password derived, not created — not deleted |
| Member | `newstudent@sccpag.edu.ph` | `Str0ng!Pass` | Registered live for Tests 1 & 2 — **deleted** from the DB after testing (along with its `club_members` row) |

---

# Test Session 3 — A.2 Event & Engagement Cluster

**Date:** 2026-09-09
**Time:** ~2:55 PM – 3:20 PM (UTC+8, Asia/Manila)
**Scope:** The five A.2 procedures in `docs/test.docx` (activity creation, venue double-booking, Reverb real-time broadcast, Churn Risk Engine, AI-assisted proposal).
**Method:** New automated suite `tests/Feature/EventEngagementTest.php` (16 tests, in-memory SQLite), plus live Playwright verification against `http://clubsyncing.test` signed in as the standing Officer/President fixture `manchester@sccpag.edu.ph` (Mentors Club). Cross-checked against `Officer\ActivityController`, `ChurnRiskService`, `ChurnRiskController`, `AcleMonitorService`, `GeminiService`, `App\Events\NotificationCreated`, `resources/js/app.js`, `routes/web.php`, `routes/channels.php`.

## Verdict

**2 of 5 pass.** Two procedures describe features that were never built (venue conflict detection, AI-assisted activity proposal), and one is only partially wired (real-time broadcast reaches the DSA, never club members). No regressions: the 22 pre-existing tests still pass (33 passed / 5 failed overall, the 5 failures being the new tests that document these gaps).

| # | Procedure | Result |
|---|---|---|
| 1 | Officer creates & schedules an activity with complete details | **PASS** |
| 2 | Overlapping venue/time booking is rejected | **FAIL** — no conflict check exists |
| 3 | New/updated activity broadcast in real time via Reverb | **FAIL** — broadcast reaches DSA only, never members; updates broadcast nothing |
| 4 | Churn Risk Engine scores members; Officer + DSA only | **PASS** |
| 5 | AI-assisted proposal checks venue availability before submission | **FAIL** — feature does not exist |

---

## Test 1 — Officer creates and schedules an activity with complete details

**Result: PASS.**

Automated (`P1`, 2 tests): posting a complete payload to `officer.activities.store` redirects to `officer.activities.index` with the `success` flash and no validation errors, and persists a `club_activities` row with the exact title, venue, date, `time_start`, `time_end`, `status = scheduled`, and `created_by` = the acting officer. The negative case (blank title/venue/date/time_start/time_end) returns validation errors on all five fields and writes no row.

Live: created **"QA Venue Test Alpha"** — 2026-11-18, SCC Gymnasium, 09:00–11:00, 40 participants — through `/officer/activities/create` as the Mentors Club president. Redirected to the calendar with *"Activity successfully scheduled!"*; the activity appeared on the calendar immediately. All four required detail fields (title, venue, date, time) are present on the form, with venue as a fixed `<select>` of six approved SCC locations.

## Test 2 — Overlapping venue/time booking is rejected

**Result: FAIL. The feature does not exist — venue double-booking is silently accepted.**

`ActivityController::store()` runs exactly one pre-insert conflict check (`ActivityController.php:155-166`): a **duplicate title + date within the same club**, which is non-blocking (a `duplicate_warning` the officer can bypass with a `confirmed` flag). There is no query anywhere in `store()` or `update()` that compares `venue` against other activities. Nothing constrains venue at the database level either — `club_activities` has no unique or exclusion constraint covering `(venue, date, time range)`.

The only venue-overlap logic in the codebase is `AcleMonitorService::build()` (`AcleMonitorService.php:40-48`), which flags same-venue/same-day/overlapping-time pairs — but it is **read-only reporting on the DSA's ACLE monitor screen, after the fact**, it is restricted to `activity_type = 'acle'`, and it never blocks a booking.

Automated (`P2`): with Club A holding SCC Gymnasium 09:00–11:00 on a given date, Club B's post for the same gym 10:00–12:00 was **accepted** — no `venue` error, row created. (The companion test confirming a legitimate back-to-back 11:00–13:00 booking is allowed passes, but only trivially, since no check runs at all.)

Live: with "QA Venue Test Alpha" already holding SCC Gymnasium 09:00–11:00 on 2026-11-18, submitted **"QA Venue Test Bravo"** for the *same venue and date* at 10:00–12:00 — a two-hour overlap. Result: *"Activity successfully scheduled!"*, no warning, no error. Both rows confirmed in the database:

```
id  9  QA Venue Test Alpha  2026-11-18  SCC Gymnasium  09:00:00-11:00:00
id 10  QA Venue Test Bravo  2026-11-18  SCC Gymnasium  10:00:00-12:00:00
```

Note this was a *same-club* collision, so it also slipped past the duplicate-title check (the titles differ). Cross-club collisions are equally unguarded — the duplicate check is scoped by `club_id`, so two different clubs can always book the same venue at the same hour.

**To fix:** add an overlap query in `store()` and `update()` — same `venue`, same `date`, `time_start < existing.time_end AND time_end > existing.time_start`, excluding the row being edited and any cancelled activity — and fail validation on `venue`. `AcleMonitorService`'s predicate is the correct comparison to reuse; it just needs to run before the insert and across all activity types, not only ACLE.

## Test 3 — New/updated activity broadcast in real time via Laravel Reverb

**Result: FAIL (infrastructure works; activities are not broadcast to members).**

The Reverb stack itself is correctly built, and this part passes:
- `App\Events\NotificationCreated` implements `ShouldBroadcastNow`, broadcasts on `PrivateChannel('notifications.{recipient_id}')` as `notification.created`, with an `{id, title, body, action_url, created_at, unread}` payload (verified by assertion).
- `routes/channels.php` authorizes the private channel to its owner only.
- `resources/js/app.js` subscribes via Laravel Echo + `pusher-js`, and renders a live toast plus an unread-badge update with no page refresh. It subscribes once and survives Turbo navigations.
- `ClubNotification::booted()` fires the broadcast on every notification insert.

What fails is the activity path feeding it. **No event is broadcast when an activity is created or updated for the people who would care — the club's members.** Specifically:
- Creating an **internal meeting** (auto-approved) broadcasts **nothing at all** — `store()` dispatches no notification on this branch.
- Creating an **approval-requiring** activity (ACLE, community involvement, etc.) dispatches `NotifyDsaOfActivitySubmission`, which notifies **DSA users only** with *"Activity awaiting approval"*. Instrumented run: exactly 1 broadcast, recipient = the DSA user; the club's active member received nothing.
- **`update()` broadcasts nothing** on an ordinary edit. The only exception is an edit that drops an already-approved activity back to pending, which re-notifies the DSA — again, never the members. So a rescheduled date, a changed venue, or a changed time never reaches a connected member in real time.

Automated (`P3`, 4 tests): the two tests asserting a club member receives a broadcast on activity create and on activity update both fail; the test asserting the DSA receives one for an approval-requiring create passes, as does the channel/payload wiring test.

Environment note: the Reverb server was not listening on `127.0.0.1:8080` during this session (it starts with `composer run dev`; the site is served by Herd separately). This is not the defect — the defect is upstream, in that no activity-lifecycle event is ever handed to Reverb for members. Standing up the server would not change the result.

**To fix:** dispatch notifications to the club's active members on activity create and on a material update (date, time, venue, or title change), which will flow through the existing `ClubNotification` → `NotificationCreated` → Reverb path with no new infrastructure. Optionally add a dedicated `ActivityScheduled` / `ActivityUpdated` broadcast on a club channel so calendar views can patch themselves live.

## Test 4 — Churn Risk Engine scores members and is Officer/DSA-only

**Result: PASS.**

Scoring — `ChurnRiskService::forClub()` returns one row per **active** member with a 0–100 `score`, a `low`/`medium`/`high` `tag` (thresholds 34 / 67), and a plain-language `factors` list. The score is built from four weighted, explainable signals summing to 100: attendance share (40), officer-marked semester presence (35), unpaid fees (15), and never-attended inactivity (10). Signals are gathered with a fixed number of grouped queries per club, not per member.

Automated (`P4`, 6 tests): with a completed activity, one member who attended and is marked `active`, and one who never attended and is marked `dropped`, every returned row carried an in-range integer score, a valid tag, and a non-empty factor list; the engaged member scored `0`/`low` and the disengaged one scored `high`, with the summary rolling up correctly to `{total: 2, high: 1, low: 1}`.

Access control — `routes/web.php:250` gates `/churn-risk` behind `role:dsa,president,treasurer,mmo`. Verified: Officer → 200, DSA → 200, **Member → 403, Adviser → 403**. `ChurnRiskController::index()` additionally scopes officers to clubs they hold a position in and **aborts 403** when an officer passes another club's `club_id` in the URL rather than silently ignoring it — also verified. This matches POLICY.md's requirement that churn data never reach students or advisers.

Live: `/churn-risk` as the Mentors Club president rendered the engine with the confidentiality banner (*"Confidential — engagement scores are for officers and the DSA only and must not be shared with members"*), the high/medium/low summary tiles, and a scored member row (score 12, tag "Low", factor *"No semester status recorded"*). The member-role 403 was independently confirmed in Session 2, Test 4.

## Test 5 — AI-assisted activity proposal checks venue availability before submission

**Result: FAIL. The feature does not exist.**

There is no AI-assisted activity/event proposal anywhere in the system, so there is nothing that could check venue availability before final submission.

1. **No route.** `php artisan route:list --path=officer/activities` returns 10 routes (index, store, create, show, update, attendance ×2, complete, edit, letter) — none AI-related, none an availability check. The only AI endpoint in the app is `officer.announcements.ai-draft`, which drafts **announcement** text.
2. **No service method.** `GeminiService` exposes exactly three generators — `draftAnnouncement()`, `draftClubNarrative()`, and `generateViolationStatement()`. None takes a venue, date, or time; none queries `club_activities`.
3. **No UI.** The live `/officer/activities/create` form contains zero occurrences of "AI", "Gemini", "generate", "draft", "suggest", or "assist", and zero occurrences of "available", "conflict", "booked", or "occupied". Its only action button is "Post Activity". There is no availability indicator of any kind next to the venue selector.

Automated (`P5`, 2 tests): the route-existence test finds no registered route name matching an AI proposal, and a POST to a plausible `/officer/activities/ai-proposal` endpoint returns **405** (no such route).

This procedure is a superset of Test 2 — it cannot pass until venue-conflict detection (Test 2) exists, since availability checking is the thing it is supposed to surface. Recommend building Test 2's conflict query first and exposing it as a live check on the create form, then layering the Gemini-assisted proposal on top.

## Cleanup

The two live test activities (`QA Venue Test Alpha` id 9, `QA Venue Test Bravo` id 10) were deleted from the dev database after verification — confirmed 0 rows remaining. No other data was created or modified; the Churn Risk and AI checks were read-only.

## Artifacts

- `tests/Feature/EventEngagementTest.php` — 16 tests (11 passing, 5 failing by design as gap documentation). The 5 failures are the executable specification for the three unbuilt/partial features; they will go green once those are implemented.


---

# Test Session 4 — Venue Conflict Check Built (A.2 Test 2)

**Date:** 2026-09-09
**Time:** ~3:25 PM – 3:40 PM (UTC+8, Asia/Manila)
**Scope:** Close the Session 3 Test 2 gap — reject a booking that overlaps an existing activity in the same venue and time slot.
**Method:** Implementation plus 13 automated tests, then live Playwright re-run of the exact scenario that proved the defect in Session 3.

## Result: PASS (post-fix). A.2 cluster moves from 40% to 60%.

## What was built

- **`app/Models/ClubActivity.php`**
  - `NON_EXCLUSIVE_VENUES = ['Others']` — "Others" is the catch-all on the SCC venue list, so two clubs picking it are not necessarily in the same room and must not block each other.
  - `scopeHoldingVenue($venue, $date, $ignoreId)` — activities still holding a venue on a date. **Cancelled and DSA-rejected activities release their slot**; scheduled, pending, approved and completed all keep it.
  - `findVenueConflict($venue, $date, $timeStart, $timeEnd, $ignoreId)` — returns the first overlapping activity, or null.
- **`app/Http/Controllers/Officer/ActivityController.php`**
  - `venueConflictError()` helper, wired into both `store()` and `update()`. On a clash it returns `back()->withInput()->withErrors(['venue' => ...])`, so the officer keeps everything they typed.
  - In `store()` the venue check runs **before** the existing duplicate-title warning, so a hard clash is never masked by a bypassable prompt.
  - In `update()` the activity is excluded from its own check via `$ignoreId`, so an edit that leaves the slot alone does not collide with itself.

## Two decisions worth recording

**1. Half-open overlap, compared in PHP.** The rule is `newStart < existingEnd && newEnd > existingStart`, so back-to-back bookings (09:00–11:00 then 11:00–13:00) do not collide.

The comparison deliberately happens in PHP on minute values rather than in SQL. The `time_start`/`time_end` columns are written as `'H:i'` by the officer form but normalised to `'H:i:s'` by PostgreSQL, and comparing those two shapes as strings silently breaks the exact-boundary case: `'09:00:00' > '09:00'` is true because the shorter string sorts first, so a booking ending exactly when another starts would be wrongly rejected. Doing it in SQL instead would need `substr(col::text, 1, 5)`, whose cast syntax differs between PostgreSQL and the SQLite used in tests. The candidate set is one venue on one date — a handful of rows — so this stays a single cheap query. Two regression tests cover both stored shapes.

**2. The conflict message respects the cross-club rule.** POLICY.md § Data Privacy: *"Cross-club data access is blocked at middleware level — users cannot access data from clubs they are not members or officers of."* So the message discloses the other club's activity **title only when the clash is with the officer's own club**:

- Own club: `SCC Gymnasium is already booked for "QA Conflict Alpha" from 09:00 to 11:00 on November 19, 2026. Choose a different venue or time.`
- Another club: `SCC Gymnasium is already booked by another club from 09:00 to 11:00 on November 19, 2026. Choose a different venue or time.`

The occupied time range is disclosed either way — an officer cannot pick a free slot without it, and it is the minimum needed to do so.

## Automated coverage (13 tests, all passing)

| Case | Expected | Result |
|---|---|---|
| Overlapping booking, different club | rejected | PASS |
| Conflict message hides another club's activity title | no leak | PASS |
| Conflict message names the officer's own club's activity | named | PASS |
| Back-to-back (existing 09:00–11:00, new 11:00–13:00) | allowed | PASS |
| Ends exactly when an existing one starts (new 07:00–09:00) | allowed | PASS |
| Stored times in `H:i:s` — overlap blocked, back-to-back allowed | both correct | PASS |
| Same slot, different venue | allowed | PASS |
| Same venue and slot, different date | allowed | PASS |
| `Others` venue, identical slot | allowed | PASS |
| Cancelled activity's slot reused | allowed | PASS |
| DSA-rejected activity's slot reused | allowed | PASS |
| Edit moving an activity onto an occupied slot | rejected | PASS |
| Edit keeping the activity's own slot | allowed | PASS |

Full suite after the change: **45 passed, 4 failed** — the 4 remaining failures are the untouched A.2 Test 3 (member broadcast) and Test 5 (AI proposal) gaps. No regressions; all 22 pre-existing tests still pass.

## Live verification (Playwright, `manchester@sccpag.edu.ph`, Mentors Club)

Re-ran the exact Session 3 scenario against `http://clubsyncing.test`:

1. Booked **QA Conflict Alpha** — SCC Gymnasium, 2026-11-19, 09:00–11:00 → accepted.
2. Booked **QA Conflict Bravo** — same venue and date, **10:00–12:00** → **rejected**. Stayed on `/officer/activities/create` showing *"SCC Gymnasium is already booked for "QA Conflict Alpha" from 09:00 to 11:00 on November 19, 2026. Choose a different venue or time."* All typed input was preserved on the redisplayed form.
3. Booked **QA Conflict Charlie** — same venue and date, **11:00–13:00** (back-to-back) → accepted.

Database confirmed only Alpha (09:00–11:00) and Charlie (11:00–13:00) persisted; Bravo was never written. This is the direct inverse of the Session 3 result, where the overlapping booking was accepted with a success message.

## Cleanup

`QA Conflict Alpha` (id 11) and `QA Conflict Charlie` (id 12) were deleted from the dev database after verification — 0 rows remaining.

## Still open from Session 3

- **Test 3** — activity create/update still broadcasts nothing to club members (DSA approval notices only).
- **Test 5** — the AI-assisted activity proposal still does not exist. Its venue-availability requirement is now unblocked: `ClubActivity::findVenueConflict()` is the check it needs to call.


---

# Test Session 5 — Member Real-Time Broadcast Built (A.2 Test 3)

**Date:** 2026-09-09
**Time:** ~3:45 PM – 4:05 PM (UTC+8, Asia/Manila)
**Scope:** Close the Session 3 Test 3 gap — a newly created or updated activity must reach connected club members in real time, with no manual refresh.
**Method:** Implementation plus 11 automated tests, then live end-to-end verification with a running Reverb server and a real signed-in browser session.

## Result: PASS (post-fix). A.2 cluster moves from 60% to 80%.

## What was built

- **`app/Jobs/NotifyMembersOfActivity.php`** (new) — notifies a club's active members that an activity was scheduled, updated, or approved. Each `ClubNotification` insert fires the existing `NotificationCreated` broadcast, so no new real-time infrastructure was needed; the gap was only that nothing on the activity path was feeding it. Uses `chunkById(200)`, matching `NotifyMembersOfAnnouncement`.
- **`Officer\ActivityController::store()`** — dispatches on create, but only for activities that are already confirmed (internal meetings). Approval-requiring activities still notify the DSA only.
- **`Officer\ActivityController::update()`** — dispatches only when a *material* field changed: title, date, time_start, time_end or venue. Reuses the `$changes` diff already computed for the activity change log.
- **`DSA\ActivityController::approve()`** — dispatches on approval. Without this, approval-requiring activities (ACLE, community involvement, campus resource use, other external — every type except internal meetings) would never reach members at all, and the procedure would still fail for the majority of activity types.

## Four decisions worth recording

**1. Members hear about confirmed activities only.** POLICY.md § Event Creation: activities needing DSA sign-off "require DSA approval before being considered confirmed." So the job returns early unless `approval_status` is `no_approval_needed` or `approved`. An ACLE activity announces itself when the DSA approves it, not when the officer submits it — otherwise members would be told to show up to something that might yet be rejected. The same guard means an edit that resets an approved activity back to `pending_approval` is silently held rather than announced.

**2. Only material edits notify.** A reworded description or a revised participant estimate does not interrupt anyone. A changed title, date, time or venue does. This is the difference between a useful notification and notification fatigue.

**3. Target year levels are honoured.** `club_activities.target_year_levels` was previously stored but never used for anything. The job now filters recipients by `users.year_level` against it. A member whose year level is not recorded is included regardless — dropping someone because their profile is incomplete would silently lose them a notice they should have had.

**4. The acting officer is excluded.** They just performed the change; a toast telling them about their own edit is noise. Passed as `$excludeUserId` from both controller dispatch points, and omitted on the DSA approval path (where the officer *should* hear about it).

## Automated coverage (11 tests, all passing)

| Case | Expected | Result |
|---|---|---|
| Member notified when an activity is created | broadcast | PASS |
| Member notified when an activity is updated | broadcast | PASS |
| DSA notified for an approval-requiring submission | broadcast | PASS |
| Member *not* notified while the activity is pending approval | held | PASS |
| Member notified the moment the DSA approves | broadcast | PASS |
| Acting officer not notified of their own change | silent | PASS |
| Immaterial edit (description only) | silent | PASS |
| Pending / inactive members | silent | PASS |
| Activity targeted at year 1 reaches the 1st year, not the 4th | filtered | PASS |
| Payload carries title, venue, time range and a club link | correct | PASS |
| Event is `ShouldBroadcastNow` on a private channel with a payload | correct | PASS |

Full suite after the change: **54 passed, 2 failed** — the 2 remaining failures are the untouched A.2 Test 5 (AI proposal) gap. No regressions; all 22 pre-existing tests still pass.

## Live verification (Reverb running, real browser session)

Unlike Session 3, the Reverb server was started for this test (`php artisan reverb:start --host=0.0.0.0 --port=8080`, confirmed listening on 127.0.0.1:8080).

Signed in as `manchester@sccpag.edu.ph` (Mentors Club, club id 9, and the club's only active member) and left the browser sitting on `/member/dashboard`, untouched:

1. **Echo connected and authorised.** `window.Echo.connector.pusher.connection.state` = `connected`; the private channel `private-notifications.2` reported `subscribed: true, subscriptionPending: false` — confirming `/broadcasting/auth` authenticates correctly against the Firebase session, not just that the socket opened.
2. **Create broadcast.** Inserted a real activity ("QA Realtime Broadcast Test", Dec 3 2026, 14:00–16:00, Function Hall) and dispatched the job with no exclusion, as if a different officer had scheduled it. With **no navigation and no reload**, both `.js-notif-badge` elements in the page chrome went from hidden to "1", and the toast host was created and populated. The stored notification read: `Mentors Club scheduled a new activity — "QA Realtime Broadcast Test" — on Dec 3, 2026, 14:00–16:00 at Function Hall.`
3. **Update broadcast, captured visually.** Moved the activity to Dec 4, 09:00–11:00, SCC Gymnasium and dispatched the UPDATED variant. Screenshot taken immediately shows the toast rendered in the top-right — *"Mentors Club updated an activity — "QA Realtime Broadcast Test" — now Dec 4, 2026, 09:00–11:00 at SCC Gymnasium"* — with the sidebar Notifications badge incremented to **2**. Again with no page reload. Screenshot: `reverb-toast-live.png`.

This is the direct inverse of the Session 3 finding, where an instrumented create produced exactly one broadcast addressed to the DSA and nothing at all for the club's member.

## Cleanup

The test activity (id 13) and its 2 notifications were deleted — 0 rows remaining for both. The Reverb server was stopped. The temporary bootstrap script and the screenshot were removed from the repository (the screenshot is kept outside it).

## Still open from Session 3

- **Test 5** — the AI-assisted activity proposal still does not exist. Its venue-availability requirement is unblocked: `ClubActivity::findVenueConflict()` (Session 4) is the check it needs to call.


---

# Test Session 6 — AI-Assisted Event Proposal Built (A.2 Test 5)

**Date:** 2026-09-09
**Time:** ~4:10 PM – 4:35 PM (UTC+8, Asia/Manila)
**Scope:** Close the last Session 3 gap — the AI-assisted event proposal must check venue availability before allowing final submission.
**Method:** Implementation plus 12 automated tests (Gemini HTTP faked), then live Playwright verification against the **real** Gemini API.

## Result: PASS (post-fix). A.2 cluster reaches 100% — all five procedures pass.

## What was built

- **`ClubActivity::VENUES`** — the SCC venue list moved out of `Officer\ActivityController` (where it was a private const) onto the model, so the form, the validator and the proposal endpoint all read one source of truth. POLICY.md § Event Creation requires venues to come from this list rather than free text.
- **`ClubActivity::venuesFreeAt($date, $start, $end, $except)`** — venues from that list with no conflicting booking in the slot. Skips the non-exclusive `Others`.
- **`GeminiService::draftActivityProposal()`** — returns `{description, purpose}` for an activity, requested as `responseMimeType: application/json` and parsed defensively (a malformed or non-JSON reply yields null rather than garbage in the form).
- **`Officer\ActivityController::aiProposal()`** — the endpoint, plus `GeminiService` injected into the controller.
- **Route** `POST /officer/activities/ai-proposal` (`officer.activities.ai-proposal`), inside the existing officer group and throttled `20,1` — each call can reach the Gemini API, so a stuck client must not burn quota. (Note: the older `officer.announcements.ai-draft` endpoint has no throttle; worth adding for consistency.)
- **UI** on `officer/activities/create.blade.php` — a panel below the venue selector with an optional context box, a check button, a pass/fail result, and clickable chips for the free alternative venues that re-run the check when picked.

## Three decisions worth recording

**1. The two halves are independent, and availability is the authoritative one.** The endpoint always runs the venue check; the Gemini draft is best-effort layered on top. If AI is switched off or the API call fails, the officer still gets a correct availability answer and writes the prose themselves. This is POLICY.md's "disable AI-assisted features gracefully, keep manual alternatives fully functional" — and it means the procedure's actual requirement (checking availability before submission) never depends on a third-party API being up. When there is no draft the response carries the POLICY-mandated `AI Unavailable — Manual Draft Required.` notice.

**2. The gate is server-side, not advisory.** `can_submit: false` from this endpoint is backed by `store()` refusing the same booking — the check built in Session 4. An officer who never calls the endpoint, or ignores its answer, still cannot book the slot. Both a test and a live bypass attempt confirm this; without it the "before allowing final submission" requirement would be decorative.

**3. Nothing is created from AI output.** The endpoint returns a draft; the officer reads it, edits it, and submits the form themselves — and non-internal activities then go to the DSA queue as before. That officer review is the human gate POLICY.md § AI & Automation requires ("No AI-generated content may be dispatched automatically ... This gate cannot be bypassed by any role"). A test asserts the endpoint persists zero activities. The client-side script also only fills `description`/`purpose` when they are still empty, so it never overwrites something the officer already wrote.

The cross-club redaction from Session 4 is carried through: a conflict with another club's activity returns `own_club: false` and `title: null`, and the message names the occupied window but not the activity.

## Automated coverage (12 tests, all passing)

| Case | Expected | Result |
|---|---|---|
| Endpoint is registered | exists | PASS |
| Conflicting slot | `venue_available: false`, `can_submit: false`, window reported | PASS |
| Free slot | `venue_available: true`, no conflict | PASS |
| Another club's conflict | title withheld, `own_club: false` | PASS |
| Alternative venues | free ones listed; conflicting and `Others` excluded | PASS |
| Draft returned, nothing persisted | draft present, 0 activities created | PASS |
| Gemini switched off | availability still correct, `AI Unavailable — Manual Draft Required.`, no HTTP sent | PASS |
| Gemini call returns 500 | draft null, availability still correct | PASS |
| Submission gate enforced server-side | `store()` rejects the same booking | PASS |
| Venue outside the SCC list | 422 | PASS |
| Member / Adviser session | 403 | PASS |

Full suite after the change: **66 passed, 0 failed.** No regressions; every pre-existing test still passes.

## Live verification (real Gemini API, Playwright)

`GEMINI_API_KEY` is configured in this environment, so the live run exercised the actual API rather than a fake.

Seeded a blocking booking owned by a **different** club — Society of Hospitality Management Students, SCC Gymnasium, 2026-12-10, 13:00–16:00 — then, as the Mentors Club president:

1. Filled the form for "Sportsfest Kickoff Rally", 2026-12-10, 14:00–15:00, SCC Gymnasium, with context "Opening rally for the intramurals season, with a short program and team introductions." and pressed **Check venue & draft proposal**.
2. **Conflict reported correctly and redacted:** *"✗ SCC Gymnasium is already booked by another club from 13:00 to 16:00 on December 10, 2026. Choose a different venue or time."* — the other club's activity title never appeared. Screenshot: `ai-proposal-conflict.png`.
3. **Alternatives offered:** Main AV Room, Open Court, Function Hall, Covered Court — correctly excluding the conflicting SCC Gymnasium and the non-exclusive `Others`.
4. **Real Gemini draft landed** in the Description and Purpose fields, e.g. *"The Sportsfest Kickoff Rally is a one-hour opening event at the SCC Gymnasium designed to officially launch the upcoming intramurals season..."* and a purpose paragraph phrased for an administrator reviewing it for approval.
5. **Clicked the "Covered Court" chip** — the venue switched and the check re-ran automatically: *"✓ Covered Court is free for that slot."* plus *"AI-assisted draft — review and edit before submitting."*
6. **Bypass attempt.** Set the venue back to SCC Gymnasium in the DOM and submitted the form directly, ignoring the panel entirely. Rejected server-side with the same conflict message, staying on the create page. This is the important one: the proposal's verdict is enforced by `store()`, not merely displayed.

## Cleanup

The blocking activity (id 14) was deleted — 0 rows remaining, and the rally was never created. The temporary bootstrap script and the screenshot were removed from the repository (the screenshot is kept outside it).

## A.2 cluster — final state

| # | Procedure | Result |
|---|---|---|
| 1 | Officer creates & schedules an activity with complete details | PASS |
| 2 | Overlapping venue/time booking is rejected | PASS (built Session 4) |
| 3 | New/updated activity broadcast in real time via Reverb | PASS (built Session 5) |
| 4 | Churn Risk Engine scores members; Officer + DSA only | PASS |
| 5 | AI-assisted proposal checks venue availability before submission | PASS (built Session 6) |

**Average Overall Rating: 100%.** `tests/Feature/EventEngagementTest.php` — 44 tests, all passing.


---

# Test Session 7 — A.3 Test 1: Gemini Announcement Draft

**Scope:** A.3 AI & Automation Cluster, Procedure 1 — "Tests the system if the Gemini API successfully generates an announcement draft based on the Officer's input prompt."
**Date:** 2026-09-13
**Time:** ~12:56 PM – 1:05 PM (UTC+8, Asia/Manila)
**Method:** Live end-to-end testing with Playwright against `http://clubsyncing.test` hitting the **real Gemini API** (`gemini-flash-latest`), signed in as the BYTE Org president (`delgado.president@sccpag.edu.ph`), cross-checked against `GeminiService::draftAnnouncement()`, `Officer\AnnouncementController::aiDraft()`, `resources/views/officer/announcements/create.blade.php` and `workflows/POLICY.md`.

## Result: PASS (post-fix)

Generation works end to end against the live API. Two defects were found while testing, fixed, and re-verified in the same session; a third change hardens the prompt against invented details.

## Live verification (real Gemini API)

1. **Title + description prompt.** Title *"BYTE Org 1st Semester General Assembly"*, description *"Remind all BSIT members about the first general assembly on September 20, 2026, 1:00 PM at the CCS AVR. Attendance is required, we will elect committee heads and present the semester activity calendar."* → `POST /officer/announcements/ai-draft` returned **200** in roughly 8 seconds with a 964-character, three-paragraph draft carrying every detail from the prompt (date, time, venue, the election of committee heads, the activity calendar) and none invented. The draft landed in the Content box and the hidden `ai_assisted` flag flipped to `1`.
2. **Human gate holds.** Saving it created announcement id 1 with `status = draft`, `ai_assisted = true`, `published_at = null`, shown as *"AI-assisted draft"* on the detail page. Nothing was published or dispatched by the generation itself (POLICY.md §AI & Automation).
3. **Description-only prompt** (no title) — *"Announce that BYTE Org is opening applications for the web development bootcamp, deadline September 25, slots are limited to 30 BSIT students."* → **failed before the fix** (see Defect 1), **200 + a usable three-paragraph draft after it**, which correctly expanded the club acronym to its full registered name.
4. **Letter type.** Title *"Request for Venue Use — CCS AVR"* with a description addressed to the Dean → a formal request letter, body only, no salutation or sign-off, as the prompt requires.
5. **Empty prompt** (no title, no description, posted directly past the client-side guard) → **422** with `{"error":"Enter a title or describe the topic before generating."}` and no Gemini call made.

## Defects found and fixed

| # | Defect | Severity | Fix |
|---|---|---|---|
| 1 | The AI panel invites *"Describe what this is about and let AI draft the content"* and its client-side guard accepts a title **or** a description, but `aiDraft()` validated `title` as `required`. A description-only prompt — the exact flow the panel advertises — was rejected. Worse, the endpoint answered with a **302 redirect to HTML** (the page's `fetch()` sends no `Accept: application/json` header, so `ValidationException` did not render as JSON), so `res.json()` threw and the officer was told *"Network error. Please try again."* — a message that points at the wrong cause entirely. | High | `title`/`context` are now `required_without` each other, and validation runs through `Validator::make()` so every failure returns `{error}` JSON with a 422, never a redirect. `GeminiService::draftAnnouncement()` builds the topic line from the description when no title is given. |
| 2 | AI announcement generation wrote **no audit-log entry**. `GenerateClubNarrativeJob` and `GenerateViolationStatementJob` both record theirs; this path recorded nothing, so officer-initiated AI content was invisible to the audit trail. POLICY.md §Data Privacy requires every AI content generation event to be logged with timestamp, actor UID and affected resource. | High | `aiDraft()` now records `ai.announcement.drafted` (and `ai.announcement.draft_failed` on failure) against the club, with actor id, type, title, whether a description was used, and the draft length. Verified live: rows 7–9 in `audit_logs`, actor 73, `App\Models\Club#16`, hash-chained. |
| 3 | `draftAnnouncement()` had no instruction against inventing facts — unlike `draftActivityProposal()` and `draftClubNarrative()`, which both carry one. The live letter draft invented a year (*"September 20, 2024"*) and a time range (*"1:00 PM to 5:00 PM"*) the officer never supplied. | Medium | Added the requirement *"Use ONLY the details given above. Do NOT invent dates, times, venues, fees, names, or figures that were not provided"*. Re-ran the identical letter prompt: the invented year and time are gone, only the given date remains. |

## Automated coverage (19 tests, all passing)

`tests/Feature/AiAutomationTest.php` — the Gemini transport is faked so the suite stays offline and deterministic:

- Draft generated from title + description; output trimmed and returned as JSON.
- The prompt actually sent carries the club name, the officer's title and the officer's own words.
- Description-only and title-only prompts both generate (regression guard for Defect 1).
- Empty prompt → 422 JSON with a readable message, not a redirect, and no API call.
- Letter type produces a formal-letter prompt; over-long description (>500 chars) is rejected before any call.
- Generation persists and publishes nothing; an accepted draft saves as `draft` + `ai_assisted`, never `published` (POLICY §AI & Automation).
- Success and failure are both written to the tamper-evident audit log with actor and club (POLICY §Data Privacy).
- Graceful degradation: no API key → 503 and no outbound call, the AI panel is not rendered, and the manual draft path still saves; an upstream 500 → a clean error, nothing saved.
- Access control: member and adviser sessions get 403, and an officer with no club is refused before any call.

`php artisan test` — **85 passed (291 assertions)**, no regressions in the other 66.

## Cleanup

Announcement id 1 (BYTE Org, *"BYTE Org 1st Semester General Assembly"*) was left in the dev database as evidence of the run; it is a draft and invisible to members. Remove with `App\Models\Announcement::find(1)->delete()` if not wanted. The three `audit_logs` rows stay — the log is append-only by design.

## Still open (deferred)

| # | Issue | Notes |
|---|---|---|
| 1 | `POST /officer/announcements/ai-draft` has no rate limit | Every call costs a Gemini request. An officer (or a stolen officer session) can hold the button down. A `throttle` on this route is worth adding alongside the auth-route throttling from Session 1. |
| 2 | Officer logins still land on the member dashboard | Carried over from A.1; reaching the draft page needs a direct URL or the officer sidebar. |

---

# Test Session 8 — A.3 Tests 2-5: Review Queue, Decisions, Compliance Scan, Post-Event Report

**Scope:** A.3 AI & Automation Cluster, Procedures 2-5 — adviser review routing and the publication gate; adviser approval/rejection status and notifications; the nightly compliance scan; and the post-event report's attendance and engagement data.
**Date:** 2026-09-13
**Time:** ~1:10 PM – 2:00 PM (UTC+8, Asia/Manila)
**Method:** Pest feature tests against in-memory SQLite (Gemini transport faked), then live end-to-end confirmation with Playwright against `http://clubsyncing.test` on the Supabase dev database — officer `delgado.president@sccpag.edu.ph` (BYTE Org), adviser `nagis.adviser@sccpag.edu.ph`, and `dsa.admin@sccpag.edu.ph`. Queued jobs were drained with `php artisan queue:work --stop-when-empty` since no worker runs outside `composer run dev`.

## Result: PASS (post-fix) — A.3 reaches 100%, all five procedures pass

The baseline run of the new tests passed 19 of 23 for Procedures 2-3 and failed exactly on the missing notifications; Procedures 4 and 5 exposed one content defect and one accuracy defect. All were fixed and re-verified in this session.

## Defects found and fixed

| # | Defect | Severity | Fix |
|---|---|---|---|
| 1 | Submitting a draft moved it to `pending_review` and it appeared in the adviser's queue, but **the adviser was never told**. Nothing in the system prompted a review; the draft simply sat there until the adviser happened to open the queue page. | High | `NotifyAdviserOfAnnouncementSubmission` job, dispatched from `Officer\AnnouncementController::submit()`. Names the officer, the title, the club, and flags an AI-assisted draft; links straight to the review page. |
| 2 | **Approval, revision requests and rejections notified the author of nothing.** Approval notified members only; `requestRevision()` flashed "The officer will be notified" to the adviser while creating no notification at all, and rejection was silent. An officer whose announcement was rejected had no way to learn it, or why, short of reopening the draft. | High | `NotifyOfficerOfAnnouncementDecision` job, dispatched from all three adviser actions. Carries the decision and the adviser's stated reason, and links to the announcement. |
| 3 | The DSA compliance notification read `"{club}: missing_ched_report."` — **a raw database slug**, with none of the specifics the scan had already worked out. This is the item the previous cycle's docx remark flagged as needing refinement before deployment. | Medium | Added `AiNotificationQueue::getGapLabelAttribute()` (a human-readable name per gap type). The notification is now titled `Compliance violation: Missing CHED report` with the scan's own description as the body, the Gemini prompt uses the label instead of the slug, and the three views that hand-rolled `ucfirst(str_replace(...))` now use the accessor. |
| 4 | **The post-event (CHED) report counted the club roster as participants.** `GenerateChedReportJob` listed every approved, semester-active member as an attendee and reported that count as "Number of Participants", ignoring the activity's attendance sheet entirely — the report certified attendance by people who may never have shown up, and carried no engagement data at all. | High | The job now builds its payload from `Attendance` rows for that activity (`time_in` present), through a testable `GenerateChedReportJob::reportData()`. Participants are the actual attendees with their time in/out; the roster is used only as the denominator for engagement: attendance rate against eligible members, turnout against the officer's estimate, absentees, and registered-but-never-timed-in. When no attendance was recorded at all the report says so instead of listing the roster. PDF view and XLSX export updated to match. |

## Automated coverage (49 new tests, all passing)

Added to `tests/Feature/AiAutomationTest.php` (68 tests in the file, covering all five A.3 procedures):

- **P2 (8 tests)** — submission routes the draft to the right adviser's queue and to no other adviser's; the adviser is notified; a pending draft is neither listed on the member bulletin nor reachable by direct URL; members are not notified on submission; an officer cannot smuggle `status`/`published_at` through their own save and no `officer.*publish*` route exists; a queued draft is locked from further officer edits; an officer of another club cannot submit it.
- **P3 (15 tests)** — approval sets `published`, reviewer and timestamps, and dispatches the member notification; active members are notified in-app and inactive ones are not; the author is notified on approval, revision and rejection, with the adviser's reason carried through; revision and rejection record notes, keep `published_at` null and never notify members; both require a reason; an adviser of another club is refused on all three actions; and the officer can revise and resubmit after a revision request.
- **P4 (17 tests)** — the scan is scheduled nightly at 08:00; each of the four checks fires on its own condition and stays quiet inside its grace period (14-day presence window, finalized CHED report, fresh approval request, officer records updated after October 1); a compliant club is untouched; a gap is not raised twice while one is open and is raised again once resolved; the AI statement is drafted but held `pending_review` with `sent_at` null; the scan still flags clubs with Gemini switched off; and the DSA notification names the exact requirement, never the slug.
- **P5 (9 tests)** — the participant count and list come from the attendance sheet, not the roster; time in/out are read off the record; engagement figures are computed against eligible members and the officer's estimate; a registered no-show is excluded; attendance from another activity is never borrowed; an unrecorded sheet is reported as such; semester-inactive members drop out of the denominator; activity details carry across; and completing an activity through the officer route generates the report and its files.

`php artisan test` — **134 passed (456 assertions)**, no regressions in the other 66.

## Live verification (Playwright, real data)

**P2 / P3 — announcement review chain (BYTE Org):**
1. As the officer, submitted announcement 1 (the AI-drafted assembly notice from Session 7). Status moved to `pending_review`; `NotifyAdviserOfAnnouncementSubmission` queued and, once drained, produced notification 15 to Patricia Nagis: *"Paulo Delgado submitted "BYTE Org 1st Semester General Assembly" from Building the Youth through Technology Education for your review (AI-assisted draft)."*
2. As the adviser, the Approval Queue showed **Pending Review 1** with the item tagged **✦ AI**. Opened it and pressed **Approve & Publish**: status `published`, `reviewed_by` 26, `published_at` 13:32:52. Two notifications followed — the member copy and *"Announcement approved — Your announcement ... is now on the club bulletin"* to the author.
3. As a member, the approved announcement appeared on the **Bulletin Board**.
4. Drafted and submitted a second announcement, then **while it was pending** checked the bulletin as a member: not listed, and `/member/bulletin/2` returned **404**, while announcement 1 stayed visible. This is the publication gate doing its job.
5. As the adviser, rejecting with an empty reason was refused; with the reason *"This duplicates the assembly notice already published this week..."* the status became `rejected`, `published_at` stayed null, and the author was notified: *"The adviser rejected "Duplicate Assembly Notice (test draft)": This duplicates..."*. The revision-request path was not re-run live — it shares this code path and is covered by six automated tests.

**P4 — nightly compliance scan:** the dev database had no failing condition today (no current semester, no completed ACLE/CI activity, no stale approval request, and the October 1 officer-records check does not apply before that date), so a controlled gap was seeded: a completed community-involvement activity for BYTE Org, finished 9 days ago with no CHED report. `DetectClubViolationsJob` raised exactly one violation — *"Building the Youth through Technology Education has completed activities pending a finalized CHED report for more than 7 days: Coastal Clean-Up Drive (compliance test)."* — status `pending_review`, `sent_at` null. The AI statement drafted from it named the club, the activity and the CHED requirement. The DSA queue rendered **"Building the Youth through Technology Education — Missing CHED report"**, and the detail page held the statement in an editable box behind **Approve & Send** — the human gate was not pressed. The DSA notification read *"Compliance violation: Missing CHED report"* with the full description.

**P5 — post-event report:** seeded an ACLE activity for BYTE Org and three temporary members (roster of 4), then through the officer UI checked **2 of the 4** in on the attendance page (Paulo Delgado, Geoffrey Hamar) and pressed **Mark as Completed**. The generated report read:

| Field | Value |
|---|---|
| Participants present | 2 (Paulo Delgado 1:49 PM, Geoffrey Hamar 1:49 PM) |
| Eligible club members | 4 |
| Absent | 2 |
| Attendance rate | 50.0% |
| Expected participants | 10 → 20.0% turnout |

The rendered PDF contains both attendees and **neither absentee** — before the fix it would have listed all four and reported 4 participants.

## Cleanup

Everything seeded for this session was removed: both test activities (15, 16) and their attendance rows, the generated CHED report row and its files under `reports/16`, the AI narrative draft and its adviser notification, the compliance violation and the two DSA notifications pointing at it, and the three temporary BYTE Org members. BYTE Org is back to 1 member and 0 activities; `ai_notification_queue` is empty again. Announcements 1 (published) and 2 (rejected) were kept as evidence of the review chain, along with their notifications; `audit_logs` rows stay by design.

## A.3 cluster — final state

| # | Procedure | Result |
|---|---|---|
| 1 | Gemini drafts an announcement from the officer's prompt | PASS (fixed Session 7) |
| 2 | Draft routed to the adviser queue; cannot be published without approval | PASS (adviser notification added) |
| 3 | Approval/rejection updates status and triggers the notification | PASS (officer notifications added) |
| 4 | Nightly compliance scan flags non-compliant clubs | PASS (notification content corrected) |
| 5 | Post-event report pulls attendance and engagement from the event record | PASS (rebuilt on the attendance record) |

**Average Overall Rating: 100%.** `tests/Feature/AiAutomationTest.php` — 68 tests, all passing.

## Still open (deferred)

| # | Issue | Notes |
|---|---|---|
| 1 | `POST /officer/announcements/ai-draft` has no rate limit | Carried from Session 7 — each press costs a Gemini request. |
| 2 | B.5 Usability remark still cites the compliance-notification wording | That defect is fixed (Session 8, defect 3); the non-functional remark in `docs/test.docx` was left alone as it sits outside the A.3 scope of this session. |
| 3 | The compliance scan is slow against the remote database | A full 42-club pass over the Supabase pooler ran for 20+ minutes and was stopped after it had produced the violation under test. Fine for a nightly 08:00 job, but worth an index/eager-load pass before the on-premise deployment.

---

# Test Session 9 — A.4 AI Narrative Report (baseline, before debugging)

**Scope:** New functional cluster A.4, added to `docs/test.docx`. The requirement under test: at the end of the semester the AI produces a narrative report that summarises every activity a club conducted, with each activity's details and its technical data (expected participants and actual attendees), delivered as a document to the DSA.
**Date:** 2026-09-14
**Time:** ~11:25 AM – 12:10 PM (UTC+8, Asia/Manila)
**Method:** Live end-to-end run with Playwright against `http://clubsyncing.test` on the Supabase dev database. Officer `delgado.president@sccpag.edu.ph` (BYTE Org, club 16), DSA `dsa.admin@sccpag.edu.ph`. Queue drained with `php artisan queue:work --stop-when-empty`. **Test only — no code was changed.**

## Result: 1 of 6 procedures pass (17%)

## Setup

12 temporary members were seeded into BYTE Org (users 100–111, `narrtest.member01…12@sccpag.edu.ph`, last names suffixed `(narrtest)`, no Firebase account) so attendance could vary per activity; the roster was 13 with the president. Five activities were then created through the officer form with every field filled, the three approval-requiring ones were approved through the DSA UI, attendance was checked in through the officer attendance page, and each was marked complete through **Mark as Completed**.

| ID | Activity | Type | Date / time | Venue | Expected | Checked in |
|---|---|---|---|---|---|---|
| 17 | BYTE Org 1st Semester General Assembly | internal_meeting | Sep 15, 08:00–11:00 | SCC Gymnasium | 13 | 11 |
| 18 | ACLE: Cybersecurity Awareness in Everyday Computing | acle | Sep 16, 13:00–16:00 | Main AV Room | 12 | 9 |
| 19 | Tech Week 2026 Planning Meeting | internal_meeting | Sep 17, 15:00–16:30 | Function Hall | 10 | 6 |
| 20 | Web Development Workshop: Building Your First Laravel App | campus_resource_use | Sep 18, 09:00–12:00 | Main AV Room | 12 | 10 |
| 21 | UI/UX Design Workshop with Figma | campus_resource_use | Sep 21, 13:00–16:00 | Function Hall | 10 | 7 |

"Workshop" has no activity type of its own; both workshops were filed as Campus Resource Use.

## What the system produced

Completion dispatched one `GenerateClubNarrativeJob` per activity, plus a `GenerateChedReportJob` for the ACLE. All jobs succeeded (0 failed). Gemini returned text for all five, giving narratives **6–10**. All five are `pending_review` with `ai_available = true`; the adviser (Patricia Nagis, user 26) received five "AI narrative ready for review" notifications; and there are five `ai.narrative.generated` audit rows. CHED report 2 was generated for activity 18: unfinalized, `narrative` null.

## Procedures

| # | Procedure | Result | Evidence |
|---|---|---|---|
| 1 | End of semester: one narrative report summarising every activity of the semester | **FAIL** | No semester-level generation exists. `Officer\ActivityController::complete()` dispatches `GenerateClubNarrativeJob` per activity; `routes/console.php` schedules no narrative job; the `semesters` table is empty. Five activities → five unrelated narratives. |
| 2 | Each activity's details (title, type, date, time, venue, description, purpose) | **FAIL** | `GeminiService::draftClubNarrative()` sends only title, date, venue and `purpose ?: description` (purpose is required, so description is never sent). Time and type are never sent. **Two of five drafts are truncated** but stored as successful: #8 is the whole string `"Building the Youth through Technology Education gathered at the Function Hall on September 17, 202"`; #10 is `"*Draft P2:* Putting theory into practice, the workshop centered on hands"`. |
| 3 | Technical data: expected participants and actual attendees from the attendance record | **FAIL** | The prompt carries neither `expected_participants` nor any attendance count. No draft states a number. |
| 4 | Grounded in recorded data only; no unverified outcomes | **FAIL** | Drafts turn the pre-event purpose into accomplished fact: #6 *"Attendees took part in a member vote to ratify the committee heads"*; #9 *"participants actively coded their way toward the day's main goal: creating a fully functional CRUD application"*. No invented names or figures. Same "report overstates the data" shape as Session 8, defect 4. |
| 5 | Held for adviser review; cannot be published without approval | **PASS** | All five `pending_review`, `published_at` null, adviser notified per draft. Signed in as a BYTE Org member, the **Bulletin Board** shows none of them (only the Session 7 announcement). |
| 6 | Produced as a document and handed over to the DSA | **FAIL** | No narrative export of any kind (no PDF/DOCX route, view or job). DSA nav has no narrative entry; `GET /dsa/narratives` → **404**; **CHED Reports** shows "No finalized reports yet." Approval (`Adviser\NarrativeController::approve`) publishes to the bulletin only. |

## Observations for the debugging pass (not yet investigated)

- **Truncation.** `draftClubNarrative()` returns `candidates.0.content.parts.0.text` without checking `finishReason` or joining multiple parts, and stores whatever comes back with `ai_available = true`. Whether that causes #8 and #10 is **unverified**.
- **Early completion.** An activity can be marked complete before its scheduled date: activities 17–21 are dated Sep 15–21 and were all completed on Sep 14. The narrative then describes an event that has not happened, in the past tense.
- **Adviser queue page.** `/adviser/narratives` was not opened live this session: the adviser's seeded password is not in the repo and deriving it was blocked. Queue membership was confirmed from the data (`club_id` 16, `pending_review`), which is exactly what `NarrativeController::index()` filters on.

## Fixtures left in place for debugging

These are deliberately **not** cleaned up yet: activities 17–21 and their attendance rows (43), narratives 6–10 and their adviser notifications, CHED report 2 and its files under `storage/app/private/reports/18`, member/DSA/officer notifications from the scheduling and approval jobs, and users 100–111 with their BYTE Org memberships. To remove the seeded members later:

```php
$ids = App\Models\User::where('email', 'like', 'narrtest.member%@sccpag.edu.ph')->pluck('id');
App\Models\Attendance::whereIn('user_id', $ids)->delete();
App\Models\ClubMember::whereIn('user_id', $ids)->delete();
App\Models\User::whereIn('id', $ids)->delete();
```

---

# Test Session 10 — A.4 AI Narrative Report: Debug and Fix (Procedures 1, 2, 3, 4, 6)

**Scope:** Fix the five A.4 procedures that failed in Session 9, then re-verify all six.
**Date:** 2026-09-14
**Time:** ~12:15 PM – 12:35 PM (UTC+8, Asia/Manila)
**Method:** Root-cause reproduction against the real Gemini API; Pest feature tests (in-memory SQLite, Gemini faked); live end-to-end run with Playwright on `http://clubsyncing.test` against the Supabase dev database. Accounts: DSA `dsa.admin@sccpag.edu.ph`; officers `delgado.president@…` (BYTE Org, club 16) and `agustin.president@…` (POLISAYS, club 2); adviser `bangued.adviser@sccpag.edu.ph` (POLISAYS — the one adviser with a documented fixture password, see Session 2).

## Result: PASS (post-fix). All six A.4 procedures pass; A.4 moves from 17% to 100%.

## Root cause of the truncated drafts (P2)

Replaying the Session 9 prompts for activities 19 and 21 eight times against the real API: 6 answers ended `finishReason: STOP`; **2 ended `MAX_TOKENS` after 19–20 output tokens, with `thoughtsTokenCount` 488–489**. The configured model alias `gemini-flash-latest` does not always honour `thinkingBudget: 0`, and thinking tokens count against `maxOutputTokens` (512), so the model could spend nearly the whole allowance thinking. `GeminiService` read `candidates.0.content.parts.0.text` without checking `finishReason`, so the fragment was stored as a successful draft. The same pattern (512 tokens, no finish check) was in all four generators.

## What was built / changed

| Area | Change |
|---|---|
| `GeminiService` | New `answerText()` helper, used by all generators: any `finishReason` other than `STOP` is a failed generation (logged; the caller falls back to "AI Unavailable — Manual Draft Required"); answer parts are joined and thought parts skipped. Output allowance raised from 512 to 2,048 (8,192 for the semester report). New `draftSemesterNarrative()`. `draftClubNarrative()` rewritten on the same record facts and accuracy rules. |
| `NarrativeReportData` (new) | Builds the facts a narrative is written from: per activity, the title, type label, date, time, venue, description, purpose, expected participants, attendees checked in (`time_in` present) and turnout; per semester, every **completed** activity dated inside the semester, plus totals (activities, types, check-ins, unique attendees, expected, turnout). Also `reviewWarnings()`: flags an activity the narrative never mentions, an activity paragraph that omits its attendance, and any number that does not appear in the records. |
| Accuracy rules (P4) | Both prompts label the description and purpose as "written by the officer before the activity" and forbid stating objectives as achieved, recalculating figures, or inventing names or results; outcomes may come only from an officer's post-activity report. |
| `semester_narrative_reports` (migration 000065) + `SemesterNarrativeReport` | One report per club per semester (unique). Stores a snapshot of the records, the AI draft, the adviser's edit, status `pending_review` → `submitted`, the PDF path, reviewer and timestamps. |
| `DispatchSemesterNarrativeReportsJob` (scheduled daily 06:00) + `GenerateSemesterNarrativeReportJob` (P1) | From the day after a semester's end date (14-day catch-up window), queues a report for each club with at least one completed activity in it. Drafts it, audits `ai.semester_report.generated`, and notifies the adviser — or, with no adviser, holds it and alerts the DSA. |
| `php artisan narrative-reports:generate {semester} [--club=] [--force]` | On-demand generation for a missed window or for testing; refuses a semester that has not ended unless `--force`. |
| DSA → **Semesters** page | Nothing in the app could create a semester before this (the table was empty), so there was no end date to trigger on. The DSA can now add and edit semesters (overlap and date-order validation, a single current semester, audited). |
| Adviser → **Semester Reports** (P5/P6 gate) | Review page showing the records beside the editable narrative, with the review warnings. **Save Changes** keeps it pending; **Approve & Submit to DSA** renders the PDF, marks it submitted, audits `semester_report.submitted` (with the count of unresolved warnings), and notifies every DSA user. |
| `reports/semester-narrative-pdf` (P6) | The document: header, I. Narrative, II. Summary of Activities (type, date/time, venue, expected, attended, turnout, totals), III. Activity Details (description, purpose, post-report if any), adviser sign-off, and a note on the data source and AI assistance. |
| DSA → **Semester Reports** | Lists submitted reports only (semester filter) with PDF download; unsubmitted reports return 404. |

## Automated coverage (52 new tests, all passing)

`tests/Feature/NarrativeReportTest.php`:
- **P1 (16)** — the daily schedule; nothing drafted while the semester runs; one report per club the day after it ends (and nothing on the end date itself, verified with time travel); last-day activities included; activities outside the semester, not completed, or from another club excluded; clubs with nothing completed skipped; no duplicate on re-run; catch-up window honoured; the command's `--force` guard; DSA semester create, overlap and date-order validation, single current semester, and DSA-only access.
- **P2 (7)** — every detail field reaches the prompt; ACLE named in full; `H:i:s` times handled; a `MAX_TOKENS` answer is not stored, for both the semester report and the bulletin recap; multi-part answers joined and thought parts skipped; an output allowance of at least 2,048.
- **P3 (6)** — attendance from the sheet, not the roster, with no-shows excluded; expected and actual attendance and the totals in the prompt; unique attendees counted once; "not recorded" stated plainly; the recap prompt carries attendance; a warning when the narrative omits an activity's attendance.
- **P4 (7)** — the before-the-activity labelling and the no-outcomes rules in both prompts; the post-activity report used as the outcome source; no warnings on a faithful narrative; unsupported figures flagged; an unmentioned activity flagged; one part of a two-part title accepted as a mention.
- **P5 (6)** — held `pending_review` with only the adviser notified and the generation audited; invisible to the DSA until submitted (list and direct PDF URL); another club's adviser, an officer and a member are refused; with no adviser, the report is held and the DSA alerted.
- **P6 (10)** — submit produces a real PDF (`%PDF`), marks the report submitted, notifies the DSA and audits it; the DSA list and the download; the document's content (narrative, types, times, venues, purposes, per-activity and total attendance, adviser); save does not submit; no edits after submission; no empty submission; the manual path when Gemini is unavailable; DSA-only list.

`php artisan test` — **186 passed (602 assertions)**; the other 134 are unchanged.

## Live verification

1. **Truncation fix, real API.** Replaced BYTE Org's five pre-fix recaps (#6–10) with fresh ones (#11–15): all complete, each stating type, date, time, venue and attendance (e.g. *"11 actual attendees, representing 84.6% of the 13 expected participants"*), with objectives phrased as plans. A further 12 calls on the two previously truncated activities: **12/12 complete**.
2. **Second club, through the UI.** As `agustin.president`, created the same five activity types for POLISAYS (ids 22–26: general assembly, ACLE, preparatory meeting, two workshops), DSA-approved the three that need it, recorded 12/13, 10/12, 7/9, 8/12 and 9/10 check-ins, and marked all five complete.
3. **Semester (P1).** As the DSA, the new **Semesters** page showed its empty state; added *1st Semester, A.Y. 2026–2027* (Aug 10 – Dec 18, 2026, not marked current). An overlapping semester was refused: *"These dates overlap 1st Semester, A.Y. 2026–2027 (Aug 10, 2026 – Dec 18, 2026)."*
4. **Generation (P1–P4).** `narrative-reports:generate 5` refused (*"ends on December 18, 2026 … pass --force"*); with `--club=16 --club=2 --force` it produced one report per club. Both covered all five activities in date order with every detail and figure matching the records (POLISAYS: 46 check-ins by 12 members against 56 expected, 82.1%; BYTE Org: 43 by 11 against 57, 75.4%), objectives as plans throughout, and **zero review warnings**. A first pass ended every activity paragraph with "No officer's post-activity report was submitted"; a rule against remarking on absent reports was added and both were regenerated (reports #3 and #4).
5. **Adviser gate (P5).** As `bangued.adviser`, **Semester Reports** listed the POLISAYS report (✦ AI draft); the review page showed the records. Saving an edit that added *"More than 150 students benefited"* raised **"These figures do not appear in the activity records: 150."** Restored the draft and pressed **Approve & Submit to DSA**: *"Report submitted to the DSA."*; the editor locked; the PDF downloaded.
6. **Hand-over to the DSA (P6).** Both DSA users were notified; the DSA **Semester Reports** page listed POLISAYS only (*"5 activities · 46 total check-ins · Submitted Sep 14, 2026 by Bianca Bangued"*); the PDF downloaded as `polisays-1st-semester-ay-2026-2027-narrative-report.pdf` (3 pages: narrative, summary table with totals 56 / 46 / 82.1%, activity details, sign-off). BYTE Org's still-pending report returned **404** at its DSA PDF URL. Audit trail: `semester.created`, `ai.semester_report.generated`, `semester_report.saved`, `semester_report.submitted` (`unresolved_warnings: 0`).

Browser console errors during the run were all pre-existing or deliberate: the missing `/images/scc-crest.png`, Reverb WebSocket failures (Reverb not running), and the two 404 probes.

## Data left in the dev database

- Semester 5 (*1st Semester, A.Y. 2026–2027*, not current). Marking it current also activates the nightly compliance scan's semester checks for every club, so it is left for the DSA to decide.
- Semester reports #3 (POLISAYS, submitted, PDF under `storage/app/private/reports/semester-narratives/3`) and #4 (BYTE Org, pending with adviser Nagis).
- POLISAYS activities 22–26 with 46 attendance rows, their recaps and the ACLE's CHED report; BYTE Org activities 17–21 and recaps #11–15; users 100–111 (`narrtest.member*`), now members of both clubs. Audit rows for the two replaced reports (#1, #2) remain, as the log is append-only.

## Still open

| # | Issue | Notes |
|---|---|---|
| 1 | An activity can be marked complete before its scheduled date | From Session 9; outside this scope. The report counts such an activity as completed. |
| 2 | The review check reads numerals only | A figure written in words ("one hundred fifty") is not flagged. |
| 3 | `POST /officer/announcements/ai-draft` has no rate limit | Carried from Session 7. |

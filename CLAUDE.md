# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

> Read [POLICY.md](POLICY.md) before implementing any feature that touches authentication, passwords, role access, announcements, event creation, AI outputs, notifications, or student data.

## Commands

```bash
# First-time setup
composer run setup
# Runs: composer install, .env generation, key:generate, migrate, npm install, npm run build

# Development (starts server + queue worker + Vite HMR concurrently)
composer run dev

# Run tests
composer run test
# Clears config cache, then runs Pest (tests use in-memory SQLite per phpunit.xml)

# Run a single test file
php artisan test tests/Feature/SomeTest.php

# Frontend build
npm run build

# Database
php artisan migrate
php artisan migrate:fresh --seed   # Full reset with seeders
php artisan db:seed --class=SeedAdmin
```

## Architecture

**Stack:** Laravel 13 / PHP 8.3 + Vue 3 + Inertia.js + Tailwind CSS 4 + Vite. Production runs on PostgreSQL (currently Supabase-hosted; on-premise PostgreSQL is the target for the SCC server deployment); tests use in-memory SQLite.

**Authentication:** Firebase REST API — no Laravel Auth guards. The flow is:
1. `AuthManager::loginWithFirebase()` calls Firebase identitytoolkit REST endpoint
2. Firebase UID is matched to a `users` record in the PostgreSQL database
3. `SessionManager::store()` saves `idToken`, `uid`, `role`, `user_id` to Laravel session
4. `firebase.token` middleware auto-refreshes expired tokens on each request

"Continue with Google" (`Auth\GoogleController`) is a second way into the same flow. `GoogleSignIn` runs the OAuth code flow and accepts only `hd=sccpag.edu.ph` accounts, before Firebase is called. `AuthManager::signInWithGoogle()` (Firebase `accounts:signInWithIdp`) then returns Firebase tokens. `SessionManager::signIn()` opens the session for both methods. Setup: `docs/GOOGLE-SIGN-IN-SETUP.md`.

**Middleware stack (routes/web.php):**
- `firebase.token` — validates/refreshes Firebase JWT in session
- `guest.firebase` — blocks authenticated users from auth pages
- `role:{role}` — enforces RBAC; the alias `officer` covers every officer position (`User::OFFICER_ROLES`)

**Roles:** `admin`, `dsa`, `vpa`, `adviser`, `member`, and the officer positions `president`, `vice_president`, `secretary`, `treasurer`, `auditor`, `mmo_internal`, `mmo_external` (plus the legacy `mmo`). An officer position is always held in one club (`club_officers`), given by an administrator on Manage Users. Labels and lists live on `App\Models\User` (`POSITION_LABELS`, `OFFICER_ROLES`, `STUDENT_ROLES`).

Advisers are employees: they sign in at `/login/adviser` (adviser number = `users.employee_number`) and sign up at `/register/adviser` (`Auth\AdviserRegisterController`), picking a club with no adviser as a request (`users.requested_club_id`) that an administrator confirms on Manage Users. One adviser per club, one club per adviser.

`vpa` is the Vice President for Administration (run by the VPA or the SSG): it gives the last approval of the room booking letter every new activity (internal meetings included) is sent with, after the club adviser (`ClubActivity` `approval_status`: `pending_approval` (adviser) → `pending_vpa` → `approved`). Create the account with `php artisan app:create-vpa --email=…`. The letter is composed by `App\Services\BookingLetter` (PDF via dompdf); signatures by `App\Services\SignatureStorage`.

Controllers are grouped by role under `app/Http/Controllers/{Admin,DSA,VPA,Adviser,Officer,Member}/`.

**Services (`app/Services/`):**
- `AuthManager` — Firebase REST auth wrapper (login, register, password reset, token refresh)
- `FirebaseService` — Firebase Realtime Database reads/writes
- `SessionManager` — Laravel session storage for auth state
- `GeminiService` — Google Gemini API for AI-assisted announcement drafting

**Frontend:** Blade templates in `resources/views/` are layout shells only. Vue 3 components via Inertia handle interactive pages. The Inertia entry point is `resources/js/app.js`. Controllers pass data to Vue components as Inertia props — not as Blade variables.

**Queue:** `QUEUE_CONNECTION=database`. The `ai_notification_queue` table handles async Gemini AI jobs. Run `php artisan queue:listen` during development (included in `composer run dev`).

**External services required (set in `.env`):**
- `FIREBASE_API_KEY`, `FIREBASE_PROJECT_ID`, `FIREBASE_DATABASE_URL` — auth + Realtime DB
- `GEMINI_API_KEY` — AI announcement drafting

## Key Conventions

- Route names follow the pattern `{role}.{resource}.{action}` (e.g., `officer.events.store`)
- Role-specific views live under `resources/views/{role}/`
- Migrations are in `database/migrations/`; seeders cover departments, admin user, and sample clubs
- Tests use an in-memory SQLite DB (configured in `phpunit.xml`) — no Firebase calls in tests; mock `AuthManager` and `FirebaseService`

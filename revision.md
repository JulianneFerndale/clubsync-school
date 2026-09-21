# ClubSync — SRS & SDD Revision Brief

Plug this file into Claude alongside the existing **Software Requirements Specification
(SRS)** and **Software Design Document (SDD)** and ask it to revise both to match the current
implementation. Two themes drive the changes: the backend database moved from **MySQL to
cloud PostgreSQL**, and the app was **optimised for speed and user experience**. The sections
below are the authoritative list of changes; treat them as the source of truth where they
conflict with the older documents.

## 1. Database Migration: MySQL → Supabase (PostgreSQL, Tokyo)

- The database is now **Supabase PostgreSQL** in the **`ap-northeast-1` (Tokyo)** region;
  MySQL is fully retired.
- The app connects through the **Supabase session pooler on port 5432** — not the transaction
  pooler (6543), which breaks migrations and prepared statements.
- Connections use TLS (`sslmode`) and a short connect timeout to fail fast rather than hang.
- **PostgreSQL behavioural differences handled in code:**
  - Search/filter uses `LOWER(col) LIKE ?` (Postgres `LIKE` is case-sensitive; MySQL was not).
  - Login email lookups are normalised to lowercase before matching.
  - All tables live in the `public` schema (a stray `laravel.` prefix was removed).
- **Free-tier storage (~500 MB)** is now a hard constraint — see §4.
- SRS: DB technology, hosting region, data locality, storage limits. SDD: data-tier and
  deployment diagrams, connection topology, migration/compatibility notes.

## 2. Architecture & Stack Corrections

- Stack: **Laravel + Blade + Tailwind CSS 4 + Vite**; the UI is **server-rendered Blade** —
  remove any SRS/SDD claim that it is an Inertia/Vue single-page application.
- Client interactivity is **vanilla JavaScript** plus **Hotwire Turbo** (new) for no-reload
  navigation. It is a **Progressive Web App** with an offline page and service worker.
- **Authentication** is Firebase (REST) with state in the Laravel session (no Laravel
  auth-guard login). A **refresh token is now persisted** so active users are not logged out
  when the 1-hour Firebase token expires.
- **Real-time:** database-backed queue + **Laravel Reverb** WebSocket server for broadcasting.

## 3. New / Revised Features

- **AI Club Narratives (Google Gemini):** on activity completion a post-event narrative is
  drafted automatically, held in a **human review queue (club adviser)**, and published only
  after approval. No AI output is ever auto-published.
- **Tamper-evident Audit Log:** append-only, SHA-256 **hash-chained** records of AI generation
  and approval actions, retained for **at least one academic year**.
- **Churn Risk Engine:** per-member engagement score (attendance, semester presence, unpaid
  fees, inactivity) tagged Low/Medium/High. **DSA sees all clubs; officers only their own;
  members and advisers are blocked (HTTP 403).**
- **Real-time notifications:** Reverb broadcasts a private per-user event; the bell badge and a
  toast update live, with no refresh.
- **Officer panel:** the home lists the officer's joined academic & non-academic clubs (mirrors
  the member home); officers can enroll in non-academic clubs; create-activity / draft-post
  tools live on the per-club dashboard.

## 4. Data Retention & Storage (new non-functional requirement)

- A daily scheduled job measures database size against the **500 MB** cap and **alerts admins**
  at 75% and 90% usage.
- It **auto-prunes only transient data** (expired sessions, old read notifications).
- Admins can **archive an old semester**: attendance and fee-payment records export to Excel,
  then prune. The current + previous semester and audit logs (≥ 1 year) are always retained.

## 5. User-Experience & Performance Optimisation

- **Session and cache drivers moved from `database` to `file`**, removing a remote round-trip
  to Tokyo on every request — light-page response fell from ~1.8 s warm / ~25 s cold to under
  0.1 s.
- **N+1 query fixes** via eager loading; duplicate per-page count queries consolidated.
- **Hotwire Turbo** gives SPA-style navigation (no full reloads or white flashes); file
  downloads opt out and download natively.
- **Global loading UI:** a branded splash during login/registration, a **cancellable dialog**
  during data-saving actions, and a navigation spinner, all with graceful exception handling.

## 6. Security & Privacy Constraints (reaffirm in both documents)

- Institutional-email-only registration; minimum-PII collection; per-club access isolation.
- Churn-risk data must never be visible to students; AI output always passes a human gate.
- Every data mutation, approval, and AI event is recorded in the tamper-evident audit log.

## 7. Revision Checklist

**SRS** — revise: technology stack; database and hosting region; storage/capacity limits; the
data-retention non-functional requirement; the real-time notification requirement; the
churn-engine access rules; and the AI human-review constraint.

**SDD** — revise: architecture and deployment diagrams (Supabase / Reverb / Turbo); the data
model and schema/region notes; the authentication and refresh-token flow; the broadcasting
sequence; the retention/archival job design; and the caching/session strategy.

## 8. Notes for the Reviser

- Keep requirement IDs stable; mark changed items as *Revised* and new ones as *Added*.
- Where the old documents describe MySQL, an Inertia/Vue SPA, or database-backed sessions,
  treat those passages as superseded by this brief.

---

# Revision 2 — Governance, Advisers, Integrations & Design System

This second brief supersedes Revision 1 where they conflict. It covers the role/governance
rework, the adviser accounts, the two external integrations, and the design-system change.
Apply these to the **SRS**, **SDD**, and **Software Project Management Plan (SPMP)**.

## 9. Terminology & Institutional Naming (Revised)

- "DSA" now expands to **Director of Student Affairs** (previously "Dean of Student Affairs").
  Update every occurrence in the SRS/SDD glossary, actor lists, use-case narratives, and UI
  copy. The role, permissions, and route prefix (`dsa.*`) are unchanged — only the title.

## 10. Membership Approval Authority (Revised — major)

- **Club member approval is the club Adviser's responsibility, not the DSA's.** The pipeline is:
  a student applies (status `pending`) → **club officers submit** the applicant → **the club
  adviser approves or rejects** (with remarks). The DSA view is now **read-only oversight**.
- Academic-club enrollment is **no longer automatic**. Course selection at registration creates
  a **pending application** to the mapped academic club; officers accept, adviser approves, then
  it becomes active. Until approved, the applicant cannot see club content. (Revision 1 / SRS
  "Auto-Enrollment" requirement is superseded.)
- Officer-record management (add/edit/archive club officers) is restricted to the **Adviser**;
  officers have read-only visibility of the officer roster.
- SRS: revise the membership, registration/auto-enrollment, and approval-authority requirements;
  reassign the "approve member" use case from DSA to Adviser. SDD: update the state model for
  `ClubMember` (`status`, `registration_status`, `submitted_by`, `approved_by`) and the
  officer/adviser controllers and route guards.

## 11. Officer Experience Rework (Revised — major)

- Officers **navigate as members by default**: on login they land on the member dashboard and
  use the member navigation — no standing admin sidebar.
- Administrative tools are **contextual to the club they run**. The member dashboard lists
  "Clubs You Manage"; opening that club reveals a **Club Management** panel (Activities,
  Announcements, Members, Officers, Fee Collection, Presence, Compliance, Churn Risk). The system
  verifies officer membership **per-club** before exposing any admin control.
- SRS: rewrite the Officer actor's navigation/entry-point requirements. SDD: the member route
  group now admits the officer roles; club-show controllers are officer-aware; the officer admin
  pages become the in-club admin context reached from the club page.

## 12. Adviser Accounts & Identity (Added)

- Advisers (faculty) are identified by a unique **employee number** (`employee_number`, format
  `SCC-ADV-####`), **not** an EDP number; `edp_number` is now nullable for adviser accounts.
- **42 adviser accounts** (one per club) are provisioned via a seeder that creates a Firebase
  Authentication account and links each adviser to a club (`clubs.adviser_id`). Passwords are
  deterministic (derived from the institutional email) and policy-compliant.
- SRS: add the Adviser identity attribute and the "one adviser per club" rule. SDD: `users`
  schema change (`employee_number`, nullable `edp_number`) and the seeding/provisioning design.

## 13. External Integrations (Added — gated on credentials)

- **Google Calendar sync (revised — per user):** confirmed activities, fee due dates and
  semester start/end dates are copied into the **personal Google Calendar of each user they
  concern** (activity → targeted active members, officers, adviser; fee → members who still
  owe it; semester → everyone). **Queued background jobs** act as each user through a
  **service account with domain-wide delegation** (dependency-free JWT → Calendar v3 REST,
  scope `calendar.events`). A `google_calendar_events` table tracks each copy so edits
  update it and lost access removes it. `php artisan google-calendar:sync` backfills.
  The database remains the source of truth; sync failures are logged and retried, never fatal.
- **Institutional email (Gmail) copies:** publishing a club announcement notifies members
  **in-app AND emails a copy** through the configured institutional mailer. The in-system
  notification remains the system of record; email is an added, best-effort channel and never
  carries auto-generated AI content.
- Both features are **feature-flagged off** until Workspace credentials are configured.
- SRS: add the calendar-sync and email-notification requirements (with the "in-system is
  authoritative" and "no auto-AI email" constraints). SDD: the service/job design, the
  service-account auth flow, and the config/feature-flag gating. POLICY.md updated accordingly.

## 14. Design System (Revised)

- **Primary brand color is `#07873e`** (replacing the former dark green), applied app-wide.
- The **left sidebar and the main top bar share a single CSS custom property** (`--cs-chrome`,
  a slightly-dark-gray `#333a45`); changing that one token recolors both surfaces together.
- SDD/UI spec: document the design tokens (`--color-primary`, `--cs-chrome`) as the single
  source of truth for brand and chrome colors.

## 15. DSA Clubs Oversight (Added)

- The DSA Clubs view has **compliance sub-tabs**: a club is **Compliant** once it has submitted
  a finalized CHED/post-activity report; otherwise **Non-Compliant**. A **"Clubs with Pending
  Members"** tab lists clubs with applicants awaiting adviser action. Each club card shows a
  compliance badge and a pending-applicant count.
- SRS: add the compliance-monitoring requirement. SDD: the `Club` scopes (`compliant`,
  `nonCompliant`, `pendingApplications`) and the tab-count queries.

## 16. SPMP Impact (Added — new document dimension)

- **Configuration/onboarding tasks (deployment prerequisites):**
  1. Delete the orphaned `amilio.adviser@sccpag.edu.ph` Firebase account and re-run the adviser
     seeder to reach 42/42 advisers.
  2. Provision a Google Workspace **service-account key** + super-admin domain-wide delegation
     (scope `calendar.events`) to enable per-user calendar sync; set `GOOGLE_CALENDAR_ENABLED`
     and run `php artisan google-calendar:sync`. Full checklist: `docs/GOOGLE-CALENDAR-SETUP.md`.
  3. Switch the mailer to institutional Gmail SMTP (App Password) to enable announcement emails.
- **Dependencies / external services:** Supabase (Tokyo), Firebase Auth + RTDB, Google Gemini,
  Laravel Reverb, and (new) Google Workspace Calendar + Gmail. Track credential ownership and
  rotation in the SPMP resource plan.
- **Risks:** free-tier storage cap (mitigated by retention/archival job); Tokyo DB latency
  (mitigated by file cache/session + N+1 fixes); external-integration credential availability
  (mitigated by feature flags so the app runs without them); single-region hosting.
- **Schedule/traceability:** keep requirement IDs stable; tag items in this brief as *Revised*
  or *Added*; the amilio account and the two integrations are the only items with an external
  dependency still outstanding.

## 17. Revision 2 Checklist

**SRS** — DSA title; membership approval authority (DSA → Adviser); academic enrollment
(auto → pending/approval); officer navigation model; adviser identity + one-per-club rule;
calendar-sync and email-notification requirements; DSA compliance monitoring.

**SDD** — `ClubMember`/`ClubOfficer`/`users` schema and state changes; officer-aware routing and
controllers; the calendar/email service + queued-job designs; feature-flag gating; design tokens.

**SPMP** — onboarding/config prerequisites; external-service dependency and credential plan;
updated risk register; traceability of Revised/Added items.

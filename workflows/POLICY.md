# POLICY.md

Business rules and institutional policies for ClubSync. These govern behavior that must be enforced in code — not UI preferences.

---

## Authentication & Passwords

- Registration requires an official SCC institutional email (`@sccpag.edu.ph`). No other domain is accepted.
- Password complexity: minimum 8 characters, at least one uppercase letter, one number, one special character.
- **Time-gated password changes:** a user may change their password at most once every 7 calendar days. Enforce server-side using the `lastPasswordChanged` timestamp. Display the next available change date on rejection.
- On failed login, always return a generic message ("The email or password you entered is incorrect.") — never reveal which field is wrong.
- Suspended accounts return: "Your account has been suspended. Please contact the Director of Student Affairs for assistance."
- Session tokens expire after 30 minutes of inactivity. "Remember Me" stores the refresh token in an HttpOnly cookie with a 30-day expiry.

## Registration & Academic Club Enrollment

- **The email address is settled before the wizard moves on.** It is checked against both stores at the email field — the `users` table and Firebase Authentication — and registration does not proceed to department or course selection until it is clear. A taken address is reported against the email field itself, never as a failure at the end of the flow.
- Firebase will not answer "is this address taken?" while email enumeration protection is on, so the Firebase account is **created at the email step**, and its refusal to create a duplicate is the check.
- An address held in Firebase with no `users` row is an unfinished registration. If the password entered opens that account it is the same person returning, and they resume and complete it; otherwise the address belongs to someone else and they are told to sign in or reset their password.
- On course selection during registration, the student is registered as a **pending applicant** to the academic club(s) mapped to their course. Enrollment is NOT automatic and NOT active on sign-up.
- Academic club membership is confirmed through the same two-step approval as non-academic clubs: the **club officers accept** the applicant, then the **club adviser gives final approval**. The Director of Student Affairs does not approve club membership.
- Until approved, a pending academic applicant may see the club exists but cannot access its content (activities, announcements). Approval sets the membership to active.
- The course→academic-club mapping is still the trigger that creates the pending application; the student does not need to search for or manually join their academic club.
- **A signed-in student with no course on record is held at the course step.** Without a course they belong to no academic club and never will, so the system requires the selection before any other page is reachable, then enrols them exactly as registration would. Advisers, the DSA and administrators are exempt — they have no course by nature.
- If no club is mapped to a course, complete registration, flag the student's profile as `Pending Club Assignment`, and alert the DSA Admin. Inform the student their club assignment is pending review. The flag is re-evaluated whenever the student's course is set, so mapping the course later clears it without anyone doing so by hand.

## Role-Based Access & Churn Risk

- **Churn Risk data (engagement scores and risk tags) must never be visible to Student Members** — not even in aggregate or anonymized form. Enforce at both the UI and the server route/middleware level; return 403 on direct URL access.
- Churn risk is scoped: Club Officers see only their own club's members; DSA sees all clubs.
- Club Advisers do not have access to the Churn Risk Engine.
- An officer who belongs to multiple clubs gets elevated privileges per-club, not globally.
- **Officers serve for a school year.** The summer semester closes a school year and the next one begins, and every club appoints a new set of officers for it. A club that has appointed nobody since the school year began is asked to — its adviser is notified once per year and the DSA can see which clubs have not answered.
- **Turnover never revokes access on a date.** The sitting officers keep working until the club actually replaces them, so no club is left unable to run itself because a term rolled over. Who holds a post is changed by the adviser appointing someone, not by the calendar.

## Announcements & Content Workflow

- Club officers publish their own club's announcements and letters directly. There is no approval queue in front of an announcement, and no role reviews one before it reaches members.
- An announcement is `Published` the moment the officer posts it. Only its author may edit it afterwards, and an edit does not re-notify members.
- Where an announcement was AI-drafted, the officer reading and posting it **is** the human gate §AI & Automation requires: the draft is returned to the officer's form and nothing is published until they press Publish.
- Adviser review applies to **activities**, not announcements — see §Event Creation.

## Event Creation

- Internal meetings remain direct — no approval queue, no letter drafting required. On submission, an internal meeting is immediately saved as `Scheduled` / `no_approval_needed`.
- Activities classified as ACLE, community involvement, campus resource use, or other external activity require **club Adviser** approval before being considered confirmed. These are saved as `pending_approval` and the club's adviser is notified; an approval letter may be attached. The adviser approves, or rejects with remarks (a reason is required). Approval is the moment the activity becomes real to members: they are notified and it is copied onto their calendars.
- An adviser decides only for their own club. The DSA retains oversight — monitoring, filtering and reporting on every club's activities — but does not approve or reject them.
- If no active Adviser is assigned to a club, the activity still waits in `pending_approval` and the DSA Admin is alerted, so nothing is left waiting on nobody.
- Required fields: Event/Activity Title, Date, Venue (from predefined SCC venue list), Purpose/Objectives, Expected Number of Participants. Block submission if any are missing.
- Venues must come from the SCC-configured list (Gymnasium, Main AV Room, Open Court, Function Hall, Covered Court, etc.) — not free-text.
- Officers may edit any activity at any time, including ones that have already taken place. If an edit is made to an activity that was previously `approved` and is not an internal meeting, its status resets to `pending_approval` and the adviser is notified again.
- A new activity must start in the future. The date alone is not enough: an activity dated today but starting at a time that has already passed is refused, and the officer is told to pick a later time or a future date. This applies to creating an activity, not to editing one that has already happened.

## AI & Automation

- **No AI-generated content may be dispatched automatically.** All AI outputs (compliance notifications, post-event narratives) must pass through a human review queue before being sent or published. This gate cannot be bypassed by any role.
- If the Gemini API is unavailable: disable AI-assisted features gracefully, keep manual alternatives fully functional, and flag AI-generated drafts as "AI Unavailable — Manual Draft Required."

## Data Privacy (RA 10173)

- Collect only the minimum PII necessary (name, institutional email, EDP number, course, financial records).
- All PII must be encrypted at rest.
- Cross-club data access is blocked at middleware level — users cannot access data from clubs they are not members or officers of.
- Every data modification, approval action, login attempt, and AI content generation event must be written to the tamper-resistant audit log with timestamp, actor UID, and affected resource.
- Audit logs are retained for a minimum of one complete academic year.

## Private Messaging (Chat)

Any active user may message any other active user one-to-one. Because conversations are personal data under RA 10173, these rules apply on top of §Data Privacy:

- **Participants only.** A conversation can be read or written only by its two participants. No role can open another person's conversation, including DSA, advisers, officers and administrators. A non-participant gets 404 (not 403), so the conversation's existence is not disclosed either.
- **Notice before use.** Chat stays closed until the user acknowledges the in-app privacy notice. The notice says what is collected, why, who can read it, how long it is kept, and the user's rights. The acknowledgement date is stored and audit-logged. If chat's data handling changes, the notice text must change with it.
- **Encrypted at rest; no plaintext copies.** Message bodies are encrypted in the database. Message text must never be copied into notifications, email, broadcasts (WebSocket payloads carry ids and counts only), application logs, the audit log, reports, the Churn Risk Engine or any AI prompt.
- **Minimal directory.** People search requires at least 2 characters, returns at most 10 matches, and discloses only name, a coarse role label (Student / Club Officer / Club Adviser / Student Affairs Office) and profile photo. It never discloses email, EDP number, course, year level, officer position or admin status. Suspended users are not listed and cannot be messaged.
- **Audit metadata, not content.** Sending, unsending, deleting a conversation, blocking/unblocking and acknowledging the notice are audit-logged with actor, affected resource and counterpart. The text itself is never logged.
- **User control.** A sender may unsend any of their own messages at any time; the text is destroyed, not hidden. Deleting a conversation removes it from that user's view only; once both participants have deleted it, the messages are permanently erased. A user may block another, which stops both sides sending.
- **Retention.** Messages are permanently deleted after `CHAT_RETENTION_DAYS` (default 365, floor 30) by the daily `chat:prune` job.
- **No offline copies.** The service worker never caches `/chat` pages or responses, and chat pages opt out of Turbo's snapshot cache, so a conversation is not left readable on a shared computer after logout.
- **Not a notification channel.** New messages raise the unread chat badge and a content-free toast. They do not create entries in the notifications table and are never emailed.

## Notifications

- The in-system notification is the system of record: every notification MUST be persisted in-app so offline users see it on next login. No SMS integrations.
- Institutional email (the SCC Google Workspace / Gmail, `@sccpag.edu.ph`) MAY be used as an **added** delivery channel that mirrors an in-system notification — never as a replacement for it, and never as the only place a notification exists. Email is best-effort: a failed send must not block the in-app notification or the originating action.
- Email copies are limited to institutional recipients and institutional content (e.g. published club announcements). No third-party/personal email domains are used for outbound notifications.
- **No AI-generated content may be emailed automatically.** AI-drafted compliance/violation notices remain in-system only and still require human review before dispatch (see Announcements & Content Workflow); the email channel does not bypass that gate.
- Dismissal state is stored in the database; dismissed notifications must not reappear.

## External Integrations

- Scheduled records MAY be copied into the personal Google Workspace calendar of each user they concern — and only those users, so the copy never widens who can see a club's data:
  - **Confirmed club activities** (internal meetings, and DSA-approved external activities) → the club's active members the activity targets by year level, its officers, and its adviser. Pending applicants do not receive them.
  - **Fee due dates** → only the members who still owe that fee. The copy is removed once the payment is recorded. Individual payment status is never written to a calendar.
  - **Semester start and end dates** → every user.
- Sync runs in the background via queued jobs and a service account with domain-wide delegation. It must never block or roll back the originating action, and a sync failure is logged, not surfaced as a user error.
- The database remains the source of truth; calendar copies are a convenience mirror. When a record stops applying to a user (activity rejected, reset to pending or retargeted; member leaves; fee paid; adviser replaced), their copy is removed.
- Only institutional Google Workspace credentials are used, and only `@sccpag.edu.ph` accounts are synced. Suspended users receive no new copies. The service-account key is never committed to the repository.

## Club Registry

- **A school year runs first semester → second semester → summer**, and a semester records which term it is and which school year it belongs to. A school year is named for the calendar year it starts in: August 2026 through the summer that follows is `2026-2027`.
- Exactly one semester is flagged current. Everything scoped to a semester — member presence, narrative reports, officer turnover, the archival guard — reads the school year from it, and fails closed when none is flagged rather than guessing.
- There are exactly 19 academic clubs (course-mapped) and 23 non-academic clubs (open to all students), for 42 total. The registry is maintained by the Administrator and is the authoritative source for auto-enrollment and the AI chatbot's recommendations.
- The four SCC colleges for enrollment routing: College of Teacher Education, Arts and Sciences; College of Business Education (CBE); College of Criminology (COC); College of Computing Studies (CCS).

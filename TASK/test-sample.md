# ClubSync – Club Management System
## Software Testing Documentation
### Test Plan · Functional & Non-Functional Requirements · Test Case Specification
### College of Computing Studies | Saint Columban College, Pagadian City

**Group Members:**
- Roxanne Kate Pagsiat Ulgasan – Project Manager
- Julianne Ferndale Torino Flores – System Developer
- Jynie Frasco Catacutan – System Quality Assurance

**Adviser:** Mr. Hardy Facunla, LPT, MLIS, RL
**Client:** Mr. Rosebelt Silao Lomocso, LPT, MPA, CSASS

---

## Persons Involved (Legend)

The following personnel are involved in the testing of **every** test item listed in this document. For brevity, the *Persons Involved* column uses the abbreviations below.

| Abbreviation | Role |
|---|---|
| **SD** | System Developer / Programmer (Julianne Ferndale T. Flores) |
| **QA** | Tester / Quality Assurance (Jynie F. Catacutan) |
| **PM** | Project Manager / Analyzer–Designer (Roxanne Kate P. Ulgasan) |
| **ADV** | Capstone Adviser (Mr. Hardy Facunla) |
| **CL** | Client / User Representatives (DSA, Advisers, Club Officers, Student Members) |
| **TP** | Technical Panel |

---

# 1. Test Plan

The following test plan enumerates the interfaces and features of the ClubSync Club Management System to be tested, grouped by user role. Access to each feature is governed by Firebase Authentication and role-based access control (RBAC) middleware.

## 1.1 Shared — Authentication & Access

| ITEM # | TEST ITEMS | FEATURES TO BE TESTED | PERSONS INVOLVED |
|---|---|---|---|
| 1 | Landing / Start Interface | Displays the ClubSync entry screen with options to log in or register. | SD, QA, PM, ADV, CL, TP |
| 2 | Login Interface | Requires a valid registered SCC institutional email and password; returns a generic error on failure and blocks suspended accounts. | SD, QA, PM, ADV, CL, TP |
| 3 | Registration — Step 1 (Personal Details) | Accepts first/last name, EDP number, **school year level**, SCC email, password, mobile number, optional photo, and Terms acceptance. | SD, QA, PM, ADV, CL, TP |
| 4 | Registration — Step 2 (Department) | Allows the student to select their department. | SD, QA, PM, ADV, CL, TP |
| 5 | Registration — Step 3 (Course) | Allows course selection, creates the account, and registers a pending application to the mapped academic club(s). | SD, QA, PM, ADV, CL, TP |
| 6 | Forgot Password Interface | Sends a password-reset request through Firebase for a valid institutional email. | SD, QA, PM, ADV, CL, TP |
| 7 | Terms & Conditions Interface | Displays the Terms and Conditions and Data Privacy notice. | SD, QA, PM, ADV, CL, TP |
| 8 | Profile Interface | Displays the user's profile and allows updating or removing the profile photo. | SD, QA, PM, ADV, CL, TP |
| 9 | Logout | Allows users to securely end their session and protect their account. | SD, QA, PM, ADV, CL, TP |
| 10 | Offline / Connectivity Handling | Serves the offline fallback page and connection banner when the network or database is unavailable (PWA). | SD, QA, PM, ADV, CL, TP |

## 1.2 Student Member

| ITEM # | TEST ITEMS | FEATURES TO BE TESTED | PERSONS INVOLVED |
|---|---|---|---|
| 1 | Member Dashboard | Displays the member's clubs, upcoming activities, and notifications. | SD, QA, PM, ADV, CL, TP |
| 2 | Club Directory Interface | Lists clubs available to browse and join. | SD, QA, PM, ADV, CL, TP |
| 3 | Club Details Interface | Displays a club's information, activities, and announcements to enrolled members. | SD, QA, PM, ADV, CL, TP |
| 4 | Join / Leave Club | Submits a pending membership request to a non-academic club and allows leaving a club. | SD, QA, PM, ADV, CL, TP |
| 5 | Activity Feed Interface | Displays the member's upcoming and past club activities. | SD, QA, PM, ADV, CL, TP |
| 6 | Bulletin Board Interface | Displays published club announcements and narratives; supports viewing, liking, and commenting. | SD, QA, PM, ADV, CL, TP |
| 7 | Notifications Interface | Displays persisted in-app notifications and allows marking them as read. | SD, QA, PM, ADV, CL, TP |

## 1.3 Club Officer (President / Treasurer / MMO)

| ITEM # | TEST ITEMS | FEATURES TO BE TESTED | PERSONS INVOLVED |
|---|---|---|---|
| 1 | Officer Dashboard | Displays management summaries for the officer's club. | SD, QA, PM, ADV, CL, TP |
| 2 | Activity Management Interface | Lists activities by month and allows adding, viewing, editing, and completing activities. | SD, QA, PM, ADV, CL, TP |
| 3 | Add / Edit Activity Form | Captures title, description, date, time, venue, purpose, expected participants, **target year level(s)**, activity type, and optional approval letter; routes internal meetings vs. external activities appropriately. | SD, QA, PM, ADV, CL, TP |
| 4 | Attendance Interface | Records member time-in/time-out for an activity. | SD, QA, PM, ADV, CL, TP |
| 5 | CHED Report Interface | Adds a narrative to, finalizes, and downloads (PDF/XLSX) auto-generated CHED reports for ACLE/community activities. | SD, QA, PM, ADV, CL, TP |
| 6 | Fees Management Interface | Creates and edits club fees and marks members paid/unpaid. | SD, QA, PM, ADV, CL, TP |
| 7 | Announcement Drafting Interface (MMO) | Creates/edits announcements, requests an **AI-assisted draft**, and submits for adviser approval. | SD, QA, PM, ADV, CL, TP |
| 8 | Member Registration Submission | Submits new member registrations for adviser approval. | SD, QA, PM, ADV, CL, TP |
| 9 | Member Presence Interface | Updates the semestral presence status of club members. | SD, QA, PM, ADV, CL, TP |
| 10 | Officer Records Interface | Views the club's officer records. | SD, QA, PM, ADV, CL, TP |
| 11 | Compliance / Violations Interface | Views and resolves club compliance notices. | SD, QA, PM, ADV, CL, TP |
| 12 | Churn Risk Interface (Officer scope) | Displays engagement scores and risk tags for the officer's own club only. | SD, QA, PM, ADV, CL, TP |

## 1.4 Club Adviser

| ITEM # | TEST ITEMS | FEATURES TO BE TESTED | PERSONS INVOLVED |
|---|---|---|---|
| 1 | Adviser Dashboard | Displays club oversight summaries and pending review items. | SD, QA, PM, ADV, CL, TP |
| 2 | Announcement Approval Interface | Reviews submitted announcements and approves, requests revision, or rejects them. | SD, QA, PM, ADV, CL, TP |
| 3 | AI Narrative Review Interface | Reviews AI-drafted post-event narratives and approves (publishes) or discards them. | SD, QA, PM, ADV, CL, TP |
| 4 | Member Registration Approval | Gives final approval or rejection to pending member registrations. | SD, QA, PM, ADV, CL, TP |
| 5 | Member Presence Interface | Manages and monitors members' semestral presence. | SD, QA, PM, ADV, CL, TP |
| 6 | Officer Records Management | Adds, edits, and archives club officer records. | SD, QA, PM, ADV, CL, TP |
| 7 | Compliance / Violations Interface | Views and resolves club compliance notices. | SD, QA, PM, ADV, CL, TP |

## 1.5 Director of Student Affairs (DSA)

| ITEM # | TEST ITEMS | FEATURES TO BE TESTED | PERSONS INVOLVED |
|---|---|---|---|
| 1 | DSA Dashboard | Displays system-wide monitoring and analytics across all clubs. | SD, QA, PM, ADV, CL, TP |
| 2 | Club Registry Interface | Lists, creates, edits, and views clubs. | SD, QA, PM, ADV, CL, TP |
| 3 | Member Oversight Interface | Read-only view of each club's member registrations. | SD, QA, PM, ADV, CL, TP |
| 4 | Officer Records Oversight | Read-only view of each club's officer records. | SD, QA, PM, ADV, CL, TP |
| 5 | Activity Review Interface | Reviews externally-classified activities and approves or rejects them with remarks. | SD, QA, PM, ADV, CL, TP |
| 6 | Activity Monitor Interface | Monitors all confirmed/scheduled activities across clubs. | SD, QA, PM, ADV, CL, TP |
| 7 | CHED Reports Interface | Views and downloads finalized CHED reports (PDF/XLSX). | SD, QA, PM, ADV, CL, TP |
| 8 | Compliance / Violations Interface | Reviews AI-drafted compliance notices and approves (dispatches) or dismisses them. | SD, QA, PM, ADV, CL, TP |
| 9 | Churn Risk Engine (All clubs) | Displays engagement scores and risk classifications across all clubs. | SD, QA, PM, ADV, CL, TP |

## 1.6 Administrator

| ITEM # | TEST ITEMS | FEATURES TO BE TESTED | PERSONS INVOLVED |
|---|---|---|---|
| 1 | Admin Dashboard | Displays administrative summaries and system status. | SD, QA, PM, ADV, CL, TP |
| 2 | Storage & Retention Interface | Displays database usage, largest tables, and archivable semesters. | SD, QA, PM, ADV, CL, TP |
| 3 | Semester Archival | Exports a past semester's attendance and fee records to Excel, then prunes them (guarded against the current semester). | SD, QA, PM, ADV, CL, TP |
| 4 | Archive Download | Downloads a previously generated archive file. | SD, QA, PM, ADV, CL, TP |
| 5 | Audit Log Pruning | Prunes audit-log entries older than the one-academic-year retention floor. | SD, QA, PM, ADV, CL, TP |

## 1.7 System & Integrations (Cross-Cutting)

| ITEM # | TEST ITEMS | FEATURES TO BE TESTED | PERSONS INVOLVED |
|---|---|---|---|
| 1 | Firebase Authentication | Login, registration, token refresh, and password reset via the Firebase REST API. | SD, QA, PM, ADV, CL, TP |
| 2 | RBAC Middleware | Enforces per-role route access and returns HTTP 403 on unauthorized direct access. | SD, QA, PM, ADV, CL, TP |
| 3 | Google Gemini AI | Announcement drafting, post-event narrative, and compliance-notice drafting. | SD, QA, PM, ADV, CL, TP |
| 4 | AI Human-Review Gate | Ensures no AI-generated content is dispatched/published without human review. | SD, QA, PM, ADV, CL, TP |
| 5 | Notifications | In-app persistence and real-time delivery; optional institutional email mirror. | SD, QA, PM, ADV, CL, TP |
| 6 | Google Calendar Sync | Background mirroring of confirmed activities to the institutional calendar. | SD, QA, PM, ADV, CL, TP |
| 7 | Audit Log | Tamper-evident logging of logins, mutations, approvals, and AI events. | SD, QA, PM, ADV, CL, TP |
| 8 | Scheduled Jobs | Nightly AI compliance scan and data-retention checks. | SD, QA, PM, ADV, CL, TP |
| 9 | PWA / Offline | Installability and offline-capable behavior via the service worker. | SD, QA, PM, ADV, CL, TP |

---

# 2. Functional & Non-Functional Requirements

## 2.1 Functional Requirements

Functional requirements define **what the system must correctly do**. For ClubSync, functional acceptance is evaluated against three criteria: **freedom from bugs**, **performance requirements**, and **logical flow**.

### 2.1.1 Freedom from Bugs

| ID | Requirement |
|---|---|
| FR-B-01 | Every input form (registration, login, activity, fee, announcement, club, CHED narrative) enforces server-side validation and rejects missing or invalid data with a clear, specific message. |
| FR-B-02 | Create, read, update, and delete operations persist accurately and are immediately reflected in both the database and the user interface. |
| FR-B-03 | No feature produces an unhandled error (HTTP 500) during normal operation; a lost database/network connection degrades gracefully to the offline page instead of crashing. |
| FR-B-04 | All computed values are correct — fee totals and paid/unpaid counts, attendance counts, storage usage percentages, churn-risk scores, and the audit-log hash chain. |
| FR-B-05 | File uploads accept only the permitted types and sizes (profile photo: image ≤2 MB; approval letter: PDF/JPG/PNG ≤5 MB) and reject all others. |
| FR-B-06 | Uniqueness and duplicate safeguards work — unique institutional email and EDP number at registration, and the duplicate activity (title + date) warning. |
| FR-B-07 | All navigation links, buttons, and role routes resolve to a valid page with no broken or dead links. |

### 2.1.2 Performance Requirements

| ID | Requirement |
|---|---|
| FR-P-01 | Standard pages load and respond within an acceptable time (target ≤ 3 seconds) under the expected load of up to 500 concurrent users. |
| FR-P-02 | List interfaces (clubs, members, bulletin, activities) use pagination and eager loading to avoid loading unbounded records or issuing N+1 queries. |
| FR-P-03 | Long-running or external operations (AI drafting, calendar sync, notifications, report generation) run as background/queued jobs and never block the user's action. |
| FR-P-04 | AI (Gemini) requests complete within the configured 15-second timeout, or fail gracefully without freezing the interface. |
| FR-P-05 | Static, content-hashed assets are cached by the service worker so repeat page loads render quickly, including on mobile networks. |

### 2.1.3 Logical Flow

| ID | Requirement |
|---|---|
| FR-L-01 | Registration proceeds strictly Step 1 → Department → Course; attempting to skip a step redirects the user back to the correct step. |
| FR-L-02 | Academic-club enrollment always creates a **pending** application requiring officer acceptance then adviser approval — never an automatically active membership. |
| FR-L-03 | Activity workflow is correct: an internal meeting is saved as Scheduled / no-approval-needed, while ACLE/community/campus-resource/other-external activities are saved as pending approval and routed to the DSA. |
| FR-L-04 | Editing a previously-approved external activity resets its status to pending approval and re-notifies the DSA; switching an activity to an internal meeting clears the approval requirement. |
| FR-L-05 | Announcement workflow follows Draft → Pending Adviser Review → Published / Revision Required / Rejected; the MMO can never publish directly. |
| FR-L-06 | All AI outputs enter a human-review queue and are only dispatched or published after explicit approval. |
| FR-L-07 | On successful login, each role is redirected to its correct dashboard, and role-restricted routes are inaccessible to other roles. |
| FR-L-08 | Marking an activity complete triggers the correct follow-up: a CHED report for ACLE/community activities and a post-event narrative draft for adviser review. |

## 2.2 Non-Functional Requirements

Non-functional requirements define **how well the system performs its functions**. They are evaluated against five criteria: **efficiency**, **reliability**, **availability**, **security**, and **user-friendliness**.

### 2.2.1 Efficiency

| ID | Requirement |
|---|---|
| NFR-E-01 | Database access is optimized through indexing, eager loading, and pagination so listing pages remain responsive against the remote database. |
| NFR-E-02 | The service worker caches content-hashed assets (cache-first) and the offline page so repeat visits and poor connections do not degrade the experience. |
| NFR-E-03 | The interface is mobile-first and lightweight, performing acceptably on low-to-mid-range devices and slower campus networks. |
| NFR-E-04 | Background queues process asynchronous work efficiently without duplicating jobs or leaving the user waiting. |

### 2.2.2 Reliability

| ID | Requirement |
|---|---|
| NFR-R-01 | A lost database or Firebase connection is caught and turned into the offline page (or a 503 JSON response for API calls), never a fatal error. |
| NFR-R-02 | When the Gemini AI is unavailable, AI-assisted features degrade gracefully, manual alternatives remain fully functional, and drafts are flagged accordingly. |
| NFR-R-03 | Failures in external mirrors (Google Calendar, Firebase Realtime DB, email) are logged and never roll back or block the originating action. |
| NFR-R-04 | The semester-archival operation exports data before deleting, runs the deletion in an all-or-nothing transaction, and is disabled ("fails closed") whenever the current semester cannot be positively identified. |
| NFR-R-05 | Every notification is persisted in-app so that offline users still receive it on their next login. |

### 2.2.3 Availability

| ID | Requirement |
|---|---|
| NFR-A-01 | ClubSync is installable as a Progressive Web App, and the offline fallback page is always available even without a network connection. |
| NFR-A-02 | A health probe (`/ping`) backs the on-screen connection banner and the offline page's auto-recovery, so clients never reload into a fatal error. |
| NFR-A-03 | Session tokens expire after 30 minutes of inactivity; "Remember Me" stores a refresh token in a secure HttpOnly cookie with a 30-day expiry. |
| NFR-A-04 | The queue worker and task scheduler run continuously to keep asynchronous features (AI, notifications, retention checks) available. |

### 2.2.4 Security

| ID | Requirement |
|---|---|
| NFR-S-01 | All authentication is handled through Firebase Authentication, which issues cryptographically signed JWT session tokens. |
| NFR-S-02 | Every route is protected by RBAC middleware; users cannot access another role's dashboard or data, and direct URL access returns HTTP 403. |
| NFR-S-03 | Registration accepts only `@sccpag.edu.ph` institutional emails, and each EDP number must be unique. |
| NFR-S-04 | Passwords require at least 8 characters with an uppercase letter, a number, and a special character, and may be changed at most once every 7 calendar days. |
| NFR-S-05 | Failed logins return a generic message that does not reveal which field was wrong; suspended accounts receive the prescribed suspension notice. |
| NFR-S-06 | All traffic uses HTTPS/TLS, and personally identifiable information is encrypted at rest in compliance with RA 10173. |
| NFR-S-07 | Churn-risk data is never visible to Student Members at either the interface or the server route level, and cross-club data access is blocked at the middleware level. |
| NFR-S-08 | A tamper-evident audit log records logins, data modifications, approval actions, and AI-generation events with timestamp and actor, retained for at least one academic year. |
| NFR-S-09 | No AI-generated content can be dispatched or published automatically; the human-review gate cannot be bypassed by any role. |

### 2.2.5 User-Friendliness

| ID | Requirement |
|---|---|
| NFR-U-01 | The interface is responsive from 360 px mobile to 1920 px desktop, with role-appropriate bottom navigation. |
| NFR-U-02 | Forms provide clear inline validation and consistent success/error feedback for every action. |
| NFR-U-03 | Branding, colors (SCC green/gold), and iconography are consistent across all roles and pages. |
| NFR-U-04 | Asynchronous actions display loading indicators/overlays so the user always knows the system is working. |
| NFR-U-05 | A visible connection banner informs users of offline/online status and recovery. |
| NFR-U-06 | No software installation is required; the system is accessed through any modern browser and may be added to the home screen. |

---

# 3. Test Case Specification

This section lists all test items across the entire ClubSync Club Management System, organized by module. The **Input Specification** and **Expected Output** columns are intentionally left blank and will be completed in a subsequent revision of this document.

> **Test Case ID key:** `AUTH` = Authentication & Access · `MEM` = Member · `OFF` = Officer · `ADV` = Adviser · `DSA` = Director of Student Affairs · `ADM` = Administrator · `SYS` = System & Integrations.

## 3.1 Authentication & Access

| Test Case ID | Test Item / Scenario | Input Specification | Expected Output |
|---|---|---|---|
| TC-AUTH-01 | Display the landing / start interface | | |
| TC-AUTH-02 | Log in with a valid institutional account | | |
| TC-AUTH-03 | Log in with invalid credentials (generic error) | | |
| TC-AUTH-04 | Log in with a suspended account | | |
| TC-AUTH-05 | Register — Step 1 with valid personal details, year level, and Terms accepted | | |
| TC-AUTH-06 | Register — Step 1 with a non-`@sccpag.edu.ph` email (rejected) | | |
| TC-AUTH-07 | Register — Step 1 with a duplicate email or missing year level (rejected) | | |
| TC-AUTH-08 | Register — Step 2 department selection | | |
| TC-AUTH-09 | Register — Step 3 course selection and account creation | | |
| TC-AUTH-10 | Register — pending academic-club application created on course selection | | |
| TC-AUTH-11 | Access a later registration step directly without completing the prior step | | |
| TC-AUTH-12 | Request a password reset for a valid email | | |
| TC-AUTH-13 | View the Terms & Conditions page | | |
| TC-AUTH-14 | View own profile | | |
| TC-AUTH-15 | Update the profile photo (valid and invalid file) | | |
| TC-AUTH-16 | Delete the profile photo | | |
| TC-AUTH-17 | Log out and end the session | | |
| TC-AUTH-18 | Change password within the 7-day lock window (rejected) | | |

## 3.2 Student Member

| Test Case ID | Test Item / Scenario | Input Specification | Expected Output |
|---|---|---|---|
| TC-MEM-01 | View the member dashboard | | |
| TC-MEM-02 | Browse the club directory | | |
| TC-MEM-03 | View a club's details (enrolled) | | |
| TC-MEM-04 | Attempt to view a club's content while pending/not enrolled | | |
| TC-MEM-05 | Submit a request to join a non-academic club | | |
| TC-MEM-06 | Leave a club | | |
| TC-MEM-07 | View the activity feed | | |
| TC-MEM-08 | View the bulletin board listing | | |
| TC-MEM-09 | Open a published announcement | | |
| TC-MEM-10 | Like an announcement | | |
| TC-MEM-11 | Comment on an announcement | | |
| TC-MEM-12 | View notifications | | |
| TC-MEM-13 | Mark a notification as read (stays dismissed) | | |
| TC-MEM-14 | Attempt to access churn-risk data directly (blocked, 403) | | |

## 3.3 Club Officer (President / Treasurer / MMO)

| Test Case ID | Test Item / Scenario | Input Specification | Expected Output |
|---|---|---|---|
| TC-OFF-01 | View the officer dashboard | | |
| TC-OFF-02 | Browse and join non-academic clubs as an officer | | |
| TC-OFF-03 | View a club the officer manages | | |
| TC-OFF-04 | View the activity management calendar/list | | |
| TC-OFF-05 | Add an internal-meeting activity (auto-scheduled) | | |
| TC-OFF-06 | Add an external activity (routed to DSA for approval) | | |
| TC-OFF-07 | Add an activity with one or more target year levels | | |
| TC-OFF-08 | Add an activity leaving target year levels empty (open to all) | | |
| TC-OFF-09 | Submit an activity with missing required fields (rejected) | | |
| TC-OFF-10 | Trigger the duplicate activity (title + date) warning | | |
| TC-OFF-11 | View activity details | | |
| TC-OFF-12 | Edit an activity | | |
| TC-OFF-13 | Edit a previously-approved external activity (resets to pending) | | |
| TC-OFF-14 | Upload and download an approval letter | | |
| TC-OFF-15 | Mark an activity as completed | | |
| TC-OFF-16 | Open the attendance sheet for an activity | | |
| TC-OFF-17 | Record a member's attendance (time-in/time-out) | | |
| TC-OFF-18 | Add a narrative to a CHED report | | |
| TC-OFF-19 | Finalize and send a CHED report to the DSA | | |
| TC-OFF-20 | Download a CHED report as PDF | | |
| TC-OFF-21 | Download a CHED report as XLSX | | |
| TC-OFF-22 | View the fees list | | |
| TC-OFF-23 | Create a club fee | | |
| TC-OFF-24 | Edit a club fee | | |
| TC-OFF-25 | Mark a member as paid | | |
| TC-OFF-26 | Mark a member as unpaid | | |
| TC-OFF-27 | View the announcements list (MMO) | | |
| TC-OFF-28 | Create/save an announcement draft | | |
| TC-OFF-29 | Request an AI-assisted announcement draft | | |
| TC-OFF-30 | Edit an announcement | | |
| TC-OFF-31 | Submit an announcement for adviser review | | |
| TC-OFF-32 | Submit a new member registration | | |
| TC-OFF-33 | Update a member's semestral presence status | | |
| TC-OFF-34 | View the officer records list | | |
| TC-OFF-35 | View and resolve a compliance/violation notice | | |
| TC-OFF-36 | View churn-risk data scoped to the officer's own club | | |

## 3.4 Club Adviser

| Test Case ID | Test Item / Scenario | Input Specification | Expected Output |
|---|---|---|---|
| TC-ADV-01 | View the adviser dashboard | | |
| TC-ADV-02 | View the announcement approval queue | | |
| TC-ADV-03 | Review an individual announcement | | |
| TC-ADV-04 | Approve an announcement (publish) | | |
| TC-ADV-05 | Request a revision on an announcement | | |
| TC-ADV-06 | Reject an announcement | | |
| TC-ADV-07 | View the AI narrative review queue | | |
| TC-ADV-08 | Review an AI-drafted narrative | | |
| TC-ADV-09 | Approve a narrative (publish to bulletin) | | |
| TC-ADV-10 | Discard a narrative | | |
| TC-ADV-11 | Give final approval to a pending member registration | | |
| TC-ADV-12 | Reject a pending member registration | | |
| TC-ADV-13 | Manage members' semestral presence | | |
| TC-ADV-14 | Notify the DSA of presence concerns | | |
| TC-ADV-15 | Create an officer record | | |
| TC-ADV-16 | Edit an officer record | | |
| TC-ADV-17 | Archive an officer record | | |
| TC-ADV-18 | View and resolve a compliance/violation notice | | |
| TC-ADV-19 | Attempt to access the Churn Risk Engine (not permitted) | | |

## 3.5 Director of Student Affairs (DSA)

| Test Case ID | Test Item / Scenario | Input Specification | Expected Output |
|---|---|---|---|
| TC-DSA-01 | View the DSA dashboard | | |
| TC-DSA-02 | View the club registry list | | |
| TC-DSA-03 | Create a new club | | |
| TC-DSA-04 | View a club's details | | |
| TC-DSA-05 | Edit and update a club | | |
| TC-DSA-06 | View a club's member registrations (read-only) | | |
| TC-DSA-07 | View a club's officer records (read-only) | | |
| TC-DSA-08 | View the activity review queue | | |
| TC-DSA-09 | Approve an external activity | | |
| TC-DSA-10 | Reject an external activity with remarks | | |
| TC-DSA-11 | Download an activity's approval letter | | |
| TC-DSA-12 | Monitor all confirmed/scheduled activities | | |
| TC-DSA-13 | View the finalized CHED reports list | | |
| TC-DSA-14 | Download a CHED report (PDF/XLSX) | | |
| TC-DSA-15 | View the compliance/violations queue | | |
| TC-DSA-16 | Review a violation notice | | |
| TC-DSA-17 | Approve (dispatch) a violation notice after review | | |
| TC-DSA-18 | Dismiss a violation notice | | |
| TC-DSA-19 | View the Churn Risk Engine across all clubs | | |

## 3.6 Administrator

| Test Case ID | Test Item / Scenario | Input Specification | Expected Output |
|---|---|---|---|
| TC-ADM-01 | View the admin dashboard | | |
| TC-ADM-02 | View the storage & retention overview | | |
| TC-ADM-03 | Archive an eligible (old) semester | | |
| TC-ADM-04 | Attempt to archive the current/most-recent semester (blocked) | | |
| TC-ADM-05 | Attempt to archive when no current semester is set (fails closed) | | |
| TC-ADM-06 | Download a generated archive file | | |
| TC-ADM-07 | Prune audit-log entries beyond the retention window | | |

## 3.7 System & Integrations

| Test Case ID | Test Item / Scenario | Input Specification | Expected Output |
|---|---|---|---|
| TC-SYS-01 | Firebase login/registration through the REST API | | |
| TC-SYS-02 | Firebase expired-token auto-refresh on request | | |
| TC-SYS-03 | RBAC enforcement / 403 on cross-role direct access | | |
| TC-SYS-04 | Cross-club data access blocked at middleware | | |
| TC-SYS-05 | Gemini AI — announcement drafting | | |
| TC-SYS-06 | Gemini AI — post-event narrative drafting | | |
| TC-SYS-07 | Gemini AI — compliance/violation statement drafting | | |
| TC-SYS-08 | AI human-review gate (no automatic dispatch/publish) | | |
| TC-SYS-09 | Graceful behavior when Gemini is unavailable | | |
| TC-SYS-10 | In-app notification persistence for offline users | | |
| TC-SYS-11 | Real-time notification delivery | | |
| TC-SYS-12 | Google Calendar sync of a confirmed activity | | |
| TC-SYS-13 | Calendar/mirror sync failure does not block the action | | |
| TC-SYS-14 | Tamper-evident audit log chaining | | |
| TC-SYS-15 | Nightly AI compliance scan (scheduled job) | | |
| TC-SYS-16 | Data-retention check (scheduled job) | | |
| TC-SYS-17 | PWA installation and offline fallback page | | |
| TC-SYS-18 | Connectivity probe (`/ping`) and connection banner | | |

---

*This document was prepared as the Software Testing documentation for the ClubSync capstone project, College of Computing Studies, Saint Columban College, Pagadian City. It is intended for encoding into the Word document submission and as the content basis for the group presentation. The Input Specification and Expected Output columns of the Test Case Specification are to be completed in a subsequent revision.*

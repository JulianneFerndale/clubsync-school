# ClubSync – Club Management System
## Software Deployment and Licensing Activity Output
### College of Computing Studies | Saint Columban College, Pagadian City

**Group Members:**
- Roxanne Kate Pagsiat Ulgasan – Project Manager
- Julianne Ferndale Torino Flores – System Developer
- Jynie Frasco Catacutan – System Quality Assurance

**Adviser:** Mr. Hardy Facunla, LPT, MLIS, RL
**Client:** Mr. Rosebelt Silao Lomocso, LPT, MPA, CSASS
**Date:** May 25, 2026

---

## 1. Software and Tools Used in the Capstone Project

ClubSync was built entirely using open-source and free-tier tools in compliance with the project's zero-budget constraint. The following table enumerates every significant software component, framework, and external service used in its development and operation.

| Category | Tool / Technology | Purpose |
|---|---|---|
| **Backend Framework** | PHP 8.3 / Laravel 13 | Primary server-side framework; handles routing, business logic, RBAC middleware, session management, and all API communications |
| **Frontend Framework** | Vue 3.5 | Reactive component-based user interface for interactive pages |
| **Full-Stack Bridge** | Inertia.js 3.1 | Connects Laravel controllers to Vue components without a separate REST API layer |
| **CSS Framework** | Tailwind CSS 4.0 | Utility-first styling; ensures responsive design from 360px mobile to 1920px desktop |
| **Build Tool** | Vite 8.0 | Frontend asset bundling, hot module replacement during development |
| **Authentication Service** | Firebase Authentication (REST API) | Manages user accounts, credential verification, JWT session tokens, and password operations |
| **Real-Time Database** | Firebase Realtime Database (NoSQL) | Stores and synchronizes live data: event feeds, notifications, club announcements, and engagement metrics |
| **Relational Database** | PostgreSQL (production) / SQLite (automated testing) | Structured persistent data: users, clubs, memberships, events, fees, audit logs |
| **AI Integration** | Google Gemini API (gemini-flash-latest) | AI-assisted announcement drafting, nightly compliance notification generation, and post-event report narration |
| **Testing Framework** | Pest 4.6 + Mockery | Automated unit and feature testing for PHP backend logic |
| **Version Control** | Git / GitHub | Source code management; branching strategy (main, staging, feature branches) |
| **Package Manager (PHP)** | Composer | PHP dependency management |
| **Package Manager (JS)** | npm | JavaScript dependency management |
| **Development Utilities** | Laravel Tinker, Laravel Pail | Interactive shell and real-time log streaming during development |
| **Progressive Web App (PWA)** | Service Worker (sw.js) | Enables installability and offline-capable behavior on Android and iOS browsers without a native app |
| **Hosting Environment** | Apache 2.4+ / Nginx 1.18+ with PHP 8.x | Web server for running the Laravel application |

---

## 2. System Deployment Plan

### Deployment Model: On-Premise School Server

ClubSync will be deployed on a dedicated physical server hosted within the Saint Columban College campus. This decision was made to keep all student data — including EDP numbers, institutional email addresses, and financial records — under the direct custody of the institution, in alignment with the Philippine Data Privacy Act of 2012 (RA 10173).

### Server Requirements

| Requirement | Specification |
|---|---|
| Operating System | Linux-based (Ubuntu Server 22.04 LTS or equivalent) |
| Web Server | Apache 2.4+ or Nginx 1.18+ |
| PHP Version | PHP 8.3 |
| Database | PostgreSQL 15+ |
| RAM | Minimum 2 GB (4 GB recommended for up to 500 concurrent users) |
| Disk Storage | Minimum 20 GB |
| SSL/TLS Certificate | Required; enforces HTTPS on all connections |
| Internet Connection | Required for Firebase services and Gemini API calls |

### Deployment Steps

1. Provision the SCC server with PHP 8.3, PostgreSQL, and a web server (Apache or Nginx).
2. Clone the ClubSync repository from GitHub onto the server.
3. Run `composer run setup` to install dependencies, generate the application key, run database migrations, and seed initial data (departments, admin account, club registry).
4. Configure the `.env` file with the school's Firebase project credentials (`FIREBASE_API_KEY`, `FIREBASE_PROJECT_ID`, `FIREBASE_DATABASE_URL`) and the Gemini API key (`GEMINI_API_KEY`).
5. Install and configure an SSL/TLS certificate to enforce HTTPS.
6. Set up the server cron job for the nightly AI compliance scan (scheduled at 11:00 PM daily).
7. Register a domain name (to be confirmed by the institution) and point it to the server's IP address.
8. Conduct User Acceptance Testing (UAT) with representative users from each role group (DSA, Adviser, Officers, Students) before full rollout.

### Access Method

ClubSync is a **Progressive Web Application (PWA)**. Users access it through any modern web browser (Google Chrome, Safari, Mozilla Firefox, or Microsoft Edge) by navigating to the school-assigned URL. No software installation is required. The PWA can be added to the home screen of Android and iOS devices for an app-like experience.

---

## 3. Licensing and Pricing Model

### Model: Free — Donated to Saint Columban College

ClubSync is released to Saint Columban College **free of charge** as the final output of the group's capstone project in partial fulfillment of the academic requirements of the College of Computing Studies.

| Aspect | Details |
|---|---|
| **License Type** | Open use, institution-exclusive — provided to SCC at no cost |
| **Ongoing Cost to SCC** | Possible domain registration and web hosting fees only (if an external hosting provider is used in the future) |
| **Software License Costs** | None — all frameworks and tools are open-source or free-tier (Laravel, Vue, Firebase Spark/Blaze tier, Gemini API free tier) |
| **Commercial Restriction** | ClubSync is built exclusively for Saint Columban College. It is not intended for resale or commercial distribution. |
| **Maintenance** | Post-deployment support may be provided by the development team on a voluntary basis during the academic period |

The only permissible financial expenditure identified in the project specification is domain registration and web hosting, should the institution opt to use an external hosting provider rather than its own on-premise server.

---

## 4. System Plan: Access, Protection, and Policies

### 4.1 How Users Access the System

All users access ClubSync exclusively through a web browser using their **official SCC institutional email address** (domain: `@sccpag.edu.ph`). No other email domain is accepted during registration. Access is role-based and determined upon login.

| User Role | How They Gain Access | Dashboard Directed To |
|---|---|---|
| **Student Member** | Self-registers via the registration page using their SCC email, EDP number, enrolled course, and school year level | Personal Club Dashboard |
| **Club Officer** (President, Treasurer, MMO) | Account assigned by the Administrator; logs in with institutional email | Officer Management Panel |
| **Club Adviser** | Account assigned by the Administrator | Club Oversight Dashboard |
| **Director of Student Affairs (DSA)** | Account assigned by the Administrator | Monitoring and Analytics Dashboard |
| **Administrator** | Seeded during initial deployment (`php artisan db:seed --class=SeedAdmin`) | Admin Control Panel |

Upon course selection during self-registration, the system **automatically enrolls** the student into their corresponding academic club. This enrollment is immediate, mandatory, and requires no additional action from the student or any administrator. Students may additionally apply to non-academic clubs from their dashboard.

### 4.2 Payment Terms

ClubSync is provided to Saint Columban College at **no cost to users**. There are no subscription fees, one-time payments, or product keys required to access the system. All features are fully available to all registered SCC users within their permitted role.

### 4.3 Protection from Unauthorized Use

ClubSync employs multiple layers of security to prevent unauthorized access and misuse:

**Authentication Layer**
- All login is handled through **Firebase Authentication**, a production-grade identity service by Google, which issues cryptographically signed JWT (JSON Web Token) session tokens upon successful login.
- Session tokens expire after **30 minutes of inactivity**. The "Remember Me" option stores a refresh token in a secure, HttpOnly cookie with a 30-day expiry, invisible to JavaScript and protected from XSS attacks.
- Failed login attempts return a generic error message that does not reveal whether the email or password is incorrect, preventing account enumeration attacks.

**Registration Restriction**
- Only email addresses under the `@sccpag.edu.ph` domain are accepted during registration, preventing external, non-SCC individuals from creating accounts.
- Each EDP (Electronic Data Processing) number must be unique in the system to prevent duplicate accounts.

**Role-Based Access Control (RBAC)**
- Every route in the system is protected by Laravel middleware that enforces role-based permissions. A user cannot access a dashboard or feature that does not belong to their assigned role — not even by manually typing a URL.
- Unauthorized direct URL access returns an **HTTP 403 Forbidden** response.
- The Churn Risk Engine data is strictly inaccessible to Student Members at both the UI and server route level.

**HTTPS Encryption**
- All communication between the browser, the server, and external services (Firebase, Gemini API) is transmitted exclusively over **HTTPS with TLS 1.2 or higher**. HTTP requests are automatically redirected to HTTPS. No sensitive data (credentials, EDP numbers, session tokens) is ever transmitted over unencrypted connections.

**Data Privacy Compliance (RA 10173)**
- The system is designed in full compliance with the **Philippine Data Privacy Act of 2012 (Republic Act No. 10173)**. Student personally identifiable information (PII) — including names, EDP numbers, institutional emails, and financial records — is encrypted at rest.
- The principle of least privilege is applied: every user role can only access the minimum data necessary for their function. Cross-club data access is blocked at the middleware level.

**Audit Trail**
- A tamper-resistant audit log records all login attempts (successful and failed), account modifications, role changes, data edits, approval actions, and AI content generation events — with timestamps and user identifiers. Logs are retained for a minimum of one complete academic year.

**AI Content Gate**
- No AI-generated content (compliance notifications, post-event report narratives) can be automatically dispatched. All AI outputs pass through a mandatory human review queue. This gate cannot be bypassed by any user role.

### 4.4 Rules and Policies Users Must Follow

The following policies govern all users of the ClubSync system:

**Account and Registration**
1. Only students with an active, official SCC institutional email address (`@sccpag.edu.ph`) may register.
2. Users must provide accurate personal information, including their correct EDP number, enrolled academic course, and current school year level, during registration.
3. Creating multiple accounts or registering on behalf of another person is strictly prohibited.

**Password Policy**
4. Passwords must meet the following complexity requirements: minimum 8 characters, at least one uppercase letter, one number, and one special character.
5. Users may change their password **at most once every seven (7) calendar days**. Attempting to change a password before the 7-day period has elapsed will be rejected by the system, which will display the next available change date.

**Data and Privacy**
6. Users must not attempt to access, copy, or share data that belongs to other users, other clubs, or other roles beyond their permitted scope.
7. Users must not use ClubSync to store, share, or publish content that violates the privacy of other students or contravenes RA 10173.

**Content and Announcements**
8. All announcements and official letters drafted by the Media Mass Officer (MMO) must be submitted for Adviser approval before publication. No content may be published directly without passing through the approval workflow.
9. Users must not post false, misleading, or inappropriate content through the announcement or letter system.

**System Use**
10. ClubSync is intended exclusively for official Saint Columban College club management activities. Use of the system for personal, commercial, or any non-institutional purpose is prohibited.
11. Users must not attempt to circumvent the system's authentication, RBAC controls, or any security mechanism.
12. The Churn Risk Engine results are confidential institutional data. Club Officers and the DSA who have access to this data must not disclose individual student engagement scores or risk classifications to unauthorized parties, including the students themselves.

**Compliance**
13. Club Officers are responsible for ensuring their club's records (events, attendance, fees, documents) are kept current. Overdue records will be flagged by the system's nightly AI compliance scan.
14. All users are subject to the rules and disciplinary procedures set forth in the **SCC Student Handbook** in addition to these system-specific policies.

---

## 5. Summary

ClubSync is a free, open-source-stack Progressive Web Application developed exclusively for Saint Columban College as a capstone project output. It will be deployed on the school's on-premise server, accessible to all registered SCC users through a standard web browser with no installation required. The system enforces access control through Firebase Authentication, role-based middleware, HTTPS encryption, and RA 10173-compliant data handling. All users must abide by the account, password, content, and data privacy policies outlined above. The system is donated to SCC at no cost, with the only potential future expense being domain registration or external hosting fees should the institution elect to use a third-party provider.

---

*This document was prepared as the written output for the Software Deployment and Licensing Activity of the ClubSync capstone project, College of Computing Studies, Saint Columban College, Pagadian City. It is intended for encoding into the Word document submission and as the content basis for the group presentation slideshow (PPT).*

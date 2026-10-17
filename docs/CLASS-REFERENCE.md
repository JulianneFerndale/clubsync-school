# ClubSync — Class Reference

Complete inventory of every class in the project, with attribute datatypes and method signatures.

**Notation (UML):**

| Symbol | Meaning |
|---|---|
| `+` | `public` — callable from outside the class |
| `-` | `private` — internal helper, only usable inside the class |
| `#` | `protected` — usable by the class and its subclasses |
| `+$` / `-$` | static member |

**Totals:** 103 named classes + 45 anonymous migration classes.

| Layer | Count |
|---|---|
| Models (Eloquent) | 23 |
| Controllers | 35 |
| Jobs (queued) | 14 |
| Services | 8 |
| Middleware | 5 |
| Console Commands | 3 |
| Exports | 3 |
| Seeders | 5 |
| Events / Mail / Providers / Support / Database / Factory / TestCase | 7 |
| Migrations (anonymous `class extends Migration`) | 45 |

---

## 1. Models — `App\Models`

Attribute datatypes are the actual database column types (from `database/migrations/`), followed by the Eloquent cast where one is declared.

### 1.1 `User extends Authenticatable`
Uses: `HasFactory`, `Notifiable`

| Attribute | Datatype | Notes |
|---|---|---|
| id | `bigint` unsigned | PK |
| firebase_uid | `string` nullable, unique | Firebase Auth link |
| role | `enum('dsa','adviser','president','treasurer','mmo','member')` | default `member` |
| first_name | `string` | |
| last_name | `string` | |
| edp_number | `string(20)` nullable | student number |
| employee_number | `string` nullable, unique | faculty/adviser number |
| email | `string` unique | |
| email_verified_at | `timestamp` nullable → cast `datetime` | |
| password | `string` → cast `hashed` | hidden |
| mobile_number | `string(20)` nullable | |
| department_id | `bigint` FK → departments, nullable | |
| course_id | `bigint` FK → courses, nullable | |
| year_level | `unsignedTinyInteger` nullable → cast `integer` | 1–4 |
| college_id | `bigint` FK → colleges, nullable | |
| profile_photo_url | `string` nullable | |
| last_password_changed | `timestamp` nullable → cast `datetime` | |
| is_suspended | `boolean` → cast `boolean` | default `false` |
| is_admin | `boolean` → cast `boolean` | default `false` |
| remember_token | `string` | hidden |
| created_at / updated_at | `timestamp` | |

*Declared via PHP attributes `#[Fillable([...])]` and `#[Hidden(['password','remember_token'])]`.*

| Vis | Method | Returns |
|---|---|---|
| `#` | `casts()` | `array` |
| `+` | `getYearLevelLabelAttribute()` | `?string` — accessor, e.g. "1st Year" |
| `+` | `getNameAttribute()` | `string` — accessor, first + last name |
| `+` | `department()` | `BelongsTo` |
| `+` | `course()` | `BelongsTo` |
| `+` | `college()` | `BelongsTo` |

---

### 1.2 `Club extends Model`

| Attribute | Datatype |
|---|---|
| id | `bigint` unsigned PK |
| slug | `string` unique |
| acronym | `string` |
| name | `string` |
| adviser | `string` nullable (legacy text) |
| adviser_id | `bigint` FK → users, nullable |
| club_type | `enum('Academic','Non-Academic')` |
| type | `string` |
| course_slug | `string` default `all` |
| department_slug | `string` default `all` |
| college_id | `bigint` FK → colleges, nullable |
| description | `text` nullable |
| profile_photo_url | `string` nullable |
| banner_image | `string` nullable |
| is_active | `boolean` default `true` → cast `boolean` |
| created_at / updated_at | `timestamp` |

| Vis | Method | Returns |
|---|---|---|
| `+` | `members()` | `HasMany` |
| `+` | `officers()` | `HasMany` |
| `+` | `activities()` | `HasMany` |
| `+` | `announcements()` | `HasMany` |
| `+` | `chedReports()` | `HasMany` |
| `+` | `college()` | `BelongsTo` |
| `+` | `adviserUser()` | `BelongsTo` |
| `+` | `department()` | `BelongsTo` |
| `+` | `courses()` | `BelongsToMany` |
| `+` | `scopeCompliant($query)` | query scope |
| `+` | `scopeNonCompliant($query)` | query scope |
| `+` | `scopePendingApplications($query)` | query scope |
| `+` | `scopeAcademic($query)` | query scope |
| `+` | `scopeNonAcademic($query)` | query scope |
| `+` | `scopeActive($query)` | query scope |
| `+` | `getActiveMemberCountAttribute()` | `int` — accessor |

---

### 1.3 `ClubActivity extends Model`
Table `club_activities` (renamed from `events`).

| Attribute | Datatype |
|---|---|
| id | `bigint` unsigned PK |
| club_id | `bigint` FK → clubs, cascade delete |
| title | `string` |
| description | `text` |
| date | `date` → cast `date` |
| time_start | `time` |
| time_end | `time` |
| venue | `string` — one of `const VENUES` (main-campus rooms); exclusive per overlapping time slot via `findVenueConflict()` |
| purpose | `text` |
| expected_participants | `unsignedInteger` → cast `integer` |
| status | `enum('scheduled','completed','cancelled')` default `scheduled` |
| completed_at | `timestamp` nullable → cast `datetime` |
| event_type | `enum('standard','acle')` default `standard` |
| activity_type | `enum('internal_meeting','acle','community_involvement','campus_resource_use','other_external')` default `internal_meeting` |
| target_year_levels | `json` nullable → cast `array` |
| approval_status | `enum('no_approval_needed','pending_approval','approved','rejected')` default `no_approval_needed` |
| dsa_remarks | `text` nullable |
| approval_letter_path | `string` nullable |
| approved_at | `timestamp` nullable → cast `datetime` |
| approved_by | `bigint` FK → users, nullable |
| created_by | `bigint` FK → users, restrict delete |
| post_report_content | `longText` nullable |
| post_report_status | `enum('pending','ready','submitted')` nullable |
| created_at / updated_at | `timestamp` |

| Vis | Method | Returns |
|---|---|---|
| `+` | `club()` | `BelongsTo` |
| `+` | `creator()` | `BelongsTo` |
| `+` | `approvedBy()` | `BelongsTo` |
| `+` | `attendance()` | `HasMany` |
| `+` | `changeLogs()` | `HasMany` |
| `+` | `chedReport()` | `HasOne` |
| `+` | `scopeNeedsApproval($query)` | query scope |
| `+` | `scopeUpcoming($query)` | query scope |
| `+` | `scopeCompleted($query)` | query scope |
| `+` | `getTargetYearLevelsLabelAttribute()` | `string` — accessor |
| `+` | `const CONFIRMED_APPROVAL_STATUSES` | `array` = `['no_approval_needed','approved']` |
| `+` | `isConfirmed()` | `bool` — confirmed approval status and not cancelled |
| `+` | `reachesYearLevel(?int $yearLevel)` | `bool` — empty target list or unknown year level counts as reached |

---

### 1.4 `ClubMember extends Model`

| Attribute | Datatype |
|---|---|
| id | `bigint` unsigned PK |
| club_id | `bigint` FK → clubs, cascade delete |
| user_id | `bigint` FK → users, cascade delete |
| role | `string(50)` default `Member` |
| date_joined | `timestamp` useCurrent → cast `datetime` |
| joined_at | `timestamp` nullable → cast `datetime` |
| status | `enum('active','inactive','pending')` default `pending` |
| registration_status | `enum('pending','approved','rejected')` default `pending` |
| dsa_remarks | `text` nullable |
| submitted_by | `bigint` FK → users, nullable |
| approved_by | `bigint` FK → users, nullable |
| approved_at | `timestamp` nullable → cast `datetime` |
| created_at / updated_at | `timestamp` |

Unique constraint: `(club_id, user_id)`

| Vis | Method | Returns |
|---|---|---|
| `+` | `club()` | `BelongsTo` |
| `+` | `user()` | `BelongsTo` |
| `+` | `submittedBy()` | `BelongsTo` |
| `+` | `approvedBy()` | `BelongsTo` |
| `+` | `semesterStatuses()` | `HasMany` |
| `+` | `currentSemesterStatus()` | `?MemberSemesterStatus` |

---

### 1.5 `ClubOfficer extends Model`

| Attribute | Datatype |
|---|---|
| id | `bigint` unsigned PK |
| club_id | `bigint` FK → clubs, cascade delete |
| user_id | `bigint` FK → users, cascade delete |
| position | `enum('president','treasurer','mmo')` |
| assigned_at | `timestamp` useCurrent → cast `datetime` |
| created_at / updated_at | `timestamp` |

Unique constraint: `(club_id, user_id, position)`

| Vis | Method | Returns |
|---|---|---|
| `+` | `club()` | `BelongsTo` |
| `+` | `user()` | `BelongsTo` |

---

### 1.6 `ClubOfficerRecord extends Model`

| Attribute | Datatype |
|---|---|
| id | `bigint` unsigned PK |
| club_id | `bigint` FK → clubs, cascade delete |
| full_name | `string` |
| position | `string` |
| student_id_number | `string` |
| contact_email | `string` nullable |
| academic_year | `string` |
| semester | `enum('1st','2nd')` |
| is_active | `boolean` default `true` → cast `boolean` |
| created_at / updated_at | `timestamp` |

| Vis | Method | Returns |
|---|---|---|
| `+` | `club()` | `BelongsTo` |

---

### 1.7 `Announcement extends Model`

| Attribute | Datatype |
|---|---|
| id | `bigint` unsigned PK |
| club_id | `bigint` FK → clubs, nullable |
| author_id | `bigint` FK → users, restrict delete |
| title | `string` nullable |
| content | `longText` |
| type | `enum('announcement','letter')` default `announcement` |
| status | `enum('draft','pending_review','revision_required','published','rejected')` default `draft` |
| ai_assisted | `boolean` default `false` → cast `boolean` |
| adviser_notes | `text` nullable |
| reviewed_by | `bigint` FK → users, nullable |
| reviewed_at | `timestamp` nullable → cast `datetime` |
| published_at | `timestamp` nullable → cast `datetime` |
| created_at / updated_at | `timestamp` |

| Vis | Method | Returns |
|---|---|---|
| `+` | `club()` | `BelongsTo` |
| `+` | `author()` | `BelongsTo` |
| `+` | `reviewer()` | `BelongsTo` |
| `+` | `likes()` | `HasMany` |
| `+` | `comments()` | `HasMany` |
| `+` | `scopePublished($query)` | query scope |
| `+` | `scopePendingReview($query)` | query scope |

---

### 1.8 `AnnouncementComment extends Model`

| Attribute | Datatype |
|---|---|
| id | `bigint` unsigned PK |
| announcement_id | `bigint` FK → announcements, cascade delete |
| user_id | `bigint` FK → users, cascade delete |
| content | `text` |
| created_at / updated_at | `timestamp` |

| Vis | Method | Returns |
|---|---|---|
| `+` | `announcement()` | `BelongsTo` |
| `+` | `user()` | `BelongsTo` |

---

### 1.9 `AnnouncementLike extends Model`

| Attribute | Datatype |
|---|---|
| id | `bigint` unsigned PK |
| announcement_id | `bigint` FK → announcements, cascade delete |
| user_id | `bigint` FK → users, cascade delete |
| created_at / updated_at | `timestamp` |

Unique constraint: `(announcement_id, user_id)`

| Vis | Method | Returns |
|---|---|---|
| `+` | `announcement()` | `BelongsTo` |
| `+` | `user()` | `BelongsTo` |

---

### 1.10 `Attendance extends Model`
Table `attendance`.

| Attribute | Datatype |
|---|---|
| id | `bigint` unsigned PK |
| event_id | `bigint` FK → club_activities, cascade delete |
| user_id | `bigint` FK → users, cascade delete |
| time_in | `timestamp` nullable → cast `datetime` |
| time_out | `timestamp` nullable → cast `datetime` |
| recorded_by | `bigint` FK → users, nullable |
| created_at / updated_at | `timestamp` |

Unique constraint: `(event_id, user_id)`

| Vis | Method | Returns |
|---|---|---|
| `+` | `activity()` | `BelongsTo` |
| `+` | `user()` | `BelongsTo` |
| `+` | `recorder()` | `BelongsTo` |

---

### 1.11 `AuditLog extends Model`
`const UPDATED_AT = null` — append-only, no updates.

| Attribute | Datatype |
|---|---|
| id | `bigint` unsigned PK |
| actor_id | `bigint` FK → users, nullable |
| action | `string` (indexed) |
| resource_type | `string` nullable |
| resource_id | `unsignedBigInteger` nullable |
| metadata | `json` nullable → cast `array` |
| previous_hash | `string` nullable |
| hash | `string` |
| created_at | `timestamp` useCurrent → cast `datetime` |

Index: `(resource_type, resource_id)`

| Vis | Method | Returns |
|---|---|---|
| `+` | `actor()` | `BelongsTo` |
| `+$` | `record(string $action, ?Model $resource = null, array $metadata = [], ?int $actorId = null)` | `self` — static, writes the hash-chained entry |

---

### 1.12 `AiNotificationQueue extends Model`
Table `ai_notification_queue`. Backs the club-violation workflow.

| Attribute | Datatype |
|---|---|
| id | `bigint` unsigned PK |
| club_id | `bigint` FK → clubs, cascade delete |
| gap_type | `string` |
| description | `text` nullable |
| draft_content | `longText` (AI-generated) |
| dsa_edited_content | `longText` nullable |
| status | `enum('pending_review','approved','edited_sent','discarded')` default `pending_review` |
| reviewed_by | `bigint` FK → users, nullable |
| reviewed_at | `timestamp` nullable → cast `datetime` |
| sent_at | `timestamp` nullable → cast `datetime` |
| ai_available | `boolean` default `true` → cast `boolean` |
| is_resolved | `boolean` default `false` → cast `boolean` |
| resolution_note | `text` nullable |
| resolved_at | `timestamp` nullable → cast `datetime` |
| resolved_by | `bigint` FK → users, nullable |
| created_at / updated_at | `timestamp` |

| Vis | Method | Returns |
|---|---|---|
| `+` | `club()` | `BelongsTo` |
| `+` | `reviewedBy()` | `BelongsTo` |
| `+` | `resolvedBy()` | `BelongsTo` |
| `+` | `finalContent()` | `?string` — edited text if present, else the draft |

---

### 1.13 `ClubNarrative extends Model`

| Attribute | Datatype |
|---|---|
| id | `bigint` unsigned PK |
| club_id | `bigint` FK → clubs, cascade delete |
| club_activity_id | `bigint` FK → club_activities, nullable |
| title | `string` |
| draft_content | `longText` nullable |
| adviser_edited_content | `longText` nullable |
| status | `enum('draft','pending_review','published','discarded')` default `pending_review` |
| ai_available | `boolean` default `true` → cast `boolean` |
| reviewed_by | `bigint` FK → users, nullable |
| reviewed_at | `timestamp` nullable → cast `datetime` |
| published_at | `timestamp` nullable → cast `datetime` |
| created_at / updated_at | `timestamp` |

| Vis | Method | Returns |
|---|---|---|
| `+` | `club()` | `BelongsTo` |
| `+` | `activity()` | `BelongsTo` |
| `+` | `reviewer()` | `BelongsTo` |
| `+` | `finalContent()` | `?string` |
| `+` | `scopePublished($query)` | query scope |
| `+` | `scopePendingReview($query)` | query scope |

---

### 1.14 `ClubNotification extends Model`
Table `notifications`.

| Attribute | Datatype |
|---|---|
| id | `bigint` unsigned PK |
| recipient_id | `bigint` FK → users, cascade delete |
| sender_type | `enum('system','user','ai_queue')` default `system` |
| club_id | `bigint` FK → clubs, nullable |
| title | `string` |
| body | `text` |
| is_read | `boolean` default `false` → cast `boolean` |
| action_url | `string` nullable |
| created_at / updated_at | `timestamp` |

| Vis | Method | Returns |
|---|---|---|
| `#$` | `booted()` | `void` — static; fires `NotificationCreated` broadcast on create |
| `+` | `recipient()` | `BelongsTo` |
| `+` | `club()` | `BelongsTo` |
| `+$` | `unreadCountFor(int $userId)` | `int` — static |

---

### 1.15 `ActivityChangeLog extends Model`

| Attribute | Datatype |
|---|---|
| id | `bigint` unsigned PK |
| club_activity_id | `bigint` FK → club_activities, cascade delete |
| changed_by | `bigint` FK → users |
| changes | `json` → cast `array` |
| created_at / updated_at | `timestamp` |

| Vis | Method | Returns |
|---|---|---|
| `+` | `activity()` | `BelongsTo` |
| `+` | `changedBy()` | `BelongsTo` |

---

### 1.16 `ChedReport extends Model`

| Attribute | Datatype |
|---|---|
| id | `bigint` unsigned PK |
| club_activity_id | `bigint` FK → club_activities |
| club_id | `bigint` FK → clubs |
| report_type | `string` |
| pdf_path | `string` nullable |
| xlsx_path | `string` nullable |
| narrative | `text` nullable |
| is_finalized | `boolean` default `false` → cast `boolean` |
| created_at / updated_at | `timestamp` |

| Vis | Method | Returns |
|---|---|---|
| `+` | `activity()` | `BelongsTo` |
| `+` | `club()` | `BelongsTo` |

---

### 1.17 `Fee extends Model`

| Attribute | Datatype |
|---|---|
| id | `bigint` unsigned PK |
| club_id | `bigint` FK → clubs, cascade delete |
| title | `string` |
| amount | `decimal(8,2)` → cast `decimal:2` |
| due_date | `date` → cast `date` |
| academic_period | `string` |
| created_at / updated_at | `timestamp` |

| Vis | Method | Returns |
|---|---|---|
| `+` | `club()` | `BelongsTo` |
| `+` | `payments()` | `HasMany` |

---

### 1.18 `FeePayment extends Model`

| Attribute | Datatype |
|---|---|
| id | `bigint` unsigned PK |
| fee_id | `bigint` FK → fees, cascade delete |
| user_id | `bigint` FK → users, cascade delete |
| status | `enum('pending','paid')` default `pending` |
| confirmed_by | `bigint` FK → users, nullable |
| confirmed_at | `timestamp` nullable → cast `datetime` |
| created_at / updated_at | `timestamp` |

Unique constraint: `(fee_id, user_id)`

| Vis | Method | Returns |
|---|---|---|
| `+` | `fee()` | `BelongsTo` |
| `+` | `user()` | `BelongsTo` |
| `+` | `confirmer()` | `BelongsTo` |

---

### 1.19 `Semester extends Model`

| Attribute | Datatype |
|---|---|
| id | `bigint` unsigned PK |
| label | `string` |
| start_date | `date` → cast `date` |
| end_date | `date` → cast `date` |
| is_current | `boolean` default `false` → cast `boolean` |
| created_at / updated_at | `timestamp` |

| Vis | Method | Returns |
|---|---|---|
| `+` | `memberStatuses()` | `HasMany` |
| `+` | `scopeCurrent($query)` | query scope |

---

### 1.20 `MemberSemesterStatus extends Model`

| Attribute | Datatype |
|---|---|
| id | `bigint` unsigned PK |
| club_member_id | `bigint` FK → club_members, cascade delete |
| semester_id | `bigint` FK → semesters |
| semester_status | `enum('active','inactive','dropped')` default `active` |
| updated_by | `bigint` FK → users |
| created_at / updated_at | `timestamp` |

| Vis | Method | Returns |
|---|---|---|
| `+` | `clubMember()` | `BelongsTo` |
| `+` | `semester()` | `BelongsTo` |
| `+` | `updatedBy()` | `BelongsTo` |

---

### 1.21 `College extends Model`

| Attribute | Datatype |
|---|---|
| id | `bigint` unsigned PK |
| name | `string` |
| created_at / updated_at | `timestamp` |

| Vis | Method | Returns |
|---|---|---|
| `+` | `clubs()` | `HasMany` |
| `+` | `users()` | `HasMany` |

---

### 1.22 `Course extends Model`

| Attribute | Datatype |
|---|---|
| id | `bigint` unsigned PK |
| slug | `string` unique |
| name | `string` |
| department_id | `bigint` FK → departments, cascade delete |
| created_at / updated_at | `timestamp` |

| Vis | Method | Returns |
|---|---|---|
| `+` | `department()` | `BelongsTo` |
| `+` | `users()` | `HasMany` |
| `+` | `clubs()` | `BelongsToMany` (pivot `club_course`) |

---

### 1.23 `Department extends Model`

| Attribute | Datatype |
|---|---|
| id | `bigint` unsigned PK |
| slug | `string` unique |
| name | `string` |
| short_name | `string(20)` |
| created_at / updated_at | `timestamp` |

| Vis | Method | Returns |
|---|---|---|
| `+` | `courses()` | `HasMany` |
| `+` | `users()` | `HasMany` |

---

### 1.24 `GoogleCalendarEvent extends Model`

One ClubSync record copied into one user's Google Calendar.

| Attribute | Datatype |
|---|---|
| id | `bigint` unsigned PK |
| user_id | `bigint` FK → users, cascade delete |
| source_type | `string(20)` — `activity` \| `fee` \| `semester` |
| source_id | `bigint` unsigned |
| slot | `string(20)` default `'main'` — a semester has `start` and `end` |
| google_event_id | `string` |
| payload_hash | `char(40)` — sha1 of the last payload sent |
| created_at / updated_at | `timestamp` |

Unique: `(user_id, source_type, source_id, slot)`. Index: `(source_type, source_id)`.

| Vis | Member | Type / Returns |
|---|---|---|
| `+` | `const ACTIVITY` / `FEE` / `SEMESTER` | `string` |
| `+` | `user()` | `BelongsTo` |

---

## 2. Controllers — `App\Http\Controllers`

### 2.1 `Controller` (base)
No attributes, no methods.

### 2.2 Auth — `App\Http\Controllers\Auth`

#### `LoginController extends Controller`
| Vis | Member | Type / Returns |
|---|---|---|
| `-` | `$auth` | `AuthManager` (constructor-promoted) |
| `-` | `$session` | `SessionManager` (constructor-promoted) |
| `+` | `__construct(AuthManager $auth, SessionManager $session)` | |
| `+` | `show()` | `View` |
| `+` | `authenticate(Request $request)` | `RedirectResponse` |
| `+` | `logout(Request $request)` | `RedirectResponse` |

#### `RegisterController extends Controller`
| Vis | Member | Type / Returns |
|---|---|---|
| `-` | `$auth` | `AuthManager` |
| `-` | `$session` | `SessionManager` |
| `+` | `__construct(AuthManager $auth, SessionManager $session)` | |
| `+` | `showStep1()` | `View` |
| `+` | `storeStep1(Request $request)` | `RedirectResponse` |
| `+` | `showDepartment()` | `RedirectResponse\|View` |
| `+` | `storeDepartment(Request $request)` | `RedirectResponse` |
| `+` | `showCourse()` | `RedirectResponse\|View` |
| `+` | `storeCourse(Request $request)` | `RedirectResponse` |

#### `ForgotPasswordController extends Controller`
| Vis | Member | Type / Returns |
|---|---|---|
| `-` | `$auth` | `AuthManager` |
| `+` | `__construct(AuthManager $auth)` | |
| `+` | `show()` | `View` |
| `+` | `send(Request $request)` | `RedirectResponse` |

---

### 2.3 Admin — `App\Http\Controllers\Admin`

#### `DashboardController extends Controller`
| Vis | Member | Type / Returns |
|---|---|---|
| `-` | `$acleMonitor` | `AcleMonitorService` |
| `+` | `__construct(AcleMonitorService $acleMonitor)` | |
| `+` | `index(Request $request)` | `View` |

#### `ClubController extends Controller`
| Vis | Method | Returns |
|---|---|---|
| `+` | `academic(Request $request)` | `View` |
| `+` | `nonAcademic(Request $request)` | `View` |
| `+` | `create()` | `View` |
| `+` | `store(Request $request)` | mixed |

#### `StorageController extends Controller`
| Vis | Member | Type / Returns |
|---|---|---|
| `-` | `$storage` | `DatabaseStorageService` |
| `+` | `__construct(DatabaseStorageService $storage)` | |
| `+` | `index()` | `View` |
| `+` | `archiveSemester(Semester $semester)` | `RedirectResponse` |
| `+` | `download(Request $request)` | `BinaryFileResponse` |
| `+` | `pruneAudit()` | `RedirectResponse` |

---

### 2.4 DSA — `App\Http\Controllers\DSA`

#### `DashboardController extends Controller`
| Vis | Member | Type / Returns |
|---|---|---|
| `-` | `$acleMonitor` | `AcleMonitorService` |
| `+` | `__construct(AcleMonitorService $acleMonitor)` | |
| `+` | `index(Request $request)` | `View` |

#### `ActivityController extends Controller`
| Vis | Method | Returns |
|---|---|---|
| `+` | `monitor(Request $request)` | `View` |
| `+` | `show(ClubActivity $event)` | `View` |
| `+` | `review()` | `View` |
| `+` | `approve(ClubActivity $event)` | `RedirectResponse` |
| `+` | `reject(Request $request, ClubActivity $event)` | `RedirectResponse` |
| `+` | `downloadLetter(ClubActivity $event)` | file response |

#### `ClubController extends Controller`
| Vis | Method | Returns |
|---|---|---|
| `+` | `index(Request $request)` | `View` |
| `+` | `create()` | `View` |
| `+` | `store(Request $request)` | `RedirectResponse` |
| `+` | `show(Club $club)` | `View` |
| `+` | `edit(Club $club)` | `View` |
| `+` | `update(Request $request, Club $club)` | `RedirectResponse` |

#### `ClubMemberController extends Controller`
| Vis | Method | Returns |
|---|---|---|
| `+` | `index(Club $club)` | `View` |

#### `ClubOfficerRecordController extends Controller`
| Vis | Method | Returns |
|---|---|---|
| `+` | `index(Club $club)` | `View` |

#### `ViolationController extends Controller`
| Vis | Method | Returns |
|---|---|---|
| `+` | `index()` | `View` |
| `+` | `show(AiNotificationQueue $violation)` | `View` |
| `+` | `approve(Request $request, AiNotificationQueue $violation)` | `RedirectResponse` |
| `+` | `dismiss(AiNotificationQueue $violation)` | `RedirectResponse` |

#### `ClubPerformanceController extends Controller`
How well each club has done over a semester. Replaced the Churn Risk Engine for the DSA.
| Vis | Member | Type / Returns |
|---|---|---|
| `-` | `$performance` | `ClubPerformanceService` |
| `+` | `index(Request $request)` | `View` |
| `+` | `show(Request $request, Club $club)` | `View` |
| `-` | `semesters(Request $request)` | `array` |

#### `ChedReportController extends Controller`
| Vis | Method | Returns |
|---|---|---|
| `+` | `index()` | `View` |
| `+` | `downloadPdf(ChedReport $report)` | file response |
| `+` | `downloadXlsx(ChedReport $report)` | file response |

---

### 2.5 Adviser — `App\Http\Controllers\Adviser`

#### `DashboardController extends Controller`
| Vis | Method | Returns |
|---|---|---|
| `+` | `index()` | `View` |

#### `AnnouncementController extends Controller`
| Vis | Method | Returns |
|---|---|---|
| `-` | `adviserClub()` | `?Club` |
| `+` | `index()` | `View` |
| `+` | `show(Announcement $announcement)` | `View` |
| `+` | `approve(Announcement $announcement)` | `RedirectResponse` |
| `+` | `requestRevision(Request $request, Announcement $announcement)` | `RedirectResponse` |
| `+` | `reject(Request $request, Announcement $announcement)` | `RedirectResponse` |

#### `NarrativeController extends Controller`
| Vis | Method | Returns |
|---|---|---|
| `-` | `adviserClub()` | `?Club` |
| `+` | `index()` | `View` |
| `+` | `show(ClubNarrative $narrative)` | `View` |
| `+` | `approve(Request $request, ClubNarrative $narrative)` | `RedirectResponse` |
| `+` | `discard(ClubNarrative $narrative)` | `RedirectResponse` |

---

### 2.6 Officer — `App\Http\Controllers\Officer`

#### `DashboardController extends Controller`
| Vis | Method | Returns |
|---|---|---|
| `+` | `index()` | `View` |

#### `ActivityController extends Controller`
| Vis | Member | Type / Returns |
|---|---|---|
| — | venue list | read from `ClubActivity::VENUES` — the main-campus rooms (AVR, EB/PC/SN/ST rooms, JD 1–2, Moot Court, PC Labs A–C, Library, YDC) |
| `+` | `const YEAR_LEVELS` | `array<int,string>` — 1→"1st Year" … 4→"4th Year" |
| `-` | `$firebase` | `FirebaseService` |
| `+` | `__construct(FirebaseService $firebase)` | |
| `-` | `normalizeYearLevels(Request $request)` | `?array` |
| `-` | `officerClub()` | `?Club` |
| `+` | `index(Request $request)` | `View` |
| `+` | `create()` | `View` |
| `+` | `store(Request $request)` | `RedirectResponse` |
| `+` | `show(ClubActivity $event)` | `View` |
| `+` | `edit(ClubActivity $event)` | `View` |
| `+` | `update(Request $request, ClubActivity $event)` | `RedirectResponse` |
| `+` | `downloadLetter(ClubActivity $event)` | file response |
| `+` | `complete(Request $request, ClubActivity $event)` | `RedirectResponse` |

#### `AnnouncementController extends Controller`
| Vis | Member | Type / Returns |
|---|---|---|
| `-` | `$gemini` | `GeminiService` |
| `+` | `__construct(GeminiService $gemini)` | |
| `-` | `officerClub()` | `?Club` |
| `+` | `index()` | `View` |
| `+` | `create()` | `View` |
| `+` | `store(Request $request)` | `RedirectResponse` |
| `+` | `show(Announcement $announcement)` | `View` |
| `+` | `edit(Announcement $announcement)` | `View` |
| `+` | `update(Request $request, Announcement $announcement)` | `RedirectResponse` |
| `+` | `submit(Announcement $announcement)` | `RedirectResponse` |
| `+` | `aiDraft(Request $request)` | `JsonResponse` |

#### `AttendanceController extends Controller`
| Vis | Method | Returns |
|---|---|---|
| `-` | `officerClub()` | `?Club` |
| `+` | `index(ClubActivity $event)` | `View` |
| `+` | `record(Request $request, ClubActivity $event, User $user)` | `RedirectResponse` |

#### `FeeController extends Controller`
| Vis | Method | Returns |
|---|---|---|
| `-` | `officerClub()` | `?Club` |
| `+` | `index()` | `View` |
| `+` | `create()` | `View` |
| `+` | `store(Request $request)` | `RedirectResponse` |
| `+` | `show(Fee $fee)` | `View` |
| `+` | `edit(Fee $fee)` | `View` |
| `+` | `update(Request $request, Fee $fee)` | `RedirectResponse` |
| `+` | `markPaid(Fee $fee, User $user)` | `RedirectResponse` |
| `+` | `markUnpaid(Fee $fee, User $user)` | `RedirectResponse` |

#### `ClubController extends Controller`
| Vis | Method | Returns |
|---|---|---|
| `+` | `index()` | `View` |
| `+` | `show(Club $club)` | `View` |
| `+` | `join(Club $club)` | `RedirectResponse` |

#### `ChedReportController extends Controller`
| Vis | Method | Returns |
|---|---|---|
| `-` | `officerClub()` | `?Club` |
| `-` | `authorize(ChedReport $report)` | `void` |
| `+` | `update(Request $request, ChedReport $report)` | `RedirectResponse` |
| `+` | `finalize(ChedReport $report)` | `RedirectResponse` |
| `+` | `downloadPdf(ChedReport $report)` | file response |
| `+` | `downloadXlsx(ChedReport $report)` | file response |

---

### 2.7 Member — `App\Http\Controllers\Member`

#### `DashboardController extends Controller`
| Vis | Method | Returns |
|---|---|---|
| `+` | `index()` | `View` |

#### `ActivityController extends Controller`
| Vis | Method | Returns |
|---|---|---|
| `+` | `index()` | `View` |

#### `ClubController extends Controller`
| Vis | Method | Returns |
|---|---|---|
| `+` | `index(Request $request)` | `View` |
| `+` | `show(Club $club)` | `View` |
| `+` | `join(Club $club)` | `RedirectResponse` |
| `+` | `leave(Club $club)` | `RedirectResponse` |

#### `BulletinController extends Controller`
| Vis | Method | Returns |
|---|---|---|
| `+` | `index()` | `View` |
| `+` | `show(Announcement $announcement)` | `View` |
| `+` | `like(Announcement $announcement)` | `RedirectResponse` |
| `+` | `comment(Request $request, Announcement $announcement)` | `RedirectResponse` |

#### `NotificationController extends Controller`
| Vis | Method | Returns |
|---|---|---|
| `+` | `index()` | `View` |
| `+` | `markRead(ClubNotification $notification)` | `RedirectResponse` |

---

### 2.8 Shared (root namespace)

#### `ClubMemberController extends Controller`
| Vis | Member | Type / Returns |
|---|---|---|
| `-` | `$firebase` | `FirebaseService` |
| `+` | `__construct(FirebaseService $firebase)` | |
| `-` | `currentClub()` | `?Club` |
| `-` | `authorizeAdviser(ClubMember $member)` | `void` |
| `-` | `mirrorAndNotify(ClubMember $member, string $decision)` | `void` |
| `+` | `index()` | `View` |
| `+` | `store(Request $request)` | `RedirectResponse` |
| `+` | `approve(ClubMember $member)` | `RedirectResponse` |
| `+` | `reject(Request $request, ClubMember $member)` | `RedirectResponse` |

#### `ClubOfficerRecordController extends Controller`
| Vis | Method | Returns |
|---|---|---|
| `-` | `currentClub()` | `?Club` |
| `-` | `rules()` | `array` — validation rules |
| `-` | `requireAdviser()` | `void` |
| `+` | `index()` | `View` |
| `+` | `create()` | `View` |
| `+` | `store(Request $request)` | `RedirectResponse` |
| `+` | `edit(ClubOfficerRecord $record)` | `View` |
| `+` | `update(Request $request, ClubOfficerRecord $record)` | `RedirectResponse` |
| `+` | `archive(ClubOfficerRecord $record)` | `RedirectResponse` |

#### `ClubViolationController extends Controller`
| Vis | Method | Returns |
|---|---|---|
| `-` | `currentClub()` | `?Club` |
| `+` | `index()` | `View` |
| `+` | `resolve(Request $request, AiNotificationQueue $violation)` | `RedirectResponse` |

#### `MemberPresenceController extends Controller`
| Vis | Method | Returns |
|---|---|---|
| `-` | `currentClub()` | `?Club` |
| `+` | `index()` | `View` |
| `+` | `update(Request $request, ClubMember $member)` | `RedirectResponse` |
| `+` | `notifyDsa()` | `RedirectResponse` |

#### `ChurnRiskController extends Controller`
| Vis | Member | Type / Returns |
|---|---|---|
| `-` | `$churn` | `ChurnRiskService` |
| `+` | `__construct(ChurnRiskService $churn)` | |
| `+` | `index(Request $request)` | `View` |

#### `ProfileController extends Controller`
| Vis | Method | Returns |
|---|---|---|
| `-` | `layoutForRole(string $role)` | `string` |
| `+` | `show()` | `View` |
| `+` | `updatePhoto(Request $request)` | `RedirectResponse` |
| `+` | `deletePhoto()` | `RedirectResponse` |

---

## 3. Services — `App\Services`

### 3.1 `AuthManager`
| Vis | Member | Type / Returns |
|---|---|---|
| `-` | `$apiKey` | `string` |
| `+` | `__construct()` | |
| `+` | `loginWithFirebase(string $email, string $password, bool $remember)` | `array` |
| `+` | `registerWithFirebase(string $email, string $password)` | `array` |
| `+` | `resolveUserRole(string $uid)` | `string` |
| `+` | `refreshIdToken(string $refreshToken)` | `string` |
| `+` | `decodeIdToken(string $idToken)` | `array` |
| `+` | `isTokenExpired(string $idToken)` | `bool` |
| `+` | `verifyIdToken(string $idToken)` | `array` |
| `+` | `updatePassword(string $idToken, string $newPassword)` | `void` |
| `+` | `sendPasswordResetEmail(string $email)` | `void` |
| `+` | `revokeSession()` | `void` |

### 3.2 `SessionManager`
| Vis | Method | Returns |
|---|---|---|
| `+` | `store(string $idToken, string $uid, string $role, int $userId, bool $isAdmin = false, ?string $refreshToken = null)` | `void` |
| `+` | `updateToken(string $idToken)` | `void` |
| `+` | `redirectByRole(string $role)` | `RedirectResponse` |

### 3.3 `FirebaseService`
| Vis | Member | Type / Returns |
|---|---|---|
| `-` | `$databaseUrl` | `string` |
| `+` | `__construct()` | |
| `+` | `writeEvent(int $clubId, int $eventId, array $data)` | `void` |
| `+` | `writePost(int $clubId, int $postId, array $data)` | `void` |
| `+` | `writeMemberStatus(int $clubId, int $memberId, array $data)` | `void` |
| `+` | `writeNotification(string $userUid, int $notifId, array $data)` | `void` |
| `+` | `writeAiQueueItem(int $queueId, array $data)` | `void` |
| `+` | `writeViolation(int $clubId, int $violationId, array $data)` | `void` |
| `+` | `delete(string $path)` | `void` |
| `-` | `put(string $path, array $data)` | `void` |

### 3.4 `GeminiService`
| Vis | Member | Type / Returns |
|---|---|---|
| `-` | `$apiKey` | `string` |
| `-` | `$model` | `string` |
| `-` | `$baseUrl` | `string` |
| `+` | `__construct()` | |
| `+` | `isAvailable()` | `bool` |
| `+` | `draftAnnouncement(string $title, string $type, string $clubName, string $context = '')` | `?string` |
| `+` | `draftClubNarrative(ClubActivity $activity, Club $club)` | `?string` |
| `+` | `generateViolationStatement(AiNotificationQueue $violation, Club $club)` | `?string` |

### 3.5 `GoogleCalendarService`
| Vis | Member | Type / Returns |
|---|---|---|
Acts as one Workspace user (`$subject`, via domain-wide delegation) and writes to that user's primary calendar. API errors throw so the job can retry.

| Vis | Member | Type / Returns |
|---|---|---|
| `-` | `const SCOPE` | `string` = `'https://www.googleapis.com/auth/calendar.events'` |
| `-` | `const API_BASE` | `string` = `'https://www.googleapis.com/calendar/v3/calendars/primary/events'` |
| `-` | `$config` | `array` |
| `+` | `__construct()` | |
| `+` | `enabled()` | `bool` |
| `+` | `canSyncFor(?string $email)` | `bool` — institutional domain only |
| `+` | `createEvent(string $subject, array $event)` | `string` event id |
| `+` | `updateEvent(string $subject, string $eventId, array $event)` | `bool` — false when the event is gone (404/410) |
| `+` | `deleteEvent(string $subject, string $eventId)` | `void` |
| `-` | `client(string $subject)` | `PendingRequest` |
| `-` | `throwUnlessSuccessful(Response $response, string $action)` | `void` |
| `-` | `accessToken(string $subject)` | `string` — cached 50 min per subject |
| `-` | `buildSignedJwt(array $key, string $tokenUri, string $subject)` | `string` |
| `-` | `base64UrlEncode(string $data)` | `string` |

### 3.5a `GoogleCalendarSync`

Decides who gets which event and reconciles each user's copies.

| Vis | Member | Type / Returns |
|---|---|---|
| `+` | `__construct(GoogleCalendarService $calendar)` | |
| `+` | `recipientIds(string $type, int $id)` | `Collection<int>` |
| `+` | `entries(string $type, int $id)` | `array<string slot, array payload>` |
| `+` | `syncUsers(string $type, int $id, array $userIds)` | `array` — ids of users that failed |
| `-` | `syncUser(User $user, string $type, int $id, array $wanted, Collection $mirrors)` | `void` |
| `-` | `syncableUsers()` | `Builder` — unsuspended, institutional domain |
| `-` | `activityAudience(int $id)` | `Collection` — targeted active members + officers + adviser |
| `-` | `activityEntries(?ClubActivity)` / `feeEntries(?Fee)` / `semesterEntries(?Semester)` | `array` |
| `-` | `allDay(string $type, int $id, Carbon $date, array $fields, string $slot = 'main')` | `array` |
| `-` | `payload(string $type, int $id, array $fields, string $slot = 'main')` | `array` |
| `-` | `timezone()` | `string` |

### 3.5b `ClubPerformanceService`
Club-level semester score for the DSA (activities 30, turnout 25, presence 15, fees 15, reports 15; −5 per unresolved compliance notice).
| Vis | Member | Type / Returns |
|---|---|---|
| `+` | `const WEIGHTS` | `array` |
| `+` | `const STANDINGS` | `array` |
| `+` | `forClubs(Collection $clubs, Semester $semester)` | `Collection` |
| `+` | `forClub(Club $club, Semester $semester)` | `array` |
| `+` | `summarize(Collection $rows)` | `array` |
| `+` | `months(Semester $semester)` | `array` |
| `-` | `window(Semester $semester)` | `array` |
| `-` | `profile(Club $club, Semester $semester, array $window, array $d)` | `array` |

### 3.6 `ChurnRiskService` (officers only)
| Vis | Member | Type / Returns |
|---|---|---|
| `+` | `const HIGH` | `int` = `67` |
| `+` | `const MEDIUM` | `int` = `34` |
| `+` | `forClub(Club $club, ?Semester $semester = null)` | `Collection` |
| `-` | `profile(ClubMember $member, int $totalActivities, int $attended, int $unpaid, ?string $presence)` | `array` |
| `-` | `tag(int $score)` | `string` |
| `+` | `summarize(Collection $rows)` | `array` |

### 3.7 `DatabaseStorageService`
| Vis | Method | Returns |
|---|---|---|
| `+` | `databaseBytes()` | `int` |
| `+` | `capBytes()` | `int` |
| `+` | `usedPercent()` | `float` |
| `+` | `level()` | `string` |
| `+` | `tableSizes(int $limit = 12)` | `Collection` |
| `+` | `status()` | `array` |
| `+` | `pruneTransient()` | `array` |
| `+` | `archivableSemesters()` | `Collection` |
| `+` | `isArchivable(Semester $semester)` | `bool` |

### 3.8 `AcleMonitorService`
| Vis | Method | Returns |
|---|---|---|
| `+` | `build(Request $request)` | `array` |

---

## 4. Jobs (queued) — `App\Jobs`

All implement `ShouldQueue` and use `Dispatchable`, `InteractsWithQueue`, `Queueable`, `SerializesModels`.

| Class | Vis | Member | Type / Returns |
|---|---|---|---|
| `DetectClubViolationsJob` | `+` | `handle()` | `void` |
| | `-` | `alreadyOpen(Club $club, string $gapType)` | `bool` |
| | `-` | `raise(Club $club, string $gapType, string $description)` | `void` |
| | `-` | `checkOverduePresenceUpdate(Club $club)` | `void` |
| | `-` | `checkMissingChedReport(Club $club)` | `void` |
| | `-` | `checkOverdueActivityRequest(Club $club)` | `void` |
| | `-` | `checkUnupdatedOfficerInfo(Club $club)` | `void` |
| `GenerateChedReportJob` | `-` | `$activityId` | `int` |
| | `+` | `__construct(int $activityId)` / `handle()` | `void` |
| `GenerateClubNarrativeJob` | `-` | `$activityId` | `int` |
| | `+` | `__construct(int $activityId)` / `handle(GeminiService $gemini)` | `void` |
| `GenerateViolationStatementJob` | `-` | `$violationId` | `int` |
| | `+` | `__construct(int $violationId)` / `handle(GeminiService $gemini)` | `void` |
| `SendViolationNotificationJob` | `-` | `$violationId` | `int` |
| | `+` | `__construct(int $violationId)` / `handle(FirebaseService $firebase)` | `void` |
| `NotifyAdviserOfMemberSubmission` | `-` | `$clubId, $memberCount, $submittedBy` | `int, int, int` |
| | `+` | `__construct(...)` / `handle()` | `void` |
| `NotifyClubOfMemberDecision` | `-` | `$clubMemberId, $decision` | `int, string` |
| | `+` | `__construct(...)` / `handle()` | `void` |
| `NotifyDsaOfActivitySubmission` | `-` | `$activityId` | `int` |
| | `+` | `__construct(int $activityId)` / `handle()` | `void` |
| `NotifyDsaOfPresenceSubmission` | `-` | `$clubId, $semesterId, $submittedBy` | `int, int, int` |
| | `+` | `__construct(...)` / `handle()` | `void` |
| `NotifyDsaOfViolationResolution` | `-` | `$violationId` | `int` |
| | `+` | `__construct(int $violationId)` / `handle()` | `void` |
| `NotifyMembersOfAnnouncement` | `-` | `$announcementId` | `int` |
| | `+` | `__construct(int $announcementId)` / `handle()` | `void` |
| `NotifyOfficersOfActivityDecision` | `-` | `$activityId, $decision` | `int, string` |
| | `+` | `__construct(...)` / `handle()` | `void` |
| `SemesterPresenceReminderJob` | `+` | `handle()` | `void` |
| `SyncToGoogleCalendars` | `-` | `const BATCH_SIZE` | `int` = `25` |
| | `+` | `__construct(string $sourceType, int $sourceId)` | |
| | `+` | `handle(GoogleCalendarService $calendar, GoogleCalendarSync $sync)` | `void` — fans out to per-user batches |
| `SyncGoogleCalendarUsers` | `-` | `const MAX_ATTEMPTS` | `int` = `3` |
| | `+` | `__construct(string $sourceType, int $sourceId, array $userIds, int $attempt = 1)` | |
| | `+` | `handle(GoogleCalendarSync $sync)` | `void` — re-dispatches failed users with a delay |
| `SyncClubMemberGoogleCalendar` | `+` | `__construct(int $userId, int $clubId)` | |
| | `+` | `handle(GoogleCalendarService $calendar)` | `void` |

---

## 5. Middleware — `App\Http\Middleware`

### `FirebaseTokenMiddleware`
| Vis | Member | Type / Returns |
|---|---|---|
| `-` | `$auth` | `AuthManager` |
| `-` | `$session` | `SessionManager` |
| `+` | `__construct(AuthManager $auth, SessionManager $session)` | |
| `+` | `handle(Request $request, Closure $next)` | `Response` |
| `-` | `unauthenticated(Request $request)` | `Response` |
| `-` | `sessionExpired(Request $request)` | `Response` |

### `RoleMiddleware`
| Vis | Method | Returns |
|---|---|---|
| `+` | `handle(Request $request, Closure $next, string ...$roles)` | `Response` |

### `EnsureAdmin`
| Vis | Method | Returns |
|---|---|---|
| `+` | `handle(Request $request, Closure $next)` | `Response` |

### `RedirectIfAuthenticated`
| Vis | Member | Type / Returns |
|---|---|---|
| `-` | `$session` | `SessionManager` |
| `+` | `__construct(SessionManager $session)` | |
| `+` | `handle(Request $request, Closure $next)` | `Response` |

### `HandleInertiaRequests extends Middleware`
| Vis | Member | Type / Returns |
|---|---|---|
| `#` | `$rootView` | `string` = `'app'` |
| `+` | `version(Request $request)` | `?string` |
| `+` | `share(Request $request)` | `array` |

---

## 6. Console Commands — `App\Console\Commands`

### `RetentionCheck extends Command`
| Vis | Member | Type / Returns |
|---|---|---|
| `#` | `$signature` | `string` = `'retention:check'` |
| `#` | `$description` | `string` |
| `+` | `handle(DatabaseStorageService $storage)` | `int` |
| `-` | `requestAdminCleanup(array $status)` | `void` |

### `SyncGoogleCalendars extends Command`
| Vis | Member | Type / Returns |
|---|---|---|
| `#` | `$signature` | `string` = `'google-calendar:sync {--only=*}'` |
| `#` | `$description` | `string` |
| `+` | `handle(GoogleCalendarService $calendar)` | `int` |
| `-` | `upcoming(string $type)` | `Collection<int>` |

### `SeedAdmin extends Command`
| Vis | Member | Type / Returns |
|---|---|---|
| `#` | `$signature` | `string` = `'app:seed-admin {--email=} {--password=} {--first-name=} {--last-name=} {--edp-number=} {--firebase-uid=}'` |
| `#` | `$description` | `string` |
| `+` | `handle()` | `int` |

### `SeedClubPresident extends Command`
| Vis | Member | Type / Returns |
|---|---|---|
| `#` | `$signature` | `string` = `'app:seed-club-president {--email=} {--password=} {--first-name=} {--last-name=} {--birthday=} {--edp-number=} {--club-slug=} {--course-name=} {--department-slug=} {--firebase-uid=}'` |
| `#` | `$description` | `string` |
| `+` | `handle()` | `int` |

---

## 7. Events, Mail, Exports

### `NotificationCreated implements ShouldBroadcastNow` — `App\Events`
Uses `Dispatchable`, `InteractsWithSockets`, `SerializesModels`.

| Vis | Member | Type / Returns |
|---|---|---|
| `+` | `$notification` | `ClubNotification` (constructor-promoted) |
| `+` | `__construct(ClubNotification $notification)` | |
| `+` | `broadcastOn()` | `PrivateChannel` |
| `+` | `broadcastAs()` | `string` |
| `+` | `broadcastWith()` | `array` |

### `AnnouncementPublished extends Mailable implements ShouldQueue` — `App\Mail`
Uses `Queueable`, `SerializesModels`.

| Vis | Member | Type / Returns |
|---|---|---|
| `+` | `$announcement` | `Announcement` |
| `+` | `$clubName` | `string` |
| `+` | `$url` | `?string` (default `null`) |
| `+` | `__construct(Announcement $announcement, string $clubName, ?string $url = null)` | |
| `+` | `envelope()` | `Envelope` |
| `+` | `content()` | `Content` |

### `ArchiveWorkbook implements WithMultipleSheets` — `App\Exports`
| Vis | Member | Type / Returns |
|---|---|---|
| `-` | `$sheets` | `array<int, array{title:string, headings:array, rows:array}>` |
| `+` | `__construct(array $sheets)` | |
| `+` | `sheets()` | `array` |

### `ArchiveSheet implements FromArray, WithHeadings, WithTitle` — `App\Exports`
| Vis | Member | Type / Returns |
|---|---|---|
| `-` | `$title` | `string` |
| `-` | `$headings` | `array` |
| `-` | `$rows` | `array` |
| `+` | `__construct(string $title, array $headings, array $rows)` | |
| `+` | `array()` | `array` |
| `+` | `headings()` | `array` |
| `+` | `title()` | `string` |

### `ChedReportExport implements FromArray, WithHeadings` — `App\Exports`
| Vis | Member | Type / Returns |
|---|---|---|
| `-` | `$data` | `array` |
| `+` | `__construct(array $data)` | |
| `+` | `headings()` | `array` |
| `+` | `array()` | `array` |

---

## 8. Infrastructure

### `AppServiceProvider extends ServiceProvider` — `App\Providers`
| Vis | Method | Returns |
|---|---|---|
| `+` | `register()` | `void` |
| `+` | `boot()` | `void` |

### `ResilientPostgresConnector extends PostgresConnector` — `App\Database`
| Vis | Method | Returns |
|---|---|---|
| `+` | `connect(array $config)` | `PDO` |
| `#` | `getDsn(array $config)` | `string` |
| `#` | `ensureReachable(array $config)` | `void` |

### `Connectivity` — `App\Support`
| Vis | Member | Type / Returns |
|---|---|---|
| `+` | `const CONNECTION_SQLSTATES` | `array<string>` — 08000, 08001, 08003, 08004, 08006, 08007, 08P01, 57P01, 57P02, 57P03 |
| `+` | `const CONNECTION_MESSAGES` | `array<string>` — network/DNS/curl failure fragments |
| `+$` | `isConnectivityFailure(?Throwable $e)` | `bool` — static |
| `-$` | `messageIndicatesConnectionLoss(string $message)` | `bool` — static |

---

## 9. Database Layer

### Seeders — `Database\Seeders`
| Class | Vis | Member | Type / Returns |
|---|---|---|---|
| `DatabaseSeeder extends Seeder` | `+` | `run()` | `void` |
| `AdminSeeder extends Seeder` | `+` | `run()` | `void` |
| `DepartmentSeeder extends Seeder` | `+` | `run()` | `void` |
| `ClubSeeder extends Seeder` | `+` | `run()` | `void` |
| `AdviserSeeder extends Seeder` | `-` | `$lastNames` | `array<string>` (42 entries) |
| | `-` | `$firstNames` | `array<string>` (42 entries) |
| | `+` | `run()` | `void` |
| | `-` | `passwordFor(string $email, string $slug)` | `string` |
| | `-` | `recoverUid(string $email, string $password)` | `?string` |
| | `-` | `report(array $created, array $manual)` | `void` |

### Factory — `Database\Factories`
#### `UserFactory extends Factory`
| Vis | Member | Type / Returns |
|---|---|---|
| `#$` | `$password` | `?string` — static |
| `+` | `definition()` | `array` |
| `+` | `unverified()` | `static` |

### Migrations — 45 anonymous classes
All follow `return new class extends Migration` with:

| Vis | Method | Returns |
|---|---|---|
| `+` | `up()` | `void` |
| `+` | `down()` | `void` |

<details>
<summary>Migration file list (45)</summary>

`0001_01_01_000000_create_users_table` · `0001_01_01_000001_create_cache_table` · `0001_01_01_000002_create_jobs_table` · `2024_01_01_000010_create_departments_table` · `2024_01_01_000011_create_courses_table` · `2024_01_01_000012_add_profile_fields_to_users_table` · `2024_01_01_000013_add_is_admin_to_users_table` · `2024_01_01_000014_create_clubs_table` · `2024_01_01_000015_create_club_members_table` · `2024_01_01_000020_create_colleges_table` · `2024_01_01_000021_add_firebase_fields_to_users_table` · `2024_01_01_000022_update_clubs_table` · `2024_01_01_000023_update_club_members_table` · `2024_01_01_000024_create_club_officers_table` · `2024_01_01_000025_create_events_table` · `2024_01_01_000026_create_attendance_table` · `2024_01_01_000027_create_announcements_table` · `2024_01_01_000028_create_fees_table` · `2024_01_01_000029_create_fee_payments_table` · `2024_01_01_000030_create_notifications_table` · `2024_01_01_000031_create_ai_notification_queue_table` · `2024_01_01_000032_create_compliance_scan_log_table` · `2024_01_01_000033_create_compliance_items_table` · `2024_01_01_000034_create_announcement_likes_table` · `2024_01_01_000035_create_announcement_comments_table` · `2024_01_01_000036_create_club_course_table` · `2024_01_01_000037_rename_events_to_club_activities` · `2024_01_01_000038_add_registration_workflow_to_club_members_table` · `2024_01_01_000039_create_semesters_table` · `2024_01_01_000040_create_member_semester_statuses_table` · `2024_01_01_000041_add_tiered_approval_to_club_activities_table` · `2024_01_01_000042_create_activity_change_logs_table` · `2024_01_01_000043_create_club_officer_records_table` · `2024_01_01_000044_create_ched_reports_table` · `2024_01_01_000045_add_completed_at_to_club_activities_table` · `2024_01_01_000046_add_violation_workflow_to_ai_notification_queue_table` · `2024_01_01_000047_make_draft_content_nullable_on_ai_notification_queue` · `2024_01_01_000048_create_club_narratives_table` · `2024_01_01_000049_create_audit_logs_table` · `2024_01_01_000050_add_banner_image_to_clubs_table` · `2024_01_01_000060_add_google_event_id_to_club_activities` · `2024_01_01_000061_add_employee_number_to_users_table` · `2024_01_01_000062_make_edp_number_nullable_on_users_table` · `2024_01_01_000063_add_year_level_to_users_table` · `2024_01_01_000064_add_target_year_levels_to_club_activities_table`

</details>

---

## 10. Tests — `Tests`

| Class | Members |
|---|---|
| `TestCase extends BaseTestCase` | (no declared members) |
| `AuditLogChainTest` | Pest-style test file (closures, no class) |
| `OfflineFallbackTest` | Pest-style test file |
| `Feature\ExampleTest`, `Unit\ExampleTest` | Pest-style test files |

---

## 11. Global Functions — `app/helpers.php`

Not a class; these are globally autoloaded procedural helpers (all effectively `public`).

| Vis | Function | Returns |
|---|---|---|
| `+` | `auth_role()` | `string` — session `firebase_user_role` |
| `+` | `auth_uid()` | `string` — session `firebase_uid` |
| `+` | `auth_user_id()` | `int` — session `firebase_user_id` |
| `+` | `auth_is_admin()` | `bool` — session `firebase_is_admin` |
| `+` | `year_level_label(int $level)` | `string` — 1 → "1st Year" … 4 → "4th Year" |

---

## Tables Without a Model

Three tables exist in migrations but have no corresponding Eloquent class — they are queried directly or are framework-owned:

| Table | Columns |
|---|---|
| `compliance_items` | id, club_id (FK), item_type `enum('document','post_event_report','membership_application','officer_vacancy')`, reference_id `unsignedBigInteger` nullable, due_date `date` nullable, resolved `boolean` |
| `compliance_scan_log` | id, scanned_at `timestamp`, gaps_found `integer` default 0, all_clear `boolean` default false |
| `club_course` (pivot) | club_id, course_id — composite PK |

Framework tables: `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `sessions`, `password_reset_tokens`.

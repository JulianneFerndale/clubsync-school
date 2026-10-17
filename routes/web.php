<?php

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

// Public pages
Route::get('/', fn () => view('start'))->name('start');
Route::get('/welcome', fn () => view('welcome'))->name('welcome');
Route::get('/terms', fn () => view('terms'))->name('terms');

// Offline fallback page (served by the service worker when a navigation fails).
// Kept public + dependency-free so it always renders without a network connection.
Route::get('/offline', fn () => view('offline'))->name('offline');

// Health probe used by the on-screen connection banner and the offline page's
// auto-reload. It confirms the app can actually serve data: a cheap "SELECT 1"
// verifies the Supabase database is reachable. Returns 204 when healthy, 503 when
// the database (i.e. the internet) is unavailable — so the client never reloads
// into a fatal error. The service worker is configured to never cache this route.
Route::get('/ping', function () {
    try {
        \Illuminate\Support\Facades\DB::connection()->getPdo();
        \Illuminate\Support\Facades\DB::select('select 1');

        return response()->noContent(); // 204 — reachable
    } catch (\Throwable) {
        return response()->json(['status' => 'unreachable'], 503, ['Retry-After' => 10]);
    }
})->name('ping');

// Guest-only auth routes
Route::middleware('guest.firebase')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::get('/register', [RegisterController::class, 'showStep1'])->name('register');
    Route::get('/register/department', [RegisterController::class, 'showDepartment'])->name('register.department');
    Route::get('/register/course', [RegisterController::class, 'showCourse'])->name('register.course');
    Route::get('/register/verify', [RegisterController::class, 'showVerify'])->name('register.verify');
    Route::get('/forgot-password',  [ForgotPasswordController::class, 'show'])->name('forgot-password');
    // The reset link in Firebase's email opens here, carrying a single-use oobCode.
    Route::get('/reset-password',   [ResetPasswordController::class, 'show'])->name('reset-password');

    // Live "is this address already ours?" check behind the register email field.
    // Its own throttle: someone typing an address legitimately hits it more often
    // than they submit a form, but it still answers whether an account exists.
    Route::post('/register/check-email', [RegisterController::class, 'checkEmail'])
        ->middleware('throttle:20,1,register-email-check')
        ->name('register.check-email');

    // Throttled: these hit Firebase and/or mutate the users table, so they're the
    // targets for credential-stuffing / brute-force / registration-spam attempts.
    //
    // Every throttle in this file names its own bucket (the third argument). Without
    // one, Laravel keys a throttle by IP alone — sign-in here is Firebase, so there is
    // never a Laravel user to key by — and every throttled route shares one counter:
    // the email check fired while typing, /live/counts polling and chat all used up
    // this 5-a-minute allowance, and a student got 429 Too Many Requests part-way
    // through the registration wizard.
    Route::post('/login', [LoginController::class, 'authenticate'])->middleware('throttle:5,1,login');
    Route::post('/register', [RegisterController::class, 'storeStep1'])->middleware('throttle:5,1,register');
    Route::post('/register/department', [RegisterController::class, 'storeDepartment'])->middleware('throttle:5,1,register-department');
    Route::post('/register/course', [RegisterController::class, 'storeCourse'])->middleware('throttle:5,1,register-course');
    // Each attempt sends a document to Gemini, so it is limited like the steps above.
    Route::post('/register/verify', [RegisterController::class, 'storeVerify'])->middleware('throttle:5,1,register-verify');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'send'])->middleware('throttle:5,1,forgot-password');
    // Confirms the new password against Firebase, so it is brute-forceable — throttle it.
    Route::post('/reset-password',  [ResetPasswordController::class, 'update'])->middleware('throttle:5,1,reset-password')->name('reset-password.update');

    // "Continue with Google" — SCC Google Workspace accounts only (POLICY.md §Authentication).
    // The callback and the password confirmation reach Firebase, so they are limited too.
    Route::get('/auth/google', [GoogleController::class, 'redirect'])->middleware('throttle:10,1,google-redirect')->name('auth.google');
    Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->middleware('throttle:10,1,google-callback')->name('auth.google.callback');
    Route::get('/auth/google/link', [GoogleController::class, 'showLink'])->name('auth.google.link');
    Route::post('/auth/google/link', [GoogleController::class, 'link'])->middleware('throttle:5,1,google-link');
    // Leave the Google registration form for the email-and-password one. A POST, so a
    // Turbo hover prefetch can never drop the Google account someone just chose.
    Route::post('/register/google/cancel', [RegisterController::class, 'cancelGoogle'])->name('register.google.cancel');
});

Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware('firebase.token');

// ─── Course selection for a signed-in student who has none ──────────────────
// The course is what queues a student for their academic club, so an account
// without one is held here by the course.required middleware until it is answered.
Route::middleware('firebase.token')->group(function () {
    Route::get('/onboarding/course',  [\App\Http\Controllers\Onboarding\CourseController::class, 'show'])->name('onboarding.course');
    Route::post('/onboarding/course', [\App\Http\Controllers\Onboarding\CourseController::class, 'store'])->name('onboarding.course.store');
});

// ─── Proof of enrollment for a signed-in student still waiting on it ─────────
// RequireEnrollmentVerification holds a student here while their COE / school ID is
// with the DSA, or after it was turned down, until it is verified.
Route::middleware('firebase.token')->group(function () {
    Route::get('/verification',  [\App\Http\Controllers\Onboarding\VerificationController::class, 'show'])->name('verification.show');
    Route::post('/verification', [\App\Http\Controllers\Onboarding\VerificationController::class, 'store'])
        ->middleware('throttle:5,1,enrollment-verify')->name('verification.store');
    // A course picked by mistake, corrected before the next document is checked.
    Route::get('/verification/course',  [\App\Http\Controllers\Onboarding\VerificationController::class, 'editCourse'])->name('verification.course');
    Route::post('/verification/course', [\App\Http\Controllers\Onboarding\VerificationController::class, 'updateCourse'])
        ->middleware('throttle:5,1,enrollment-course')->name('verification.course.update');
});

// ─── Profile (all authenticated roles) ───────────────────────────────────────
Route::middleware('firebase.token')->group(function () {
    Route::get('/profile',          [ProfileController::class, 'show'])->name('profile');
    Route::post('/profile/photo',   [ProfileController::class, 'updatePhoto'])->name('profile.photo');
    Route::delete('/profile/photo', [ProfileController::class, 'deletePhoto'])->name('profile.photo.delete');

    // Throttled: verifies the current password against Firebase, so it's brute-forceable.
    Route::post('/profile/password', [PasswordController::class, 'update'])
        ->middleware('throttle:5,1,password-change')->name('profile.password.update');
});

// ─── Notifications (all authenticated roles) ─────────────────────────────────
// Members also have member.notifications.* inside the member chrome; these serve every
// other layout (officer pages, adviser, DSA, admin), so no role is left without a way to
// read a notification it missed live.
Route::middleware('firebase.token')->controller(NotificationController::class)->group(function () {
    Route::get('/notifications', 'index')->name('notifications.index');
    Route::post('/notifications/{notification}/read', 'markRead')->name('notifications.read');

    // Unread badge counts, re-read by the browser after Back, a reconnect or returning to the tab.
    Route::get('/live/counts', 'counts')->middleware('throttle:120,1,live-counts')->name('live.counts');
});

// ─── Private messaging (all authenticated roles) ─────────────────────────────
// Participant checks live in ChatController — a non-participant always gets 404.
Route::middleware('firebase.token')->prefix('chat')->name('chat.')->controller(ChatController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/privacy/acknowledge', 'acknowledge')->name('privacy.acknowledge');

    // Throttled: the directory must not be scraped by scripting the search box.
    Route::get('/people', 'search')->middleware('throttle:60,1,people-search')->name('people.search');
    Route::post('/people/{user}/block', 'block')->name('people.block');
    Route::delete('/people/{user}/block', 'unblock')->name('people.unblock');

    Route::post('/conversations', 'start')->middleware('throttle:20,1,chat-start')->name('start');

    Route::whereNumber('conversation')->scopeBindings()->group(function () {
        Route::get('/{conversation}', 'show')->name('show');
        Route::delete('/{conversation}', 'clear')->name('clear');
        Route::get('/{conversation}/messages', 'messages')->middleware('throttle:120,1,chat-read')->name('messages.index');
        Route::post('/{conversation}/messages', 'send')->middleware('throttle:30,1,chat-send')->name('messages.store');
        Route::delete('/{conversation}/messages/{message}', 'retract')->name('messages.retract');
    });
});

// ─── Resource library (all authenticated roles) ──────────────────────────────
// The club handbook, report templates and CHED memorandums, read-only. The admin
// section below is where they are uploaded and maintained. For the people who run
// clubs — advisers, officers and the DSA — not student members; the admin keeps access
// to see what they uploaded.
Route::middleware(['firebase.token', 'role:adviser,officer,dsa,admin'])->prefix('resources')->name('resources.')->controller(\App\Http\Controllers\ResourceLibraryController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/memos/{memo}/download', 'downloadMemo')->name('memos.download');
    Route::get('/files/{document}/download', 'downloadDocument')->name('documents.download');
});

// ─── Admin (is_admin flag, layered on top of any role) ───────────────────────
Route::middleware(['firebase.token', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('dashboard');

    // User & role management
    Route::get('/users',              [\App\Http\Controllers\Admin\UserController::class, 'index'])->name('users.index');
    Route::patch('/users/{user}/role', [\App\Http\Controllers\Admin\UserController::class, 'updateRole'])->name('users.role.update');

    // Proof-of-enrollment cases the automatic check could not decide (shared with the DSA)
    Route::get('/verifications',                         [\App\Http\Controllers\EnrollmentVerificationController::class, 'index'])->name('verifications.index');
    Route::post('/verifications/recheck',                [\App\Http\Controllers\EnrollmentVerificationController::class, 'recheck'])
        ->middleware('throttle:6,1,verification-recheck')->name('verifications.recheck');
    Route::get('/verifications/{verification}',          [\App\Http\Controllers\EnrollmentVerificationController::class, 'show'])->name('verifications.show');
    Route::get('/verifications/{verification}/document', [\App\Http\Controllers\EnrollmentVerificationController::class, 'document'])->name('verifications.document');
    Route::post('/verifications/{verification}/approve', [\App\Http\Controllers\EnrollmentVerificationController::class, 'approve'])->name('verifications.approve');
    Route::post('/verifications/{verification}/reject',  [\App\Http\Controllers\EnrollmentVerificationController::class, 'reject'])->name('verifications.reject');

    // Storage & data retention
    Route::get('/storage',                              [\App\Http\Controllers\Admin\StorageController::class, 'index'])->name('storage.index');
    Route::post('/storage/semesters/{semester}/archive', [\App\Http\Controllers\Admin\StorageController::class, 'archiveSemester'])->name('storage.archive');
    Route::get('/storage/download',                     [\App\Http\Controllers\Admin\StorageController::class, 'download'])->name('storage.download');
    Route::post('/storage/prune-audit',                 [\App\Http\Controllers\Admin\StorageController::class, 'pruneAudit'])->name('storage.prune-audit');

    // CHED memorandums (replaces the per-activity CHED reports)
    Route::get('/ched-memos',              [\App\Http\Controllers\Admin\ChedMemorandumController::class, 'index'])->name('ched-memos.index');
    Route::get('/ched-memos/create',       [\App\Http\Controllers\Admin\ChedMemorandumController::class, 'create'])->name('ched-memos.create');
    Route::post('/ched-memos',             [\App\Http\Controllers\Admin\ChedMemorandumController::class, 'store'])->name('ched-memos.store');
    Route::get('/ched-memos/{memo}/edit',  [\App\Http\Controllers\Admin\ChedMemorandumController::class, 'edit'])->name('ched-memos.edit');
    Route::patch('/ched-memos/{memo}',     [\App\Http\Controllers\Admin\ChedMemorandumController::class, 'update'])->name('ched-memos.update');
    Route::delete('/ched-memos/{memo}',    [\App\Http\Controllers\Admin\ChedMemorandumController::class, 'destroy'])->name('ched-memos.destroy');

    // Club handbook & report templates
    Route::get('/resources',                  [\App\Http\Controllers\Admin\ResourceDocumentController::class, 'index'])->name('resources.index');
    Route::post('/resources',                 [\App\Http\Controllers\Admin\ResourceDocumentController::class, 'store'])->name('resources.store');
    Route::get('/resources/{document}/edit',  [\App\Http\Controllers\Admin\ResourceDocumentController::class, 'edit'])->name('resources.edit');
    Route::patch('/resources/{document}',     [\App\Http\Controllers\Admin\ResourceDocumentController::class, 'update'])->name('resources.update');
    Route::delete('/resources/{document}',    [\App\Http\Controllers\Admin\ResourceDocumentController::class, 'destroy'])->name('resources.destroy');
});

// ─── DSA ────────────────────────────────────────────────────────────────────
Route::middleware(['firebase.token', 'role:dsa'])->prefix('dsa')->name('dsa.')->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\DSA\DashboardController::class, 'index'])->name('dashboard');

    Route::get('/clubs',             [\App\Http\Controllers\DSA\ClubController::class, 'index'])->name('clubs.index');
    Route::get('/clubs/create',      [\App\Http\Controllers\DSA\ClubController::class, 'create'])->name('clubs.create');
    Route::post('/clubs',            [\App\Http\Controllers\DSA\ClubController::class, 'store'])->name('clubs.store');
    Route::get('/clubs/{club}',      [\App\Http\Controllers\DSA\ClubController::class, 'show'])->name('clubs.show');
    Route::get('/clubs/{club}/edit', [\App\Http\Controllers\DSA\ClubController::class, 'edit'])->name('clubs.edit');
    Route::patch('/clubs/{club}',    [\App\Http\Controllers\DSA\ClubController::class, 'update'])->name('clubs.update');
    Route::delete('/clubs/{club}',   [\App\Http\Controllers\DSA\ClubController::class, 'destroy'])->name('clubs.destroy');

    // Member registration oversight (read-only — approval belongs to the club adviser)
    Route::get('/clubs/{club}/members',                  [\App\Http\Controllers\DSA\ClubMemberController::class, 'index'])->name('clubs.members.index');

    // Proof-of-enrollment cases the automatic check could not decide (an admin may decide them too)
    Route::get('/verifications',                         [\App\Http\Controllers\EnrollmentVerificationController::class, 'index'])->name('verifications.index');
    Route::post('/verifications/recheck',                [\App\Http\Controllers\EnrollmentVerificationController::class, 'recheck'])
        ->middleware('throttle:6,1,verification-recheck')->name('verifications.recheck');
    Route::get('/verifications/{verification}',          [\App\Http\Controllers\EnrollmentVerificationController::class, 'show'])->name('verifications.show');
    Route::get('/verifications/{verification}/document', [\App\Http\Controllers\EnrollmentVerificationController::class, 'document'])->name('verifications.document');
    Route::post('/verifications/{verification}/approve', [\App\Http\Controllers\EnrollmentVerificationController::class, 'approve'])->name('verifications.approve');
    Route::post('/verifications/{verification}/reject',  [\App\Http\Controllers\EnrollmentVerificationController::class, 'reject'])->name('verifications.reject');

    // Officer records (read-only)
    Route::get('/clubs/{club}/officers', [\App\Http\Controllers\DSA\ClubOfficerRecordController::class, 'index'])->name('clubs.officers.index');

    // Activity oversight (read-only — approving belongs to the club adviser)
    // Oversight only — approving an activity belongs to the club adviser.
    Route::get('/activities/monitor',                [\App\Http\Controllers\DSA\ActivityController::class, 'monitor'])->name('activities.monitor');
    Route::get('/activities/{event}',                [\App\Http\Controllers\DSA\ActivityController::class, 'show'])->name('activities.show');
    Route::get('/activities/{event}/letter',         [\App\Http\Controllers\DSA\ActivityController::class, 'downloadLetter'])->name('activities.letter');

    // Semester-end club reports (submitted by club presidents)
    Route::get('/club-reports',                          [\App\Http\Controllers\DSA\ClubReportController::class, 'index'])->name('club-reports.index');
    Route::get('/club-reports/{submission}/download',    [\App\Http\Controllers\DSA\ClubReportController::class, 'download'])->name('club-reports.download');
    Route::post('/club-reports/{submission}/accept',     [\App\Http\Controllers\DSA\ClubReportController::class, 'accept'])->name('club-reports.accept');
    Route::post('/club-reports/{submission}/return',     [\App\Http\Controllers\DSA\ClubReportController::class, 'returnForRevision'])->name('club-reports.return');

    // End-of-semester narrative reports (submitted by club advisers)
    Route::get('/semester-reports',               [\App\Http\Controllers\DSA\SemesterReportController::class, 'index'])->name('semester-reports.index');
    Route::get('/semester-reports/{report}/pdf',  [\App\Http\Controllers\DSA\SemesterReportController::class, 'downloadPdf'])->name('semester-reports.pdf');
    Route::get('/semester-reports/{report}/documents.zip', [\App\Http\Controllers\DSA\SemesterReportController::class, 'bundle'])->name('semester-reports.bundle');
    Route::get('/semester-reports/{report}/documents/{document}/{format}', [\App\Http\Controllers\DSA\SemesterReportController::class, 'document'])->name('semester-reports.document');
    Route::post('/semester-reports/{report}/return', [\App\Http\Controllers\DSA\SemesterReportController::class, 'returnForRevision'])->name('semester-reports.return');

    // Academic calendar (drives the end-of-semester reports)
    Route::get('/semesters',               [\App\Http\Controllers\DSA\SemesterController::class, 'index'])->name('semesters.index');
    Route::post('/semesters',              [\App\Http\Controllers\DSA\SemesterController::class, 'store'])->name('semesters.store');
    Route::patch('/semesters/{semester}',  [\App\Http\Controllers\DSA\SemesterController::class, 'update'])->name('semesters.update');
    Route::post('/semesters/{semester}/conclude', [\App\Http\Controllers\DSA\SemesterController::class, 'conclude'])->name('semesters.conclude');

    // How well each club is doing over the semester (club-level; churn risk is officer-only)
    Route::get('/club-performance',         [\App\Http\Controllers\DSA\ClubPerformanceController::class, 'index'])->name('club-performance.index');
    Route::get('/club-performance/{club}',  [\App\Http\Controllers\DSA\ClubPerformanceController::class, 'show'])->name('club-performance.show');

    // AI-assisted violation/compliance review
    Route::get('/violations',                    [\App\Http\Controllers\DSA\ViolationController::class, 'index'])->name('violations.index');
    Route::get('/violations/{violation}',        [\App\Http\Controllers\DSA\ViolationController::class, 'show'])->name('violations.show');
    Route::post('/violations/{violation}/approve', [\App\Http\Controllers\DSA\ViolationController::class, 'approve'])->name('violations.approve');
    Route::post('/violations/{violation}/dismiss', [\App\Http\Controllers\DSA\ViolationController::class, 'dismiss'])->name('violations.dismiss');
});

// ─── Club Member Registration (officer + adviser submission) ────────────────
Route::middleware(['firebase.token', 'role:officer,adviser'])->prefix('clubs/members')->name('clubs.members.')->group(function () {
    Route::get('/',         [\App\Http\Controllers\ClubMemberController::class, 'index'])->name('index');
    Route::post('/submit',  [\App\Http\Controllers\ClubMemberController::class, 'store'])->name('store');
    // Approval belongs to the club adviser (guarded in-controller)
    Route::post('/{member}/approve', [\App\Http\Controllers\ClubMemberController::class, 'approve'])->name('approve');
    Route::post('/{member}/reject',  [\App\Http\Controllers\ClubMemberController::class, 'reject'])->name('reject');
});

// ─── Semestral Member Presence (officer + adviser) ───────────────────────────
Route::middleware(['firebase.token', 'role:officer,adviser'])->prefix('clubs/presence')->name('clubs.presence.')->group(function () {
    Route::get('/',                      [\App\Http\Controllers\MemberPresenceController::class, 'index'])->name('index');
    Route::post('/{member}/status',      [\App\Http\Controllers\MemberPresenceController::class, 'update'])->name('update');
    Route::post('/notify-dsa',           [\App\Http\Controllers\MemberPresenceController::class, 'notifyDsa'])->name('notify');
});

// ─── Club Officer Records ────────────────────────────────────────────────────
// Officers + adviser may VIEW the list; only the adviser may add/edit/archive.
Route::middleware(['firebase.token', 'role:officer,adviser'])->prefix('clubs/officers')->name('clubs.officers.')->group(function () {
    Route::get('/', [\App\Http\Controllers\ClubOfficerRecordController::class, 'index'])->name('index');
});
Route::middleware(['firebase.token', 'role:adviser'])->prefix('clubs/officers')->name('clubs.officers.')->group(function () {
    Route::get('/create',            [\App\Http\Controllers\ClubOfficerRecordController::class, 'create'])->name('create');
    Route::post('/',                 [\App\Http\Controllers\ClubOfficerRecordController::class, 'store'])->name('store');
    Route::get('/{record}/edit',     [\App\Http\Controllers\ClubOfficerRecordController::class, 'edit'])->name('edit');
    Route::patch('/{record}',        [\App\Http\Controllers\ClubOfficerRecordController::class, 'update'])->name('update');
    Route::post('/{record}/archive', [\App\Http\Controllers\ClubOfficerRecordController::class, 'archive'])->name('archive');
});

// ─── Club Violations / Compliance Notices (officer + adviser) ───────────────
Route::middleware(['firebase.token', 'role:officer,adviser'])->prefix('clubs/violations')->name('clubs.violations.')->group(function () {
    Route::get('/',                   [\App\Http\Controllers\ClubViolationController::class, 'index'])->name('index');
    Route::post('/{violation}/resolve', [\App\Http\Controllers\ClubViolationController::class, 'resolve'])->name('resolve');
});

// ─── Adviser ────────────────────────────────────────────────────────────────
Route::middleware(['firebase.token', 'role:adviser'])->prefix('adviser')->name('adviser.')->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\Adviser\DashboardController::class, 'index'])->name('dashboard');

    // Activity approval queue (ACLE, community involvement, campus resource use,
    // other external). Internal meetings never reach it — they need no approval.
    Route::get('/activities',                          [\App\Http\Controllers\Adviser\ActivityController::class, 'review'])->name('activities.review');
    Route::get('/activities/{event}',                  [\App\Http\Controllers\Adviser\ActivityController::class, 'show'])->name('activities.show');
    Route::post('/activities/{event}/approve',         [\App\Http\Controllers\Adviser\ActivityController::class, 'approve'])->name('activities.approve');
    Route::post('/activities/{event}/reject',          [\App\Http\Controllers\Adviser\ActivityController::class, 'reject'])->name('activities.reject');
    Route::get('/activities/{event}/letter',           [\App\Http\Controllers\Adviser\ActivityController::class, 'downloadLetter'])->name('activities.letter');

    // AI club narrative review queue
    Route::get('/narratives',                        [\App\Http\Controllers\Adviser\NarrativeController::class, 'index'])->name('narratives.index');
    Route::get('/narratives/{narrative}',            [\App\Http\Controllers\Adviser\NarrativeController::class, 'show'])->name('narratives.show');
    Route::post('/narratives/{narrative}/approve',   [\App\Http\Controllers\Adviser\NarrativeController::class, 'approve'])->name('narratives.approve');
    Route::post('/narratives/{narrative}/discard',   [\App\Http\Controllers\Adviser\NarrativeController::class, 'discard'])->name('narratives.discard');

    // End-of-semester narrative reports: review, then submit to the DSA
    Route::get('/semester-reports',                [\App\Http\Controllers\Adviser\SemesterReportController::class, 'index'])->name('semester-reports.index');
    Route::get('/semester-reports/{report}',       [\App\Http\Controllers\Adviser\SemesterReportController::class, 'show'])->name('semester-reports.show');
    Route::patch('/semester-reports/{report}',     [\App\Http\Controllers\Adviser\SemesterReportController::class, 'update'])->name('semester-reports.update');
    // Saves the form, then has the AI draft one activity's sections again.
    Route::patch('/semester-reports/{report}/activities/{activity}/redraft', [\App\Http\Controllers\Adviser\SemesterReportController::class, 'redraft'])
        ->middleware('throttle:10,1,report-redraft')->name('semester-reports.redraft');
    // The manual path's head start: one activity written from its records, no AI needed.
    Route::patch('/semester-reports/{report}/activities/{activity}/template', [\App\Http\Controllers\Adviser\SemesterReportController::class, 'fillFromRecords'])
        ->middleware('throttle:20,1,report-template')->name('semester-reports.template');
    Route::get('/semester-reports/{report}/pdf',   [\App\Http\Controllers\Adviser\SemesterReportController::class, 'downloadPdf'])->name('semester-reports.pdf');
    Route::get('/semester-reports/{report}/documents.zip', [\App\Http\Controllers\Adviser\SemesterReportController::class, 'bundle'])->name('semester-reports.bundle');
    Route::get('/semester-reports/{report}/documents/{document}/{format}', [\App\Http\Controllers\Adviser\SemesterReportController::class, 'document'])->name('semester-reports.document');
});

// ─── Officer (president / treasurer / mmo) ───────────────────────────────────
Route::middleware(['firebase.token', 'role:officer'])->prefix('officer')->name('officer.')->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\Officer\DashboardController::class, 'index'])->name('dashboard');

    // Officer's own enrolled clubs: browse/enroll in non-academic clubs, and view a club
    Route::get('/clubs',              [\App\Http\Controllers\Officer\ClubController::class, 'index'])->name('clubs.index');
    Route::get('/clubs/{club}',       [\App\Http\Controllers\Officer\ClubController::class, 'show'])->name('clubs.show');
    Route::post('/clubs/{club}/join', [\App\Http\Controllers\Officer\ClubController::class, 'join'])->name('clubs.join');

    // Activities
    Route::get('/activities',              [\App\Http\Controllers\Officer\ActivityController::class, 'index'])->name('activities.index');
    Route::get('/activities/create',       [\App\Http\Controllers\Officer\ActivityController::class, 'create'])->name('activities.create');
    Route::post('/activities',             [\App\Http\Controllers\Officer\ActivityController::class, 'store'])->name('activities.store');
    // Throttled: each call can reach the Gemini API, so a stuck client must not burn quota.
    Route::post('/activities/ai-proposal', [\App\Http\Controllers\Officer\ActivityController::class, 'aiProposal'])
        ->middleware('throttle:20,1,ai-proposal')->name('activities.ai-proposal');
    Route::get('/activities/{event}',      [\App\Http\Controllers\Officer\ActivityController::class, 'show'])->name('activities.show');
    Route::get('/activities/{event}/edit', [\App\Http\Controllers\Officer\ActivityController::class, 'edit'])->name('activities.edit');
    Route::patch('/activities/{event}',    [\App\Http\Controllers\Officer\ActivityController::class, 'update'])->name('activities.update');
    Route::post('/activities/{event}/complete', [\App\Http\Controllers\Officer\ActivityController::class, 'complete'])->name('activities.complete');
    Route::get('/activities/{event}/letter', [\App\Http\Controllers\Officer\ActivityController::class, 'downloadLetter'])->name('activities.letter');

    // Photos, receipts, properties and budget for the semester-end report documents
    Route::post('/activities/{event}/photos',            [\App\Http\Controllers\Officer\ActivityReportController::class, 'storePhoto'])->name('activities.photos.store');
    Route::get('/activities/{event}/photos/{photo}',     [\App\Http\Controllers\Officer\ActivityReportController::class, 'showPhoto'])->name('activities.photos.show');
    Route::delete('/activities/{event}/photos/{photo}',  [\App\Http\Controllers\Officer\ActivityReportController::class, 'destroyPhoto'])->name('activities.photos.destroy');
    Route::patch('/activities/{event}/budget',           [\App\Http\Controllers\Officer\ActivityReportController::class, 'updateBudget'])->name('activities.budget');

    // Attendance
    Route::get('/activities/{event}/attendance',              [\App\Http\Controllers\Officer\AttendanceController::class, 'index'])->name('activities.attendance');
    Route::post('/activities/{event}/attendance/{user}',      [\App\Http\Controllers\Officer\AttendanceController::class, 'record'])->name('activities.attendance.record');

    // Semester-end reports to the DSA (every officer views; the president submits)
    Route::get('/reports',                                [\App\Http\Controllers\Officer\ClubReportController::class, 'index'])->name('club-reports.index');
    Route::post('/reports/{type}',                        [\App\Http\Controllers\Officer\ClubReportController::class, 'submit'])->name('club-reports.submit');
    Route::get('/reports/submissions/{submission}/download', [\App\Http\Controllers\Officer\ClubReportController::class, 'download'])->name('club-reports.download');

    // Fees
    Route::get('/fees',                                   [\App\Http\Controllers\Officer\FeeController::class, 'index'])->name('fees.index');
    Route::get('/fees/create',                            [\App\Http\Controllers\Officer\FeeController::class, 'create'])->name('fees.create');
    Route::post('/fees',                                  [\App\Http\Controllers\Officer\FeeController::class, 'store'])->name('fees.store');
    Route::get('/fees/{fee}',                             [\App\Http\Controllers\Officer\FeeController::class, 'show'])->name('fees.show');
    Route::get('/fees/{fee}/edit',                        [\App\Http\Controllers\Officer\FeeController::class, 'edit'])->name('fees.edit');
    Route::patch('/fees/{fee}',                           [\App\Http\Controllers\Officer\FeeController::class, 'update'])->name('fees.update');
    Route::post('/fees/{fee}/postpone',                   [\App\Http\Controllers\Officer\FeeController::class, 'postpone'])->name('fees.postpone');
    Route::post('/fees/{fee}/cancel',                     [\App\Http\Controllers\Officer\FeeController::class, 'cancel'])->name('fees.cancel');
    Route::post('/fees/{fee}/members/{user}/paid',        [\App\Http\Controllers\Officer\FeeController::class, 'markPaid'])->name('fees.paid');
    Route::post('/fees/{fee}/members/{user}/unpaid',      [\App\Http\Controllers\Officer\FeeController::class, 'markUnpaid'])->name('fees.unpaid');
    Route::get('/fees/{fee}/members/{user}/receipt',      [\App\Http\Controllers\Officer\FeeController::class, 'receipt'])->name('fees.receipt');
});

// ─── Member ──────────────────────────────────────────────────────────────────
// Officers navigate as members here; their club-admin tools live under officer.*/clubs.* and
// are surfaced from the club page for clubs they run.
Route::middleware(['firebase.token', 'role:member,officer'])->prefix('member')->name('member.')->group(function () {
    Route::get('/dashboard',             [\App\Http\Controllers\Member\DashboardController::class, 'index'])->name('dashboard');

    Route::get('/clubs',                 [\App\Http\Controllers\Member\ClubController::class, 'index'])->name('clubs.index');
    Route::get('/clubs/{club}',          [\App\Http\Controllers\Member\ClubController::class, 'show'])->name('clubs.show');
    Route::post('/clubs/{club}/join',    [\App\Http\Controllers\Member\ClubController::class, 'join'])->name('clubs.join');
    Route::post('/clubs/{club}/leave',   [\App\Http\Controllers\Member\ClubController::class, 'leave'])->name('clubs.leave');

    Route::get('/activity', [\App\Http\Controllers\Member\ActivityController::class, 'index'])->name('activity');
    Route::get('/activity/receipt/{payment}', [\App\Http\Controllers\Member\ActivityController::class, 'receipt'])->name('activity.receipt');

    // Bulletin board
    Route::get('/bulletin',                                  [\App\Http\Controllers\Member\BulletinController::class, 'index'])->name('bulletin.index');
    Route::get('/bulletin/{announcement}',                   [\App\Http\Controllers\Member\BulletinController::class, 'show'])->name('bulletin.show');
    Route::post('/bulletin/{announcement}/like',             [\App\Http\Controllers\Member\BulletinController::class, 'like'])->name('bulletin.like');
    Route::post('/bulletin/{announcement}/comment',          [\App\Http\Controllers\Member\BulletinController::class, 'comment'])->name('bulletin.comment');

    // Notifications
    Route::get('/notifications',                             [\App\Http\Controllers\Member\NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read',        [\App\Http\Controllers\Member\NotificationController::class, 'markRead'])->name('notifications.read');
});

// ─── Officer announcements (added to officer group above, defined here for clarity) ─
// (These must be inside the officer middleware group — appended via separate registration)
Route::middleware(['firebase.token', 'role:officer'])->prefix('officer')->name('officer.')->group(function () {
    Route::get('/announcements',                    [\App\Http\Controllers\Officer\AnnouncementController::class, 'index'])->name('announcements.index');
    Route::get('/announcements/create',             [\App\Http\Controllers\Officer\AnnouncementController::class, 'create'])->name('announcements.create');
    Route::post('/announcements',                   [\App\Http\Controllers\Officer\AnnouncementController::class, 'store'])->name('announcements.store');
    Route::get('/announcements/{announcement}',     [\App\Http\Controllers\Officer\AnnouncementController::class, 'show'])->name('announcements.show');
    Route::get('/announcements/{announcement}/edit',[\App\Http\Controllers\Officer\AnnouncementController::class, 'edit'])->name('announcements.edit');
    Route::patch('/announcements/{announcement}',   [\App\Http\Controllers\Officer\AnnouncementController::class, 'update'])->name('announcements.update');
    Route::post('/announcements/{announcement}/publish', [\App\Http\Controllers\Officer\AnnouncementController::class, 'publish'])->name('announcements.publish');
    Route::post('/announcements/ai-draft',          [\App\Http\Controllers\Officer\AnnouncementController::class, 'aiDraft'])
        ->middleware('throttle:20,1,announcement-ai-draft')->name('announcements.ai-draft');
});

// ─── Churn Risk Engine (club officers only — never members, advisers or the DSA) ─
// The DSA follows clubs, not members: see dsa.club-performance.
Route::middleware(['firebase.token', 'role:officer'])->group(function () {
    Route::get('/churn-risk', [\App\Http\Controllers\ChurnRiskController::class, 'index'])->name('churn-risk');
});

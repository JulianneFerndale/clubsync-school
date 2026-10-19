<?php

use App\Models\AuditLog;
use App\Models\Club;
use App\Models\ClubNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/*
 * Advisers are employees, not students (POLICY.md §Authentication, §Registration). They
 * sign in on their own page with their adviser number as well as their email and
 * password, or with Google, and sign up with adviser number, full name, SCC email and
 * password — no course, no proof of enrollment. At sign-up they pick the club they advise
 * from the clubs with no adviser, and an administrator confirms it: one adviser per club.
 */

const ADV_CLIENT = 'client-123.apps.googleusercontent.com';

beforeEach(function () {
    config([
        'google.sign_in.client_id'      => ADV_CLIENT,
        'google.sign_in.client_secret'  => 'client-secret',
        'google.sign_in.redirect_uri'   => null,
        'firebase.api_key'              => 'test-key',
        'firebase.sign_in_url'          => 'https://identitytoolkit.googleapis.com/v1/accounts:signInWithPassword',
        'firebase.sign_up_url'          => 'https://identitytoolkit.googleapis.com/v1/accounts:signUp',
        'firebase.sign_in_with_idp_url' => 'https://identitytoolkit.googleapis.com/v1/accounts:signInWithIdp',
    ]);

    // Nothing here may reach the real Google or Firebase.
    Http::preventStrayRequests();
});

function advClub(string $acronym, ?User $adviser = null): Club
{
    return Club::create([
        'slug'            => strtolower($acronym) . '-adv',
        'acronym'         => $acronym,
        'club_type'       => 'Non-Academic',
        'type'            => 'non_academic',
        'course_slug'     => 'all',
        'department_slug' => 'all',
        'name'            => $acronym . ' Guild',
        'is_active'       => true,
        'adviser_id'      => $adviser?->id,
    ]);
}

function advAdviser(array $overrides = []): User
{
    return User::create(array_merge([
        'first_name'      => 'Rosa',
        'last_name'       => 'Dimalanta',
        'employee_number' => 'SCC-ADV-0101',
        'email'           => 'dimalanta.adviser@sccpag.edu.ph',
        'password'        => Hash::make('Adviser1pass'),
        'firebase_uid'    => 'rosa-uid',
        'role'            => 'adviser',
        'is_admin'        => false,
        'is_suspended'    => false,
    ], $overrides));
}

/** Firebase's answers. The ID token is JWT-shaped and unexpired, so the session it opens stays open. */
function advFirebase(): void
{
    $part  = fn (array $data) => rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');
    $token = $part(['alg' => 'none']) . '.' . $part(['exp' => time() + 3600]) . '.signature';

    Http::fake([
        '*accounts:signUp*'             => Http::response(['localId' => 'new-uid', 'idToken' => $token, 'refreshToken' => 'fb-refresh']),
        '*accounts:signInWithPassword*' => Http::response(['localId' => 'rosa-uid', 'idToken' => $token, 'refreshToken' => 'fb-refresh']),
        '*accounts:signInWithIdp*'      => Http::response(['localId' => 'google-uid', 'idToken' => $token, 'refreshToken' => 'fb-refresh', 'email' => 'tomas.reyes@sccpag.edu.ph']),
    ]);
}

function advSignUp(array $overrides = [])
{
    return test()->post(route('register.adviser'), array_merge([
        'employee_number'       => 'scc-adv-0200',
        'first_name'            => 'Tomas',
        'last_name'             => 'Reyes',
        'email'                 => 'Tomas.Reyes@sccpag.edu.ph',
        'password'              => 'Adviser1pass',
        'password_confirmation' => 'Adviser1pass',
        'terms'                 => '1',
    ], $overrides));
}

// ── The adviser pages ───────────────────────────────────────────────────────

it('gives advisers their own sign-in, beside the student one', function () {
    $this->get(route('login'))->assertOk()->assertSee('Student')->assertSee(route('login.adviser'), false);

    $this->get(route('login.adviser'))
        ->assertOk()
        ->assertSee('Adviser Number')
        ->assertSee('Continue with Google')
        ->assertSee(route('auth.google', ['as' => 'adviser']), false)
        ->assertSee(route('register.adviser'), false);
});

it('asks for adviser number, full name, email and password, and lists only clubs with no adviser', function () {
    $open  = advClub('OPEN');
    advClub('TAKEN', advAdviser());

    $this->get(route('register.adviser'))
        ->assertOk()
        ->assertSee('Adviser Number')
        ->assertSee('Full Name')
        ->assertSee('Institutional Email')
        ->assertSee('Password')
        ->assertSee('Sign up with Google')
        ->assertSee($open->name)
        ->assertDontSee('TAKEN Guild')
        // Advisers are not asked for a course or proof of enrollment.
        ->assertDontSee('EDP Number')
        ->assertDontSee('Certificate of Enrollment');
});

// ── Signing up ──────────────────────────────────────────────────────────────

it('creates an adviser account with the club as a request, signs them in and tells the administrators', function () {
    advFirebase();
    $club  = advClub('OPEN');
    $admin = User::create([
        'first_name' => 'Ada', 'last_name' => 'Admin', 'email' => 'admin@sccpag.edu.ph',
        'password' => Hash::make('Password1!'), 'role' => 'dsa', 'is_admin' => true, 'is_suspended' => false,
    ]);

    advSignUp(['club_id' => $club->id])->assertRedirect(route('adviser.dashboard'));

    $adviser = User::where('email', 'tomas.reyes@sccpag.edu.ph')->firstOrFail();

    expect($adviser->role)->toBe('adviser')
        ->and($adviser->employee_number)->toBe('SCC-ADV-0200')
        ->and($adviser->firebase_uid)->toBe('new-uid')
        ->and($adviser->edp_number)->toBeNull()
        ->and($adviser->course_id)->toBeNull()
        ->and($adviser->requested_club_id)->toBe($club->id)
        // Not linked until an administrator confirms it.
        ->and($club->fresh()->adviser_id)->toBeNull()
        ->and(Hash::check('Adviser1pass', $adviser->password))->toBeTrue()
        ->and(session('firebase_user_role'))->toBe('adviser')
        ->and(session('firebase_user_id'))->toBe($adviser->id);

    expect(ClubNotification::where('recipient_id', $admin->id)->where('title', 'Adviser to confirm')->exists())->toBeTrue()
        ->and(AuditLog::where('action', 'auth.adviser.registered')->exists())->toBeTrue();

    $this->get(route('adviser.dashboard'))->assertOk()->assertSee('You asked to advise');
});

it('needs a club while any club has no adviser, and refuses one that has an adviser', function () {
    advFirebase();
    advClub('OPEN');
    $taken = advClub('TAKEN', advAdviser());

    advSignUp()->assertSessionHasErrors('club_id');
    advSignUp(['club_id' => $taken->id])->assertSessionHasErrors('club_id');

    expect(User::where('email', 'tomas.reyes@sccpag.edu.ph')->exists())->toBeFalse();
});

it('signs an adviser up with no club when every club has one', function () {
    advFirebase();
    advClub('TAKEN', advAdviser());

    $this->get(route('register.adviser'))->assertOk()->assertSee('Every club has an adviser right now');

    advSignUp()->assertRedirect(route('adviser.dashboard'));

    expect(User::where('email', 'tomas.reyes@sccpag.edu.ph')->value('requested_club_id'))->toBeNull();
});

it('accepts only SCC addresses and one account per adviser number', function () {
    advFirebase();
    advAdviser();

    advSignUp(['email' => 'tomas@gmail.com'])->assertSessionHasErrors('email');
    advSignUp(['employee_number' => 'scc-adv-0101'])->assertSessionHasErrors('employee_number');

    expect(User::where('role', 'adviser')->count())->toBe(1);
});

it('signs an adviser up with their SCC Google account, with no password', function () {
    advFirebase();
    $club = advClub('OPEN');

    $this->withSession(['register' => ['adviser_google' => [
        'email' => 'tomas.reyes@sccpag.edu.ph', 'first_name' => 'Tomas', 'last_name' => 'Reyes',
        'id_token' => 'google-id-token', 'expires' => time() + 600,
    ]]])->get(route('register.adviser'))
        ->assertOk()
        ->assertSee('tomas.reyes@sccpag.edu.ph')
        ->assertDontSee('Confirm Password');

    $this->post(route('register.adviser'), ['employee_number' => 'SCC-ADV-0300', 'club_id' => $club->id, 'terms' => '1'])
        ->assertRedirect(route('adviser.dashboard'));

    $adviser = User::where('email', 'tomas.reyes@sccpag.edu.ph')->firstOrFail();

    expect($adviser->role)->toBe('adviser')
        ->and($adviser->signsInWithGoogleOnly())->toBeTrue()
        ->and($adviser->firebase_uid)->toBe('google-uid')
        ->and($adviser->requested_club_id)->toBe($club->id);
});

it('takes an SCC Google account with no ClubSync account to adviser sign-up from the adviser page', function () {
    $part    = fn (array $data) => rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');
    $idToken = $part(['alg' => 'RS256']) . '.' . $part([
        'iss' => 'https://accounts.google.com', 'aud' => ADV_CLIENT, 'exp' => time() + 3600, 'nonce' => 'nonce-abc',
        'hd' => 'sccpag.edu.ph', 'email' => 'tomas.reyes@sccpag.edu.ph', 'email_verified' => true,
        'given_name' => 'Tomas', 'family_name' => 'Reyes',
    ]) . '.signature';

    Http::fake(['oauth2.googleapis.com/token' => Http::response(['id_token' => $idToken, 'access_token' => 'at'])]);

    $this->withSession(['google.oauth' => [
        'state' => 'state-xyz', 'nonce' => 'nonce-abc', 'verifier' => str_repeat('v', 64), 'remember' => false, 'adviser' => true,
    ]])->get('/auth/google/callback?state=state-xyz&code=auth-code')
        ->assertRedirect(route('register.adviser'));

    expect(session('register.adviser_google.email'))->toBe('tomas.reyes@sccpag.edu.ph')
        ->and(session('register.google'))->toBeNull();
});

// ── Signing in ──────────────────────────────────────────────────────────────

it('signs an adviser in with their adviser number, email and password', function () {
    advFirebase();
    $adviser = advAdviser();

    $this->post(route('login.adviser'), [
        'employee_number' => 'scc-adv-0101', 'email' => $adviser->email, 'password' => 'Adviser1pass',
    ])->assertRedirect(route('adviser.dashboard'));

    expect(session('firebase_user_id'))->toBe($adviser->id);
});

it('refuses an adviser number that is not the account\'s own', function () {
    advFirebase();
    $adviser = advAdviser();

    $this->post(route('login.adviser'), [
        'employee_number' => 'SCC-ADV-0999', 'email' => $adviser->email, 'password' => 'Adviser1pass',
    ])->assertSessionHasErrors('employee_number');

    expect(session('firebase_user_id'))->toBeNull();
});

it('keeps students off the adviser sign-in', function () {
    advFirebase();
    $student = User::create([
        'first_name' => 'Juan', 'last_name' => 'Cruz', 'edp_number' => '0000001234', 'email' => 'juan.cruz@sccpag.edu.ph',
        'password' => Hash::make('Student1pass'), 'firebase_uid' => 'rosa-uid', 'role' => 'member',
        'course_id' => testCourse()->id, 'is_admin' => false, 'is_suspended' => false,
    ]);

    $this->post(route('login.adviser'), [
        'employee_number' => 'SCC-ADV-0101', 'email' => $student->email, 'password' => 'Student1pass',
    ])->assertSessionHasErrors(['email' => 'This is not an adviser account. Students, officers and staff sign in on the Student tab.']);

    expect(session('firebase_user_id'))->toBeNull();
});

it('points an unknown address at adviser sign-up', function () {
    advFirebase();

    $this->post(route('login.adviser'), [
        'employee_number' => 'SCC-ADV-0101', 'email' => 'nobody@sccpag.edu.ph', 'password' => 'Adviser1pass',
    ])->assertSessionHasErrors('email');
});

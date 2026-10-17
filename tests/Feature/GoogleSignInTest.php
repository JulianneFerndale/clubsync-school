<?php

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/*
 * "Continue with Google" is an ADDED way in, for SCC Google Workspace accounts only.
 *
 * The domain is judged by the server from Google's own token — the hd claim names the
 * Workspace an account belongs to, and the address must be verified — and it is judged
 * before Firebase hears anything, so an outside account never gets a Firebase account.
 */

const GS_CLIENT = 'client-123.apps.googleusercontent.com';

beforeEach(function () {
    config([
        'google.sign_in.client_id'     => GS_CLIENT,
        'google.sign_in.client_secret' => 'client-secret',
        'google.sign_in.redirect_uri'  => null,
        'firebase.api_key'             => 'test-key',
        'firebase.sign_in_url'         => 'https://identitytoolkit.googleapis.com/v1/accounts:signInWithPassword',
        'firebase.sign_up_url'         => 'https://identitytoolkit.googleapis.com/v1/accounts:signUp',
        'firebase.sign_in_with_idp_url'=> 'https://identitytoolkit.googleapis.com/v1/accounts:signInWithIdp',
    ]);

    // Nothing here may reach the real Google or Firebase.
    Http::preventStrayRequests();
});

/** A Google ID token as Google's token endpoint would return it (unsigned is fine: it is not checked here). */
function gsIdToken(array $claims = []): string
{
    $claims = array_merge([
        'iss'            => 'https://accounts.google.com',
        'aud'            => GS_CLIENT,
        'exp'            => time() + 3600,
        'nonce'          => 'nonce-abc',
        'hd'             => 'sccpag.edu.ph',
        'email'          => 'maria.santos@sccpag.edu.ph',
        'email_verified' => true,
        'given_name'     => 'Maria',
        'family_name'    => 'Santos',
    ], $claims);

    $part = fn (array $data) => rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');

    return $part(['alg' => 'RS256']) . '.' . $part(array_filter($claims, fn ($v) => $v !== null)) . '.signature';
}

/** Google's token endpoint hands back $idToken; Firebase answers signInWithIdp with $firebase. */
function gsFake(string $idToken, array|callable|null $firebase = null, array $extra = []): void
{
    Http::fake($extra + [
        'oauth2.googleapis.com/token' => Http::response(['id_token' => $idToken, 'access_token' => 'at']),
        '*accounts:signInWithIdp*'    => is_callable($firebase) ? $firebase : Http::response($firebase ?? [
            'localId'      => 'maria-uid',
            'idToken'      => 'fb-id-token',
            'refreshToken' => 'fb-refresh-token',
            'email'        => 'maria.santos@sccpag.edu.ph',
        ]),
    ]);
}

/** Come back from Google with this browser's state, as Google's redirect would. */
function gsCallback(array $query = [], bool $remember = false)
{
    return test()->withSession(['google.oauth' => [
        'state' => 'state-xyz', 'nonce' => 'nonce-abc', 'verifier' => str_repeat('v', 64), 'remember' => $remember,
    ]])->get('/auth/google/callback?' . http_build_query($query + ['state' => 'state-xyz', 'code' => 'auth-code']));
}

function gsUser(array $overrides = []): User
{
    return User::create(array_merge([
        'first_name'   => 'Maria',
        'last_name'    => 'Santos',
        'edp_number'   => '0000009001',
        'email'        => 'maria.santos@sccpag.edu.ph',
        'password'     => Hash::make('Str0ng!Pass1'),
        'firebase_uid' => 'maria-uid',
        'role'         => 'member',
        'course_id'    => testCourse()->id,
        'is_admin'     => false,
        'is_suspended' => false,
    ], $overrides));
}

function gsFirebaseWasAsked(): bool
{
    return Http::recorded(fn (HttpRequest $r) => str_contains($r->url(), 'identitytoolkit'))->isNotEmpty();
}

// ── The button and the trip to Google ──────────────────────────────────────

it('offers Google sign-in only once the OAuth client is set up', function () {
    $this->get('/login')->assertOk()->assertSee('Continue with Google');

    config(['google.sign_in.client_id' => null]);

    $this->get('/login')->assertOk()->assertDontSee('Continue with Google');
    $this->get('/auth/google')->assertRedirect(route('login'))->assertSessionHas('error');
});

it('sends the browser to Google narrowed to SCC accounts, with state, nonce and PKCE', function () {
    $response = $this->get('/auth/google?remember=1')->assertRedirect();

    $url = $response->headers->get('Location');
    parse_str(parse_url($url, PHP_URL_QUERY), $query);

    $oauth = session('google.oauth');

    expect($url)->toStartWith('https://accounts.google.com/o/oauth2/v2/auth?')
        ->and($query['client_id'])->toBe(GS_CLIENT)
        ->and($query['hd'])->toBe('sccpag.edu.ph')
        ->and($query['scope'])->toBe('openid email profile')
        ->and($query['redirect_uri'])->toBe(route('auth.google.callback'))
        ->and($query['state'])->toBe($oauth['state'])
        ->and($query['nonce'])->toBe($oauth['nonce'])
        ->and($query['code_challenge_method'])->toBe('S256')
        ->and($query['code_challenge'])->toBe(rtrim(strtr(base64_encode(hash('sha256', $oauth['verifier'], true)), '+/', '-_'), '='))
        ->and($oauth['remember'])->toBeTrue();
});

// ── Strictly @sccpag.edu.ph ────────────────────────────────────────────────

it('refuses a Google account outside the SCC Workspace before Firebase hears of it', function (array $claims) {
    gsUser();
    gsFake(gsIdToken($claims));

    gsCallback()
        ->assertRedirect(route('login'))
        ->assertSessionHas('error', fn ($m) => str_contains($m, 'Only Saint Columban College Google accounts (@sccpag.edu.ph)'));

    expect(gsFirebaseWasAsked())->toBeFalse()
        ->and(session('firebase_id_token'))->toBeNull();
})->with([
    'a personal Gmail account'                         => [['hd' => null, 'email' => 'maria.santos@gmail.com']],
    'another school\'s Workspace'                      => [['hd' => 'otherschool.edu.ph', 'email' => 'maria@otherschool.edu.ph']],
    // A personal Google account can be opened with any address: only hd proves the Workspace.
    'a personal account using an SCC-looking address'  => [['hd' => null]],
    'a look-alike domain'                              => [['hd' => 'sccpag.edu.ph.evil.com', 'email' => 'maria@sccpag.edu.ph.evil.com']],
    'an SCC hd with an address on another domain'      => [['email' => 'maria.santos@gmail.com']],
    'a subdomain address'                              => [['email' => 'maria@student.sccpag.edu.ph']],
]);

it('refuses an SCC address Google has not verified', function () {
    gsUser();
    gsFake(gsIdToken(['email_verified' => false]));

    gsCallback()->assertRedirect(route('login'))->assertSessionHas('error', fn ($m) => str_contains($m, 'not verified'));

    expect(gsFirebaseWasAsked())->toBeFalse();
});

it('refuses a token that was not issued for this sign-in', function (array $claims) {
    gsUser();
    gsFake(gsIdToken($claims));

    gsCallback()->assertRedirect(route('login'))
        ->assertSessionHas('error', 'Google sign-in could not be completed. Please try again.');

    expect(gsFirebaseWasAsked())->toBeFalse();
})->with([
    'another app\'s client'  => [['aud' => 'someone-else.apps.googleusercontent.com']],
    'a replayed nonce'       => [['nonce' => 'nonce-from-another-sign-in']],
    'an expired token'       => [['exp' => time() - 10]],
    'another issuer'         => [['iss' => 'https://evil.example.com']],
]);

it('refuses a callback this browser did not start, and cannot be replayed', function () {
    gsUser();
    gsFake(gsIdToken());

    gsCallback(['state' => 'forged'])->assertRedirect(route('login'))->assertSessionHas('error');
    expect(session('google.oauth'))->toBeNull();

    // No state left in the session at all: a second use of a real callback.
    $this->get('/auth/google/callback?state=state-xyz&code=auth-code')->assertRedirect(route('login'))->assertSessionHas('error');

    Http::assertNothingSent();
});

it('says so when the person cancels on Google\'s screen', function () {
    Http::fake();

    gsCallback(['error' => 'access_denied', 'code' => null])
        ->assertRedirect(route('login'))
        ->assertSessionHas('info', 'Google sign-in was cancelled.');

    Http::assertNothingSent();
});

// ── Signing in ─────────────────────────────────────────────────────────────

it('signs in the ClubSync account with the same address once Google is linked', function () {
    $user = gsUser();
    gsFake(gsIdToken());

    gsCallback()->assertRedirect(route('member.dashboard'));

    expect(session('firebase_id_token'))->toBe('fb-id-token')
        ->and(session('firebase_user_id'))->toBe($user->id)
        ->and(session('firebase_refresh_token'))->toBe('fb-refresh-token')
        ->and(AuditLog::where('action', 'auth.google.signed_in')->where('actor_id', $user->id)->exists())->toBeTrue();

    Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'accounts:signInWithIdp')
        && str_contains($r['postBody'], 'providerId=google.com')
        && ! isset($r['idToken']));
});

it('keeps Remember Me for a Google sign-in', function () {
    gsUser();
    gsFake(gsIdToken());

    gsCallback(remember: true)->assertCookie('clubsync_refresh', 'fb-refresh-token');
});

it('asks for the ClubSync password once, then links Google to the account', function () {
    $user = gsUser();
    gsFake(gsIdToken(), function (HttpRequest $r) {
        // Not linked yet: Firebase wants the existing sign-in first. Once given, it links.
        return isset($r['idToken'])
            ? Http::response(['localId' => 'maria-uid', 'idToken' => 'linked-id-token', 'refreshToken' => 'linked-refresh'])
            : Http::response(['needConfirmation' => true, 'localId' => 'maria-uid', 'verifiedProvider' => ['password']]);
    }, [
        '*accounts:signInWithPassword*' => fn (HttpRequest $r) => $r['password'] === 'Str0ng!Pass1'
            ? Http::response(['localId' => 'maria-uid', 'idToken' => 'password-id-token', 'refreshToken' => 'password-refresh'])
            : Http::response(['error' => ['message' => 'INVALID_LOGIN_CREDENTIALS']], 400),
    ]);

    gsCallback()->assertRedirect(route('auth.google.link'));
    expect(session('firebase_id_token'))->toBeNull();

    $this->get('/auth/google/link')->assertOk()->assertSee('maria.santos@sccpag.edu.ph');

    $this->from('/auth/google/link')->post('/auth/google/link', ['password' => 'Wrong-pass1'])
        ->assertRedirect('/auth/google/link')
        ->assertSessionHasErrors(['password' => 'The password you entered is incorrect. Try again, or tap Forgot Password to reset it.']);

    $this->post('/auth/google/link', ['password' => 'Str0ng!Pass1'])->assertRedirect(route('member.dashboard'));

    expect(session('firebase_id_token'))->toBe('linked-id-token')
        ->and(session('firebase_user_id'))->toBe($user->id)
        ->and(session('google.link'))->toBeNull()
        ->and(AuditLog::where('action', 'auth.google.linked')->where('actor_id', $user->id)->exists())->toBeTrue();

    Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'accounts:signInWithIdp')
        && ($r['idToken'] ?? null) === 'password-id-token');
});

it('lets the password confirmation run out', function () {
    gsUser();
    Http::fake();

    $this->withSession(['google.link' => ['id_token' => 't', 'user_id' => 1, 'remember' => false, 'expires' => time() - 1]])
        ->post('/auth/google/link', ['password' => 'Str0ng!Pass1'])
        ->assertRedirect(route('login'))
        ->assertSessionHas('error');

    Http::assertNothingSent();
});

it('refuses a suspended account without asking Firebase', function () {
    gsUser(['is_suspended' => true]);
    gsFake(gsIdToken());

    gsCallback()->assertRedirect(route('login'))
        ->assertSessionHas('error', 'Your account has been suspended. Please contact the Director of Student Affairs for assistance.');

    expect(gsFirebaseWasAsked())->toBeFalse();
});

it('never re-points an account at a different Firebase account on Google\'s word', function () {
    gsUser(['firebase_uid' => 'the-real-uid']);
    gsFake(gsIdToken(), ['localId' => 'some-other-uid', 'idToken' => 'x', 'refreshToken' => 'y']);

    gsCallback()->assertRedirect(route('login'))->assertSessionHas('error');

    expect(session('firebase_id_token'))->toBeNull()
        ->and(User::sole()->firebase_uid)->toBe('the-real-uid');
});

it('reports Google being switched off in Firebase as unavailable, not as the person\'s fault', function () {
    gsUser();
    gsFake(gsIdToken(), fn () => Http::response(['error' => ['message' => 'OPERATION_NOT_ALLOWED']], 400));

    gsCallback()->assertRedirect(route('login'))
        ->assertSessionHas('error', 'Google sign-in is unavailable right now. Please sign in with your email and password.');
});

// ── Registering with Google: no password ───────────────────────────────────

/** A Firebase ID token that decodes as unexpired, so nothing tries to refresh it. */
function gsFirebaseToken(string $uid = 'maria-google-uid'): string
{
    $part = fn (array $data) => rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');

    return $part(['alg' => 'none']) . '.' . $part(['user_id' => $uid, 'exp' => time() + 3600]) . '.signature';
}

/**
 * Everything a whole Google registration talks to: Google, Firebase (which makes a
 * Google-only account — no accounts:signUp, no password), and Gemini reading a COE
 * that matches Maria Santos.
 */
function gsRegistrationFakes(): void
{
    config(['gemini.api_key' => 'test-key']);

    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['id_token' => gsIdToken()]),
        '*accounts:signInWithIdp*'    => Http::response([
            'localId' => 'maria-google-uid', 'idToken' => gsFirebaseToken(), 'refreshToken' => 'google-refresh',
            'email' => 'maria.santos@sccpag.edu.ph', 'isNewUser' => true,
        ]),
        '*accounts:signInWithPassword*' => Http::response(['error' => ['message' => 'INVALID_LOGIN_CREDENTIALS']], 400),
        '*generativelanguage*' => Http::response(['candidates' => [[
            'content' => ['parts' => [['text' => json_encode([
                'document_type' => 'certificate_of_enrollment',
                'school_name'   => 'SAINT COLUMBAN COLLEGE',
                'full_name'     => 'SANTOS, MARIA L',
                'edp_number'    => '0123456789',
                'course'        => 'BSIT',
                'course_id'     => testCourse()->id,
                'looks_altered' => false,
                'notes'         => '',
            ])]]],
            'finishReason' => 'STOP',
        ]]]),
    ]);
}

/** The only things the Google form asks for. */
function gsGoogleForm(array $overrides = []): array
{
    return array_merge(['edp_number' => '0123456789', 'year_level' => 2, 'mobile_number' => '09171234567', 'terms' => 'on'], $overrides);
}

it('registers an SCC Google account with only EDP number, year level and mobile number, and no password', function () {
    gsRegistrationFakes();
    $course = testCourse();

    gsCallback()->assertRedirect(route('register'))->assertSessionHas('info');
    expect(gsFirebaseWasAsked())->toBeFalse();

    // Its own form: the name and address are Google's, and there is no password.
    $this->get('/register')->assertOk()
        ->assertSee('Register with Google')
        ->assertSee('Maria Santos')
        ->assertSee('maria.santos@sccpag.edu.ph')
        ->assertSee('name="edp_number"', false)
        ->assertSee('name="year_level"', false)
        ->assertSee('name="mobile_number"', false)
        ->assertDontSee('name="password"', false)
        ->assertDontSee('name="first_name"', false)
        ->assertDontSee('name="email"', false);

    // Anything else posted is ignored: the address and name stay Google's.
    $this->post('/register', gsGoogleForm(['email' => 'someone.else@sccpag.edu.ph', 'first_name' => 'Mallory']))
        ->assertRedirect(route('register.department'))
        ->assertSessionHasNoErrors();

    expect(session('register.email'))->toBe('maria.santos@sccpag.edu.ph')
        ->and(session('register.first_name'))->toBe('Maria')
        ->and(session('register.last_name'))->toBe('Santos')
        ->and(session('register.firebase_uid'))->toBe('maria-google-uid')
        ->and(session('register.via_google'))->toBeTrue()
        ->and(session('register.password'))->toBeNull();

    // The Firebase account is Google's: made by signInWithIdp, never by signUp.
    Http::assertNotSent(fn (HttpRequest $r) => str_contains($r->url(), 'accounts:signUp'));

    // Then department and course, and proof of enrollment, as for everyone.
    $this->post('/register/department', ['department_id' => $course->department_id])->assertRedirect(route('register.course'));
    $this->post('/register/course', ['course_id' => $course->id])->assertRedirect(route('register.verify'));
    $this->get('/register/verify')->assertOk()->assertSee('Edit my EDP number');

    $this->post('/register/verify', ['document' => UploadedFile::fake()->image('coe.jpg', 600, 800)])
        ->assertRedirect(route('member.dashboard'));

    $user = User::sole();

    expect($user->email)->toBe('maria.santos@sccpag.edu.ph')
        ->and($user->first_name)->toBe('Maria')
        ->and($user->last_name)->toBe('Santos')
        ->and($user->edp_number)->toBe('0123456789')
        ->and($user->year_level)->toBe(2)
        ->and($user->mobile_number)->toBe('09171234567')
        ->and($user->course_id)->toBe($course->id)
        ->and($user->firebase_uid)->toBe('maria-google-uid')
        ->and($user->enrollment_status)->toBe('verified')
        ->and($user->signsInWithGoogleOnly())->toBeTrue()
        // Signed in at once, and the wizard's leftovers are gone.
        ->and(session('firebase_user_id'))->toBe($user->id)
        ->and(session('register'))->toBeEmpty();
});

it('lets a Google-registered student back in with Google alone, and never asks for a password', function () {
    gsRegistrationFakes();
    $course = testCourse();

    // Register.
    gsCallback();
    $this->post('/register', gsGoogleForm());
    $this->post('/register/department', ['department_id' => $course->department_id]);
    $this->post('/register/course', ['course_id' => $course->id]);
    $this->post('/register/verify', ['document' => UploadedFile::fake()->image('coe.jpg', 600, 800)])
        ->assertRedirect(route('member.dashboard'));

    $user = User::sole();

    // The profile has no password to change, and the server agrees.
    $this->get('/profile')->assertOk()
        ->assertSee('no ClubSync password to change')
        ->assertDontSee('Update Password');
    $this->from('/profile')->post('/profile/password', [
        'current_password' => 'anything', 'password' => 'Newpass12', 'password_confirmation' => 'Newpass12',
    ])->assertSessionHasErrors('password');

    // Sign out, then Google signs them straight in — no "confirm your password" step.
    $this->post('/logout');
    expect(session('firebase_id_token'))->toBeNull();

    gsCallback()->assertRedirect(route('member.dashboard'));

    expect(session('firebase_user_id'))->toBe($user->id)
        ->and(session('google.link'))->toBeNull()
        ->and(AuditLog::where('action', 'auth.google.signed_in')->where('actor_id', $user->id)->exists())->toBeTrue();

    // A password sign-in is pointed to Google instead of "incorrect password".
    $this->post('/logout');
    $this->from(route('login'))->post('/login', ['email' => 'maria.santos@sccpag.edu.ph', 'password' => 'Guess1234'])
        ->assertSessionHasErrors(['email' => 'This account signs in with Google. Tap Continue with Google below.']);
});

it('asks the Google form for the EDP number, year level, mobile number and the Terms', function () {
    gsRegistrationFakes();
    gsCallback();

    $this->from('/register')->post('/register', [])
        ->assertSessionHasErrors(['edp_number', 'year_level', 'mobile_number', 'terms'])
        ->assertSessionDoesntHaveErrors(['password', 'email', 'first_name', 'last_name']);

    expect(gsFirebaseWasAsked())->toBeFalse();
});

it('asks for the name only when Google did not supply one', function () {
    gsRegistrationFakes();
    $this->withSession(['register.google' => [
        'id_token' => gsIdToken(), 'email' => 'maria.santos@sccpag.edu.ph',
        'first_name' => null, 'last_name' => null, 'expires' => time() + 600,
    ]]);

    $this->get('/register')->assertOk()->assertSee('name="first_name"', false);

    $this->from('/register')->post('/register', gsGoogleForm())->assertSessionHasErrors(['first_name', 'last_name']);

    $this->post('/register', gsGoogleForm(['first_name' => 'Maria', 'last_name' => 'Santos']))
        ->assertRedirect(route('register.department'));

    expect(session('register.first_name'))->toBe('Maria');
});

it('keeps the Google form, and the Firebase account already made, when the student goes back to edit', function () {
    gsRegistrationFakes();
    gsCallback();
    $this->post('/register', gsGoogleForm())->assertRedirect(route('register.department'));

    // Google's answer runs out while they are further along.
    session()->forget('register.google');

    $this->get('/register')->assertOk()->assertSee('Register with Google')->assertDontSee('name="password"', false);

    $this->post('/register', gsGoogleForm(['edp_number' => '0123456780']))->assertRedirect(route('register.department'));

    expect(session('register.edp_number'))->toBe('0123456780')
        ->and(session('register.firebase_uid'))->toBe('maria-google-uid');

    Http::assertSentCount(2); // the code exchange and ONE signInWithIdp
});

it('sends an address with an unfinished PASSWORD registration to the password form', function () {
    gsFake(gsIdToken(), ['needConfirmation' => true, 'localId' => 'orphan-uid', 'verifiedProvider' => ['password']]);
    gsCallback();

    $this->post('/register', gsGoogleForm())
        ->assertRedirect(route('register'))
        ->assertSessionHasErrors('email');

    expect(session('register.google'))->toBeNull()
        ->and(session('register.firebase_uid'))->toBeNull();

    $this->get('/register')->assertOk()->assertSee('name="password"', false);
});

it('sends an address that already has a ClubSync account to sign in', function () {
    gsRegistrationFakes();
    $this->withSession(['register.google' => [
        'id_token' => gsIdToken(), 'email' => 'maria.santos@sccpag.edu.ph',
        'first_name' => 'Maria', 'last_name' => 'Santos', 'expires' => time() + 600,
    ]]);
    gsUser();

    $this->post('/register', gsGoogleForm())->assertRedirect(route('login'))->assertSessionHas('error');

    expect(gsFirebaseWasAsked())->toBeFalse();
});

it('lets the student switch to the email-and-password form', function () {
    gsRegistrationFakes();
    gsCallback();

    $this->post('/register/google/cancel')->assertRedirect(route('register'));

    $this->get('/register')->assertOk()->assertSee('name="password"', false);
    expect(session('register.google'))->toBeNull();
});

it('treats an expired Google start as an ordinary registration', function () {
    $this->withSession(['register.google' => [
        'id_token' => 'old', 'email' => 'maria.santos@sccpag.edu.ph', 'first_name' => 'Maria', 'last_name' => 'Santos',
        'expires' => time() - 1,
    ]]);

    $this->get('/register')->assertOk()->assertSee('name="password"', false);
    expect(session('register.google'))->toBeNull();
});

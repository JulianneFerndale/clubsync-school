<?php

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/*
 * ClubSync's own reset-password page. Firebase mints and emails the single-use oobCode
 * and stores the new password; the page the person uses is ClubSync's, and the new
 * password is mirrored into the users table so email sign-in works at once.
 */

beforeEach(function () {
    config([
        'firebase.api_key'            => 'test-key',
        'firebase.reset_password_url' => 'https://identitytoolkit.googleapis.com/v1/accounts:resetPassword',
    ]);
    Http::preventStrayRequests();
});

function rpUser(array $overrides = []): User
{
    return User::create(array_merge([
        'first_name'   => 'Reset',
        'last_name'    => 'Tester',
        'edp_number'   => '0000008201',
        'email'        => 'reset.tester@sccpag.edu.ph',
        'password'     => Hash::make('Old!Password1'),
        'firebase_uid' => 'reset-uid-1',
        'role'         => 'member',
        'course_id'    => testCourse()->id,
        'is_admin'     => false,
        'is_suspended' => false,
    ], $overrides));
}

/** Firebase's accounts:resetPassword: verify (oobCode only) and confirm (oobCode+newPassword) share a URL. */
function rpFirebaseOk(string $email = 'reset.tester@sccpag.edu.ph'): void
{
    Http::fake(['*accounts:resetPassword*' => Http::response(['email' => $email, 'requestType' => 'PASSWORD_RESET'])]);
}

function rpFirebaseRejects(string $code = 'EXPIRED_OOB_CODE'): void
{
    Http::fake(['*accounts:resetPassword*' => Http::response(['error' => ['message' => $code]], 400)]);
}

// ── The page ────────────────────────────────────────────────────────────────

it('shows the reset form for a valid code, naming the address', function () {
    rpFirebaseOk();

    $this->get(route('reset-password', ['mode' => 'resetPassword', 'oobCode' => 'good-code']))
        ->assertOk()
        ->assertSee('Set a new password')
        ->assertSee('reset.tester@sccpag.edu.ph')
        ->assertSee('name="oobCode"', false)
        ->assertSee('value="good-code"', false);
});

it('shows the invalid-link state when no code is present', function () {
    $this->get(route('reset-password'))
        ->assertOk()
        ->assertSee("This link isn't valid", false)
        ->assertSee(route('forgot-password'), false)
        ->assertDontSee('Set a new password');
});

it('shows the invalid-link state when Firebase rejects the code', function () {
    rpFirebaseRejects('INVALID_OOB_CODE');

    $this->get(route('reset-password', ['oobCode' => 'bad-code']))
        ->assertOk()
        ->assertSee("This link isn't valid", false)
        ->assertDontSee('Set a new password');
});

it('never echoes the oobCode into the invalid-link page', function () {
    $this->get(route('reset-password', ['mode' => 'verifyEmail', 'oobCode' => 'other-mode-code']))
        ->assertOk()
        ->assertSee("This link isn't valid", false)
        ->assertDontSee('other-mode-code');
});

// ── Applying the new password ─────────────────────────────────────────────────

it('resets the password, mirrors it into the users table and sends them to login', function () {
    $user = rpUser();
    rpFirebaseOk();

    $this->post(route('reset-password.update'), [
        'oobCode'               => 'good-code',
        'password'              => 'Brand!New2',
        'password_confirmation' => 'Brand!New2',
    ])
        ->assertRedirect(route('login'))
        ->assertSessionHas('success');

    $user->refresh();
    expect(Hash::check('Brand!New2', $user->password))->toBeTrue()
        ->and(Hash::check('Old!Password1', $user->password))->toBeFalse()
        ->and($user->last_password_changed)->not->toBeNull();

    expect(AuditLog::where('action', 'auth.password.reset')->where('actor_id', $user->id)->exists())->toBeTrue();
});

it('gives a Google-only account a password, so email sign-in then works too', function () {
    $user = rpUser(['password' => null]);
    expect($user->signsInWithGoogleOnly())->toBeTrue();

    rpFirebaseOk();

    $this->post(route('reset-password.update'), [
        'oobCode'               => 'good-code',
        'password'              => 'Brand!New2',
        'password_confirmation' => 'Brand!New2',
    ])->assertRedirect(route('login'));

    expect($user->fresh()->signsInWithGoogleOnly())->toBeFalse();
});

it('rejects a weak password without contacting Firebase', function () {
    $user = rpUser();

    $this->from(route('reset-password', ['oobCode' => 'good-code']))
        ->post(route('reset-password.update'), [
            'oobCode'               => 'good-code',
            'password'              => 'weak',
            'password_confirmation' => 'weak',
        ])
        ->assertSessionHasErrors('password');

    Http::assertNothingSent();
    expect(Hash::check('Old!Password1', $user->fresh()->password))->toBeTrue();
});

it('rejects a password that does not match its confirmation', function () {
    rpUser();

    $this->post(route('reset-password.update'), [
        'oobCode'               => 'good-code',
        'password'              => 'Brand!New2',
        'password_confirmation' => 'Different!2',
    ])->assertSessionHasErrors('password');

    Http::assertNothingSent();
});

it('tells the user to request a new link when the code is expired or already used', function () {
    $user = rpUser();
    rpFirebaseRejects('EXPIRED_OOB_CODE');

    $this->from(route('reset-password', ['oobCode' => 'stale']))
        ->post(route('reset-password.update'), [
            'oobCode'               => 'stale',
            'password'              => 'Brand!New2',
            'password_confirmation' => 'Brand!New2',
        ])
        ->assertRedirect(route('reset-password', ['oobCode' => 'stale']))
        ->assertSessionHasErrors('password');

    expect(Hash::check('Old!Password1', $user->fresh()->password))->toBeTrue();
});

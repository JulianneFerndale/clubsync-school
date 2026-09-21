<?php

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/*
 * POLICY.md §Authentication: "a user may change their password at most once every
 * 7 calendar days. Enforce server-side using the lastPasswordChanged timestamp.
 * Display the next available change date on rejection."
 *
 * See TASK/tests-logs.md Test 6, which found neither the cooldown nor the
 * change-password feature it gates existed before this.
 */

const CURRENT_PASSWORD = 'OldPassw0rd!';
const NEW_PASSWORD     = 'NewPassw0rd!';

function makeCooldownUser(?string $lastChanged = null): User
{
    static $n = 0;
    $n++;

    return User::create([
        'first_name'            => 'Pass',
        'last_name'             => "User{$n}",
        'edp_number'            => str_pad((string) $n, 10, '0', STR_PAD_LEFT),
        'email'                 => "passuser{$n}@sccpag.edu.ph",
        'password'              => Hash::make(CURRENT_PASSWORD),
        'firebase_uid'          => "firebase-uid-{$n}",
        'role'                  => 'member',
        'course_id'             => testCourse()->id,
        'is_admin'              => false,
        'is_suspended'          => false,
        'last_password_changed' => $lastChanged,
    ]);
}

/** A JWT-shaped (but unsigned) token whose payload decodes locally as unexpired. */
function cooldownIdToken(): string
{
    $header  = rtrim(strtr(base64_encode(json_encode(['alg' => 'none'])), '+/', '-_'), '=');
    $payload = rtrim(strtr(base64_encode(json_encode(['exp' => time() + 3600])), '+/', '-_'), '=');

    return "{$header}.{$payload}.signature";
}

function cooldownSession(User $user): array
{
    return [
        'firebase_id_token'  => cooldownIdToken(),
        'firebase_uid'       => $user->firebase_uid,
        'firebase_user_role' => $user->role,
        'firebase_user_id'   => $user->id,
        'firebase_is_admin'  => false,
    ];
}

/** Firebase responds happily to both the re-auth and the password update. */
function fakeFirebaseOk(): void
{
    Http::fake([
        '*accounts:signInWithPassword*' => Http::response([
            'idToken'      => 'fresh-id-token',
            'localId'      => 'firebase-uid-1',
            'refreshToken' => 'fresh-refresh-token',
        ]),
        '*accounts:update*' => Http::response([
            'idToken'      => 'rotated-id-token',
            'refreshToken' => 'rotated-refresh-token',
        ]),
    ]);
}

function submitPasswordChange(User $user, array $overrides = []): Illuminate\Testing\TestResponse
{
    return test()
        ->withSession(cooldownSession($user))
        ->from(route('profile'))
        ->post(route('profile.password.update'), array_merge([
            'current_password'      => CURRENT_PASSWORD,
            'password'              => NEW_PASSWORD,
            'password_confirmation' => NEW_PASSWORD,
        ], $overrides));
}

it('allows a password change when the user has never changed it', function () {
    fakeFirebaseOk();
    $user = makeCooldownUser();

    $response = submitPasswordChange($user);

    $response->assertRedirect(route('profile'));
    $response->assertSessionHas('success');

    $user->refresh();
    expect(Hash::check(NEW_PASSWORD, $user->password))->toBeTrue()
        ->and($user->last_password_changed)->not->toBeNull();

    // The change is audited, without any password material in the metadata.
    $entry = AuditLog::where('action', 'auth.password.changed')->latest('id')->first();
    expect($entry)->not->toBeNull()
        ->and($entry->actor_id)->toBe($user->id)
        ->and($entry->resource_id)->toBe($user->id)
        ->and(json_encode($entry->metadata))->not->toContain(NEW_PASSWORD);
});

it('blocks a password change inside the 7-day window and names the next available date', function () {
    fakeFirebaseOk();
    $user = makeCooldownUser(now()->subDays(3)->toDateTimeString());

    $response = submitPasswordChange($user);

    $response->assertSessionHasErrors('password');

    $expectedDate = now()->subDays(3)->addDays(User::PASSWORD_COOLDOWN_DAYS)->format('F j, Y');
    expect(session('errors')->first('password'))->toContain($expectedDate);

    // Nothing changed, and Firebase was never called.
    $user->refresh();
    expect(Hash::check(CURRENT_PASSWORD, $user->password))->toBeTrue();
    expect(AuditLog::where('action', 'auth.password.changed')->count())->toBe(0);
    Http::assertNothingSent();
});

it('permits the change once the 7-day window has elapsed', function () {
    fakeFirebaseOk();
    $user = makeCooldownUser(now()->subDays(8)->toDateTimeString());

    submitPasswordChange($user)->assertSessionHas('success');

    $user->refresh();
    expect(Hash::check(NEW_PASSWORD, $user->password))->toBeTrue()
        ->and($user->last_password_changed->isToday())->toBeTrue();
});

it('permits the change exactly on the 7th day boundary', function () {
    fakeFirebaseOk();
    $user = makeCooldownUser(now()->subDays(User::PASSWORD_COOLDOWN_DAYS)->toDateTimeString());

    submitPasswordChange($user)->assertSessionHas('success');

    expect(Hash::check(NEW_PASSWORD, $user->refresh()->password))->toBeTrue();
});

it('rejects a new password that fails the POLICY complexity rules', function () {
    fakeFirebaseOk();
    $user = makeCooldownUser();

    $response = submitPasswordChange($user, [
        'password'              => 'alllowercase',
        'password_confirmation' => 'alllowercase',
    ]);

    $response->assertSessionHasErrors('password');

    $user->refresh();
    expect(Hash::check(CURRENT_PASSWORD, $user->password))->toBeTrue()
        ->and($user->last_password_changed)->toBeNull();
});

it('rejects a wrong current password without starting a new cooldown', function () {
    Http::fake([
        '*accounts:signInWithPassword*' => Http::response(['error' => ['message' => 'INVALID_PASSWORD']], 400),
    ]);
    $user = makeCooldownUser();

    $response = submitPasswordChange($user, ['current_password' => 'WrongPassw0rd!']);

    $response->assertSessionHasErrors('current_password');

    $user->refresh();
    expect(Hash::check(CURRENT_PASSWORD, $user->password))->toBeTrue()
        ->and($user->last_password_changed)->toBeNull();
    expect(AuditLog::where('action', 'auth.password.changed')->count())->toBe(0);
});

it('shows the cooldown notice instead of the form on the profile page', function () {
    $user = makeCooldownUser(now()->subDays(2)->toDateTimeString());

    $response = $this->withSession(cooldownSession($user))->get(route('profile'));

    $response->assertOk();
    $response->assertSee('You can change it again on', escape: false);
    $response->assertSee(now()->subDays(2)->addDays(User::PASSWORD_COOLDOWN_DAYS)->format('F j, Y'));
    $response->assertDontSee('name="current_password"', escape: false);
});

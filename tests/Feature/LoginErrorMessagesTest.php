<?php

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/*
 * A failed sign-in says what went wrong, so the person knows what to do next: register,
 * retype the password, wait, or fix their connection — never one message for all of it.
 */

beforeEach(function () {
    config([
        'firebase.api_key'     => 'test-key',
        'firebase.sign_in_url' => 'https://identitytoolkit.googleapis.com/v1/accounts:signInWithPassword',
    ]);
});

function leUser(array $overrides = []): User
{
    return User::create(array_merge([
        'first_name'   => 'Login',
        'last_name'    => 'Tester',
        'edp_number'   => '0000008001',
        'email'        => 'login.tester@sccpag.edu.ph',
        'password'     => Hash::make('Str0ng!Pass1'),
        'firebase_uid' => 'login-uid-1',
        'role'         => 'member',
        'course_id'    => testCourse()->id,
        'is_admin'     => false,
        'is_suspended' => false,
    ], $overrides));
}

function leFirebaseRefuses(string $code): void
{
    Http::fake(['*' => Http::response(['error' => ['message' => $code]], 400)]);
}

function leSignIn(string $email = 'login.tester@sccpag.edu.ph')
{
    return test()->from(route('login'))->post('/login', ['email' => $email, 'password' => 'Wrong-pass1']);
}

it('says an address with no ClubSync account is not registered when the password opens no unfinished registration', function () {
    // Firebase is asked only whether this is a registration left unfinished
    // (RegistrationResumeTest); it refuses, as it does for an unknown address.
    leFirebaseRefuses('INVALID_LOGIN_CREDENTIALS');

    leSignIn('nobody@sccpag.edu.ph')
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => 'This email is not registered in ClubSync yet. Tap Register below to create your account.'])
        ->assertSessionDoesntHaveErrors('password');
});

it('says the password is wrong when the account exists', function (string $code) {
    leUser();
    leFirebaseRefuses($code);

    leSignIn()
        ->assertSessionHasErrors(['password' => 'The password you entered is incorrect. Try again, or tap Forgot Password to reset it.'])
        ->assertSessionDoesntHaveErrors('email');
})->with(['INVALID_LOGIN_CREDENTIALS', 'INVALID_PASSWORD']);

it('matches the registered address whatever its letter case', function () {
    leUser();
    leFirebaseRefuses('INVALID_LOGIN_CREDENTIALS');

    leSignIn('Login.Tester@SCCPAG.edu.ph')->assertSessionHasErrors('password');
});

it('says when Firebase has locked the account after too many wrong passwords', function () {
    leUser();
    leFirebaseRefuses('TOO_MANY_ATTEMPTS_TRY_LATER : Access to this account has been temporarily disabled due to many failed login attempts.');

    leSignIn()->assertSessionHasErrors(['password' => 'Too many wrong passwords for this account. Please wait a few minutes, or tap Forgot Password to reset it.']);
});

it('reports a disabled Firebase account as suspended', function () {
    leUser();
    leFirebaseRefuses('USER_DISABLED');

    leSignIn()->assertSessionHasErrors(['email' => 'Your account has been suspended. Please contact the Director of Student Affairs for assistance.']);
});

it('reports no connection to Firebase as a connection problem, not wrong credentials', function () {
    leUser();
    Http::fake(['*' => Http::failedConnection()]);

    leSignIn()
        ->assertSessionHas('error', 'ClubSync could not connect. Please check your internet connection and try again.')
        ->assertSessionHasNoErrors();
});

it('reports no connection to the database as a connection problem', function () {
    Http::fake();
    // What a dropped PostgreSQL connection throws when the lookup runs.
    DB::beforeExecuting(function (string $query, array $bindings) {
        throw new QueryException('pgsql', $query, $bindings, new PDOException('SQLSTATE[08006] [7] could not translate host name to address'));
    });

    leSignIn()
        ->assertSessionHas('error', 'ClubSync could not connect. Please check your internet connection and try again.')
        ->assertSessionHasNoErrors();

    Http::assertNothingSent();
});

it('reports a Firebase fault the person cannot fix as the service being unavailable', function () {
    leUser();
    leFirebaseRefuses('API key not valid. Please pass a valid API key.');

    leSignIn()
        ->assertSessionHas('error', 'Sign-in is unavailable right now. Please try again in a few minutes.')
        ->assertSessionHasNoErrors();
});

it('keeps the typed email but never the password', function () {
    leUser();
    leFirebaseRefuses('INVALID_LOGIN_CREDENTIALS');

    leSignIn()->assertSessionHasInput('email', 'login.tester@sccpag.edu.ph')->assertSessionMissing('_old_input.password');
});

it('still signs a registered user in', function () {
    leUser();
    Http::fake(['*' => Http::response(['idToken' => 'id-token', 'localId' => 'login-uid-1', 'refreshToken' => 'refresh'])]);

    leSignIn()->assertRedirect(route('member.dashboard'))->assertSessionHasNoErrors();
});

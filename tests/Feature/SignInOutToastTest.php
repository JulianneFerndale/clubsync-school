<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/*
 * Logging in and logging out are each confirmed by a toast (<x-toast />) on the page
 * the person lands on: shown once, gone by itself after three seconds, never a dialog.
 */

beforeEach(function () {
    config([
        'firebase.api_key'     => 'test-key',
        'firebase.sign_in_url' => 'https://identitytoolkit.googleapis.com/v1/accounts:signInWithPassword',
    ]);

    Http::preventStrayRequests();
});

function toastUser(array $overrides = []): User
{
    return User::create(array_merge([
        'first_name'   => 'Toast',
        'last_name'    => 'Tester',
        'edp_number'   => '0000008101',
        'email'        => 'toast.tester@sccpag.edu.ph',
        'password'     => Hash::make('Str0ng!Pass1'),
        'firebase_uid' => 'toast-uid-1',
        'role'         => 'member',
        'course_id'    => testCourse()->id,
        'is_admin'     => false,
        'is_suspended' => false,
    ], $overrides));
}

function toastToken(): string
{
    $part = fn (array $data) => rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');

    return $part(['alg' => 'none']) . '.' . $part(['exp' => time() + 3600]) . '.sig';
}

function toastSignIn()
{
    Http::fake(['*' => Http::response(['idToken' => toastToken(), 'localId' => 'toast-uid-1', 'refreshToken' => 'refresh'])]);

    return test()->from(route('login'))->post('/login', ['email' => 'toast.tester@sccpag.edu.ph', 'password' => 'Str0ng!Pass1']);
}

it('confirms a login with a toast on the dashboard, once', function () {
    toastUser();

    toastSignIn()
        ->assertRedirect(route('member.dashboard'))
        ->assertSessionHas('toast', ['title' => 'Logged in successfully', 'text' => 'Welcome back, Toast!']);

    $this->get(route('member.dashboard'))
        ->assertOk()
        ->assertSee('id="cs-toast"', false)
        ->assertSee('data-title="Logged in successfully"', false)
        ->assertSee('data-text="Welcome back, Toast!"', false);

    $this->get(route('member.dashboard'))->assertOk()->assertDontSee('id="cs-toast"', false);
});

it('uses the toast, not the flash dialog, for a login', function () {
    toastUser();

    toastSignIn()->assertSessionMissing('success')->assertSessionMissing('info');
});

it('does not confirm a login that failed', function () {
    toastUser();
    Http::fake(['*' => Http::response(['error' => ['message' => 'INVALID_LOGIN_CREDENTIALS']], 400)]);

    $this->from(route('login'))->post('/login', ['email' => 'toast.tester@sccpag.edu.ph', 'password' => 'Wrong-pass1'])
        ->assertSessionMissing('toast');
});

it('carries the login toast through to the course step for a student with no course', function () {
    toastUser(['course_id' => null]);

    toastSignIn()->assertRedirect(route('member.dashboard'));

    $this->get(route('member.dashboard'))->assertRedirect(route('onboarding.course'));

    $this->get(route('onboarding.course'))->assertOk()->assertSee('data-title="Logged in successfully"', false);
});

it('confirms a logout with a toast on the page after it, once', function () {
    Http::fake();
    $user = toastUser();

    $this->withSession([
        'firebase_id_token'  => toastToken(),
        'firebase_uid'       => $user->firebase_uid,
        'firebase_user_role' => $user->role,
        'firebase_user_id'   => $user->id,
        'firebase_is_admin'  => false,
        'course_selected'    => true,
    ])->post(route('logout'))
        ->assertRedirect(route('welcome'))
        ->assertSessionHas('toast', ['title' => 'Logged out successfully', 'text' => 'See you again soon!']);

    $this->get(route('welcome'))->assertOk()->assertSee('data-title="Logged out successfully"', false);

    $this->get(route('welcome'))->assertOk()->assertDontSee('id="cs-toast"', false);
});

it('is part of every layout', function (string $layout) {
    expect(file_get_contents(resource_path("views/layouts/{$layout}.blade.php")))->toContain('<x-toast />');
})->with(['auth', 'app-admin', 'app-dsa', 'app-adviser', 'app-officer', 'app-member']);

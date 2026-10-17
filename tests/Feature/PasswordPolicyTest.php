<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/*
 * POLICY.md §Authentication: at least 8 characters, with an uppercase letter, a
 * lowercase letter and a number. A special character is no longer required.
 * Registration and password change ask for exactly the same thing.
 */

beforeEach(function () {
    config([
        'firebase.api_key'     => 'test-key',
        'firebase.sign_up_url' => 'https://identitytoolkit.googleapis.com/v1/accounts:signUp',
        'firebase.sign_in_url' => 'https://identitytoolkit.googleapis.com/v1/accounts:signInWithPassword',
        'firebase.update_url'  => 'https://identitytoolkit.googleapis.com/v1/accounts:update',
    ]);

    Http::preventStrayRequests();
    Http::fake([
        '*accounts:signUp*'             => Http::response(['localId' => 'pp-uid', 'idToken' => 'pp-id', 'refreshToken' => 'pp-refresh']),
        '*accounts:signInWithPassword*' => Http::response(['localId' => 'pp-uid', 'idToken' => 'pp-id', 'refreshToken' => 'pp-refresh']),
        '*accounts:update*'             => Http::response(['idToken' => 'pp-id-2', 'refreshToken' => 'pp-refresh-2']),
    ]);
});

function ppRegister(string $password)
{
    return test()->from('/register')->post('/register', [
        'first_name' => 'Policy', 'last_name' => 'Tester', 'edp_number' => '0000006001',
        'email' => 'policy.tester@sccpag.edu.ph',
        'password' => $password, 'password_confirmation' => $password,
        'mobile_number' => '09171234567', 'year_level' => 1, 'terms' => 'on',
    ]);
}

function ppSignedInUser(): User
{
    $user = User::create([
        'first_name' => 'Policy', 'last_name' => 'Changer', 'edp_number' => '0000006002',
        'email' => 'policy.changer@sccpag.edu.ph', 'password' => Hash::make('Oldpass12'),
        'firebase_uid' => 'pp-uid', 'role' => 'member', 'course_id' => testCourse()->id,
        'is_admin' => false, 'is_suspended' => false,
    ]);

    $part = fn (array $data) => rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');

    test()->withSession([
        'firebase_id_token'  => $part(['alg' => 'none']) . '.' . $part(['exp' => time() + 3600]) . '.sig',
        'firebase_uid'       => 'pp-uid',
        'firebase_user_role' => 'member',
        'firebase_user_id'   => $user->id,
        'firebase_is_admin'  => false,
        'course_selected'    => true,
        'enrollment_cleared' => true,
    ]);

    return $user;
}

it('accepts a password without a special character at registration', function () {
    ppRegister('Abcdefg1')->assertRedirect(route('register.department'))->assertSessionHasNoErrors();
});

it('still refuses a weak password at registration', function (string $password) {
    ppRegister($password)->assertSessionHasErrors('password');
})->with([
    'no uppercase letter'  => ['abcdefg1'],
    'no lowercase letter'  => ['ABCDEFG1'],
    'no number'            => ['Abcdefgh'],
    'under 8 characters'   => ['Abcde12'],
]);

it('accepts a password without a special character when changing it', function () {
    $user = ppSignedInUser();

    $this->from('/profile')->post('/profile/password', [
        'current_password' => 'Oldpass12', 'password' => 'Newpass12', 'password_confirmation' => 'Newpass12',
    ])->assertSessionHasNoErrors()->assertSessionHas('success');

    expect($user->fresh()->last_password_changed)->not->toBeNull();
});

it('applies the same rule when changing a password', function () {
    ppSignedInUser();

    $this->from('/profile')->post('/profile/password', [
        'current_password' => 'Oldpass12', 'password' => 'newpass12', 'password_confirmation' => 'newpass12',
    ])->assertSessionHasErrors('password');
});

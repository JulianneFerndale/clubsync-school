<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/*
 * Every throttled route counts against its own allowance.
 *
 * Laravel keys an unnamed throttle by IP alone (there is never a Laravel user to key
 * by: sign-in is Firebase), so before each throttle named a bucket they all shared one
 * counter. The live email check fired while a student typed their address, and the
 * registration wizard then answered 429 Too Many Requests part-way through.
 */

beforeEach(function () {
    config([
        'firebase.api_key'     => 'test-key',
        'firebase.sign_up_url' => 'https://identitytoolkit.googleapis.com/v1/accounts:signUp',
        'firebase.sign_in_url' => 'https://identitytoolkit.googleapis.com/v1/accounts:signInWithPassword',
    ]);

    Http::fake(['*' => Http::response(['error' => ['message' => 'INVALID_LOGIN_CREDENTIALS']], 400)]);
});

it('does not let the live email check use up the registration allowance', function () {
    foreach (range(1, 8) as $i) {
        $this->postJson('/register/check-email', ['email' => "typing{$i}@sccpag.edu.ph"])->assertOk();
    }

    // A validation redirect, not 429: step 1 still has its own five a minute.
    $this->post('/register', [])->assertRedirect();
});

it('keeps sign-in attempts and the registration wizard apart, and still limits each', function () {
    foreach (range(1, 5) as $i) {
        $this->post('/login', ['email' => 'someone@sccpag.edu.ph', 'password' => 'Wrong-pass1'])->assertRedirect();
    }

    $this->post('/login', ['email' => 'someone@sccpag.edu.ph', 'password' => 'Wrong-pass1'])->assertTooManyRequests();

    $this->post('/register', [])->assertRedirect();
});

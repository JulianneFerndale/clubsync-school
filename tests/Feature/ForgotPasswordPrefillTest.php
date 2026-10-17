<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * The login page carries the email already typed there over to the Forgot Password page
 * (?email=), where it is prefilled so the person does not retype it. Only a well-formed
 * address is accepted; anything else opens the page blank.
 */

it('prefills the email field from a valid ?email query', function () {
    $this->get(route('forgot-password', ['email' => 'juan.delacruz@sccpag.edu.ph']))
        ->assertOk()
        ->assertSee('value="juan.delacruz@sccpag.edu.ph"', false);
});

it('opens with a blank field when no email is passed', function () {
    $this->get(route('forgot-password'))
        ->assertOk()
        ->assertSee('name="email"', false)
        ->assertSee('value=""', false);
});

it('ignores a malformed email instead of prefilling it', function () {
    $this->get(route('forgot-password', ['email' => 'not-an-email']))
        ->assertOk()
        ->assertDontSee('not-an-email');
});

it('does not reflect script-like input into the page', function () {
    // filter_var rejects it, so it never reaches the value attribute; Blade would escape
    // it regardless. Guards against the prefill becoming a reflected-XSS sink.
    $payload = '"><script>alert(1)</script>';

    $response = $this->get(route('forgot-password', ['email' => $payload]))->assertOk();

    expect($response->getContent())->not->toContain('<script>alert(1)</script>');
});

it('offers the email carry-over from the login page', function () {
    // The login page tags its Forgot Password link and email field so its script can
    // copy one into the other; without these hooks the carry-over silently stops working.
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('data-forgot-link', false)
        ->assertSee('id="email"', false);
});

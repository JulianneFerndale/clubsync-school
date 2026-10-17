<?php

use App\Models\Club;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

/*
 * ClubSync's own error pages: every HTTP error a user can reach renders the
 * branded page from resources/views/errors, never Laravel's stock screen, and
 * never leaks internals such as model class names or exception messages.
 */

// ── Fixtures ─────────────────────────────────────────────────────────────────

function errPageUser(string $role = 'member'): User
{
    static $n = 0;
    $n++;

    return User::create([
        'first_name'   => 'Err',
        'last_name'    => "User{$n}",
        'edp_number'   => str_pad((string) (8100 + $n), 10, '0', STR_PAD_LEFT),
        'email'        => "errpage{$n}@sccpag.edu.ph",
        'password'     => Hash::make('Password1!'),
        'role'         => $role,
        'course_id'    => testCourse()->id,
        'is_admin'     => false,
        'is_suspended' => false,
    ]);
}

function errPageSession(User $user): array
{
    $header  = rtrim(strtr(base64_encode(json_encode(['alg' => 'none'])), '+/', '-_'), '=');
    $payload = rtrim(strtr(base64_encode(json_encode(['exp' => time() + 3600])), '+/', '-_'), '=');

    return [
        'firebase_id_token'  => "{$header}.{$payload}.signature",
        'firebase_uid'       => 'uid-' . $user->id,
        'firebase_user_role' => $user->role,
        'firebase_user_id'   => $user->id,
        'firebase_is_admin'  => $user->is_admin,
    ];
}

/** A throwaway route inside the web group, so the session is started as in the app. */
function errPageRoute(string $uri, Closure $action): string
{
    Route::middleware('web')->get($uri, $action);

    return $uri;
}

// ── 404 ──────────────────────────────────────────────────────────────────────

it('shows the ClubSync 404 page for an unknown address', function () {
    $this->get('/no-such-page-anywhere')
        ->assertNotFound()
        ->assertSee("We couldn't find that page")
        ->assertSee('Error 404 · Page Not Found')
        // No session reaches an unmatched URL, so the button goes through login,
        // which forwards a signed-in user on to their dashboard.
        ->assertSee('href="' . route('login') . '"', false)
        ->assertSee('Go to ClubSync');
});

it('does not leak the model name when a record is missing', function () {
    $member = errPageUser();

    $this->withSession(errPageSession($member))
        ->get(route('member.clubs.show', 999999))
        ->assertNotFound()
        ->assertSee("We couldn't find that page")
        ->assertSee('Back to my dashboard')
        ->assertSee('href="' . route('member.dashboard') . '"', false)
        ->assertDontSee('No query results')
        ->assertDontSee('App\Models', false);
});

// ── 403 ──────────────────────────────────────────────────────────────────────

it('shows the ClubSync 403 page when a member opens a DSA page', function () {
    $member = errPageUser();

    $this->withSession(errPageSession($member))
        ->get(route('dsa.dashboard'))
        ->assertForbidden()
        ->assertSee("This page isn't open to your account")
        ->assertSee('Back to my dashboard')
        // A bare abort(403) has no reason worth showing.
        ->assertDontSee('role="note"', false);
});

it('shows the reason a 403 was raised with', function () {
    $uri = errPageRoute('/_err/forbidden', fn () => abort(403, 'Only the club president can submit reports to the DSA.'));

    $this->get($uri)
        ->assertForbidden()
        ->assertSee('Only the club president can submit reports to the DSA.');
});

it("hides Laravel's stock 403 wording", function () {
    $uri = errPageRoute('/_err/unauthorized', fn () => abort(403, 'This action is unauthorized.'));

    $this->get($uri)
        ->assertForbidden()
        ->assertDontSee('This action is unauthorized.');
});

// ── Other 4xx ────────────────────────────────────────────────────────────────

it('shows the ClubSync 405 page for a form-only address opened directly', function () {
    $this->get('/logout')
        ->assertStatus(405)
        ->assertSee('That address only works from a form');
});

it('shows the ClubSync 413 page for an oversized upload', function () {
    $uri = errPageRoute('/_err/too-large', fn () => throw new PostTooLargeException());

    $this->get($uri)
        ->assertStatus(413)
        ->assertSee('That file is too big to upload')
        ->assertSee('Go back and try again');
});

it('tells a throttled user how long to wait', function () {
    $uri = errPageRoute('/_err/throttled', fn () => throw new ThrottleRequestsException('Too Many Attempts.', null, ['Retry-After' => 30]));

    $this->get($uri)
        ->assertStatus(429)
        ->assertSee("Let's take a quick breather")
        ->assertSee('about 30 seconds');
});

it('keeps both 419 variants on the shared page', function () {
    $uri = errPageRoute('/_err/expired', fn () => abort(419));

    $this->get($uri)
        ->assertStatus(419)
        ->assertSee('Welcome back — your session took a rest')
        ->assertSee('Error 419 · Page Expired');

    $this->withSession(errPageSession(errPageUser()))
        ->get($uri)
        ->assertStatus(419)
        ->assertSee('That page rested a little too long')
        ->assertSee('href="' . route('member.dashboard') . '"', false);
});

it('falls back to the ClubSync 4xx page for other client errors', function () {
    $uri = errPageRoute('/_err/conflict', fn () => abort(409));

    $this->get($uri)
        ->assertStatus(409)
        ->assertSee("That request couldn't be completed")
        ->assertSee('Error 409 · Conflict');
});

// ── 5xx ──────────────────────────────────────────────────────────────────────

it('shows the ClubSync 500 page without the exception message', function () {
    config(['app.debug' => false]);
    $uri = errPageRoute('/_err/boom', fn () => throw new RuntimeException('secret internal detail in /var/www/app'));

    $this->get($uri)
        ->assertStatus(500)
        ->assertSee('ClubSync ran into a problem on our side')
        ->assertDontSee('secret internal detail');
});

it('shows the ClubSync 503 page', function () {
    $uri = errPageRoute('/_err/maintenance', fn () => abort(503));

    $this->get($uri)
        ->assertStatus(503)
        ->assertSee('ClubSync is getting a quick tune-up');
});

// ── Member club page ─────────────────────────────────────────────────────────

it('names the club adviser even when the club has no description', function () {
    $adviser = errPageUser('adviser');
    $member  = errPageUser();

    $club = Club::create([
        'slug'            => 'err-adviser-club',
        'acronym'         => 'EAC',
        'club_type'       => 'Non-Academic',
        'type'            => 'non_academic',
        'course_slug'     => 'all',
        'department_slug' => 'all',
        'name'            => 'Adviser Card Club',
        'description'     => null,
        'adviser_id'      => $adviser->id,
        'is_active'       => true,
    ]);

    $this->withSession(errPageSession($member))
        ->get(route('member.clubs.show', $club))
        ->assertOk()
        ->assertSee('Faculty Adviser')
        ->assertSee($adviser->name)
        ->assertSee("This club hasn't added a description yet.", false);
});

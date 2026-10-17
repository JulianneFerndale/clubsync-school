<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

/*
 * Logging out is complete: nothing left in the session, the cookies or the browser's
 * cache can sign the user back in, so every private page and action needs the email
 * and password again. (The service worker's side of this is in public/sw.js and was
 * checked in a browser.)
 */

/** The only routes anyone may use without signing in. Anything else is private. */
const LO_PUBLIC_ROUTES = [
    'GET /', 'GET welcome', 'GET terms', 'GET offline', 'GET ping', 'GET up',
    'GET login', 'POST login',
    'GET register', 'POST register', 'POST register/check-email',
    'GET register/department', 'POST register/department',
    'GET register/course', 'POST register/course',
    'GET register/verify', 'POST register/verify',
    'GET forgot-password', 'POST forgot-password',
    'GET reset-password', 'POST reset-password',
    'GET auth/google', 'GET auth/google/callback', 'GET auth/google/link', 'POST auth/google/link',
    'POST register/google/cancel',
    'GET storage/{path}', 'PUT storage/{path}',
];

function loUser(string $role = 'member', bool $isAdmin = false): User
{
    static $n = 0;
    $n++;

    return User::create([
        'first_name'   => 'Logout',
        'last_name'    => "Tester{$n}",
        'edp_number'   => str_pad((string) (7000 + $n), 10, '0', STR_PAD_LEFT),
        'email'        => "logout.tester{$n}@sccpag.edu.ph",
        'password'     => Hash::make('Str0ng!Pass1'),
        'firebase_uid' => "logout-uid-{$n}",
        'role'         => $role,
        'course_id'    => testCourse()->id,
        'is_admin'     => $isAdmin,
        'is_suspended' => false,
    ]);
}

function loToken(): string
{
    $part = fn (array $data) => rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');

    return $part(['alg' => 'none']) . '.' . $part(['exp' => time() + 3600]) . '.sig';
}

/** The session SessionManager::store() leaves after a Firebase sign-in with "Remember Me". */
function loSession(User $user): array
{
    return [
        'firebase_id_token'      => loToken(),
        'firebase_uid'           => $user->firebase_uid,
        'firebase_user_role'     => $user->role,
        'firebase_user_id'       => $user->id,
        'firebase_is_admin'      => $user->is_admin,
        'firebase_refresh_token' => 'refresh-token-from-sign-in',
        'course_selected'        => true,
    ];
}

/** Where each kind of user lands after signing in. */
function loDashboard(User $user): string
{
    if ($user->is_admin) {
        return route('admin.dashboard');
    }

    return match ($user->role) {
        'dsa'     => route('dsa.dashboard'),
        'adviser' => route('adviser.dashboard'),
        default   => route('member.dashboard'),
    };
}

/** Every route the app declares, as "METHOD uri", with whether it needs sign-in. */
function loRoutes(): array
{
    $routes = [];
    foreach (Route::getRoutes()->getRoutes() as $route) {
        $private = collect($route->gatherMiddleware())
            ->contains(fn ($m) => is_string($m) && str_starts_with($m, 'firebase.token'));

        foreach (array_diff($route->methods(), ['HEAD']) as $method) {
            $routes["{$method} {$route->uri()}"] = $private;
        }
    }

    return $routes;
}

beforeEach(function () {
    // Nothing here may reach Firebase unless a test says so: a way back in that
    // skipped the password would show up as a request.
    Http::preventStrayRequests();
});

it('ends the session, the Firebase tokens and the Remember Me cookie', function () {
    Http::fake();
    $user = loUser();

    $this->withSession(loSession($user))
        ->withCookie('clubsync_refresh', 'refresh-token-from-sign-in')
        ->post(route('logout'))
        ->assertRedirect(route('welcome'))
        ->assertCookieExpired('clubsync_refresh')
        ->assertSessionMissing(['firebase_id_token', 'firebase_refresh_token', 'firebase_user_id', 'firebase_uid']);
});

it('tells the browser to drop the site cache, so Back cannot show a signed-in page', function () {
    Http::fake();

    $this->withSession(loSession(loUser()))
        ->post(route('logout'))
        ->assertHeader('Clear-Site-Data', '"cache"');
});

it('marks the page after logout, once, so other open tabs sign out too', function () {
    Http::fake();

    $this->withSession(loSession(loUser()))->post(route('logout'))
        ->assertSessionHas('logged_out', true);

    // The page the logout lands on carries the signal (read by resources/js/app.js)…
    $this->get(route('welcome'))->assertSee('name="cs-logged-out"', false);

    // …and only that page: a later visit is not a logout.
    $this->get(route('welcome'))->assertDontSee('name="cs-logged-out"', false);
});

it('asks for the email and password again after logout', function () {
    Http::fake();
    $user = loUser();

    // Signed in: the dashboard opens.
    $this->withSession(loSession($user))->get(route('member.dashboard'))->assertOk();

    $this->post(route('logout'));

    // Logged out: signed-in pages send them to the sign-in form, which is empty.
    $this->get(route('member.dashboard'))->assertRedirect(route('login'));
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('name="password"', false)
        ->assertDontSee($user->email);

    Http::assertNothingSent();
});

it('does not let a leftover Remember Me cookie sign anyone back in', function () {
    // A browser that failed to delete the cookie still sends it; it must not be a way in.
    Http::fake();
    $user = loUser();

    $this->withSession(loSession($user))->post(route('logout'));

    $this->withCookie('clubsync_refresh', 'refresh-token-from-sign-in')
        ->get(route('member.dashboard'))
        ->assertRedirect(route('login'));

    $this->withCookie('clubsync_refresh', 'refresh-token-from-sign-in')
        ->get(route('login'))
        ->assertOk()
        ->assertSee('name="password"', false);

    Http::assertNothingSent();
});

it('keeps everything but the public pages behind sign-in', function () {
    // A route added later without the sign-in check fails here.
    $open = array_keys(array_filter(loRoutes(), fn ($private) => ! $private));

    expect(array_values(array_diff($open, LO_PUBLIC_ROUTES)))->toBe([]);
});

it('refuses every private page and action after logout', function (string $role, bool $isAdmin) {
    Http::fake();
    $user = loUser($role, $isAdmin);

    // Signed in: their own dashboard opens.
    $this->withSession(loSession($user))->get(loDashboard($user))->assertOk();

    $this->post(route('logout'))->assertRedirect(route('welcome'));

    // Logged out: every private route, read or write, sends them to sign in. Ids are
    // made up on purpose — the answer must not depend on whether a record exists.
    $private = array_keys(array_filter(loRoutes()));
    $let_in = [];

    foreach ($private as $route) {
        [$method, $uri] = explode(' ', $route, 2);
        $url = '/' . ltrim(preg_replace('/\{[^}]+\}/', '1', $uri), '/');

        $response = $this->call($method, $url);

        if (! $response->isRedirect(route('login'))) {
            $let_in[] = "{$route} → {$response->getStatusCode()}";
        }
    }

    expect(count($private))->toBeGreaterThan(100)
        ->and($let_in)->toBe([]);

    Http::assertNothingSent();
})->with([
    'student member' => ['member', false],
    'club officer'   => ['president', false],
    'adviser'        => ['adviser', false],
    'DSA'            => ['dsa', false],
    'administrator'  => ['member', true],
]);

it('lets them back in only by signing in with their email and password', function () {
    $user = loUser();

    Http::fake([
        '*accounts:signInWithPassword*' => function ($request) use ($user) {
            return $request['password'] === 'Str0ng!Pass1'
                ? Http::response(['idToken' => loToken(), 'localId' => $user->firebase_uid, 'refreshToken' => 'fresh-refresh-token'])
                : Http::response(['error' => ['message' => 'INVALID_LOGIN_CREDENTIALS']], 400);
        },
    ]);

    $this->withSession(loSession($user))->post(route('logout'));
    $this->get(route('member.dashboard'))->assertRedirect(route('login'));

    // The wrong password: still out.
    $this->post(route('login'), ['email' => $user->email, 'password' => 'not-the-password'])
        ->assertSessionHasErrors('password');
    $this->get(route('member.dashboard'))->assertRedirect(route('login'));

    // The email and password: back in.
    $this->post(route('login'), ['email' => $user->email, 'password' => 'Str0ng!Pass1'])
        ->assertRedirect(route('member.dashboard'));
    $this->get(route('member.dashboard'))->assertOk();
});

<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Auth\Concerns\ExplainsSignInFailures;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\RegistrationDraft;
use App\Models\User;
use App\Services\AuthManager;
use App\Services\SessionManager;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LoginController extends Controller
{
    use ExplainsSignInFailures;

    public function __construct(
        private AuthManager $auth,
        private SessionManager $session,
    ) {}

    public function show(): View
    {
        return view('auth.login');
    }

    public function authenticate(Request $request): RedirectResponse
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        // The users table is asked first: Firebase's email enumeration protection
        // answers an unknown address and a wrong password with the same code, so only
        // ClubSync's own records can say "not registered".
        try {
            $user = User::whereRaw('LOWER(email) = ?', [strtolower($request->email)])->first();
        } catch (QueryException $e) {
            report($e);

            return back()->with('error', self::CONNECTION_FAILED)->onlyInput('email');
        }

        if (! $user) {
            return $this->resumeRegistration($request)
                ?? back()->withErrors([
                    'email' => 'This email is not registered in ClubSync yet. Tap Register below to create your account.',
                ])->onlyInput('email');
        }

        try {
            $firebaseData = $this->auth->loginWithFirebase(
                $request->email,
                $request->password,
                $request->boolean('remember'),
            );
        } catch (ConnectionException) {
            return back()->with('error', self::CONNECTION_FAILED)->onlyInput('email');
        } catch (\Throwable $e) {
            // Registered with Google and never set a password: "incorrect password" would
            // send them hunting for one that does not exist.
            if ($user->signsInWithGoogleOnly() && in_array(trim(strtok($e->getMessage(), ':')), ['INVALID_LOGIN_CREDENTIALS', 'INVALID_PASSWORD'], true)) {
                return back()->withErrors([
                    'email' => 'This account signs in with Google. Tap Continue with Google below.',
                ])->onlyInput('email');
            }

            return $this->firebaseRefusal($e)->onlyInput('email');
        }

        if ($user->is_suspended) {
            return back()->withErrors(['email' => self::SUSPENDED])->onlyInput('email');
        }

        // A Google-only account whose Firebase side has since been given a password
        // (Forgot Password). Record that it has one, so it is no longer treated as
        // Google-only. The 'hashed' cast stores a hash, as at registration.
        if ($user->signsInWithGoogleOnly()) {
            $user->update(['password' => $request->password]);
        }

        return $this->session->signIn($user, $firebaseData);
    }

    /**
     * No ClubSync account, but perhaps a registration this person started and never
     * finished (POLICY.md §Registration): the Firebase account is made at step 1, the
     * ClubSync one only after the proof of enrollment. If the password typed opens that
     * Firebase account, they are taken back to the step they reached, not turned away.
     *
     * Null means nothing to resume, and the caller says the address is not registered.
     */
    private function resumeRegistration(Request $request): ?RedirectResponse
    {
        try {
            $account = $this->auth->loginWithFirebase($request->email, $request->password, remember: false);
        } catch (ConnectionException) {
            return back()->with('error', self::CONNECTION_FAILED)->onlyInput('email');
        } catch (\Throwable) {
            // Wrong password, or no Firebase account at all: Firebase does not say which.
            return null;
        }

        if (empty($account['localId'])) {
            return null;
        }

        $request->session()->regenerate();

        AuditLog::record('auth.registration.resumed', null, ['method' => 'password']);

        $draft = RegistrationDraft::forFirebaseUid($account['localId']);

        // Started before progress was saved: only step 1 can be asked again. Re-entering
        // the same password there picks the Firebase account back up.
        if (! $draft) {
            return redirect()->route('register')->withInput(['email' => strtolower($request->email)])->with('info',
                'You started registering with this email but did not finish. Fill in your details again, '
                . 'with the same password, to continue.');
        }

        return redirect()->route($draft->restore($account, $request->password))->with('info', self::RESUMED);
    }

    /**
     * Log out completely: the next sign-in needs the email and password again.
     *
     * The session and its Firebase tokens are destroyed, the "Remember Me" cookie is
     * deleted (revokeSession), and the browser is told to drop this site's cache so
     * Back cannot bring a signed-in page back. The service worker clears its own copy
     * on seeing this request (public/sw.js). The page after this one tells other open
     * tabs to sign out too (resources/js/app.js) — only then, once the session is gone:
     * told any earlier, a tab reached /login while still signed in and was sent back
     * to its dashboard.
     */
    public function logout(Request $request): RedirectResponse
    {
        $this->auth->revokeSession();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('welcome')
            ->with('logged_out', true)
            ->with('toast', ['title' => 'Logged out successfully', 'text' => 'See you again soon!'])
            ->header('Clear-Site-Data', '"cache"');
    }
}

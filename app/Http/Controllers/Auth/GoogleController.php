<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\GoogleSignInRefused;
use App\Http\Controllers\Auth\Concerns\ExplainsSignInFailures;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\RegistrationDraft;
use App\Models\User;
use App\Services\AuthManager;
use App\Services\GoogleSignIn;
use App\Services\SessionManager;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * "Continue with Google" — an added way to sign in, for SCC Google Workspace accounts
 * only (POLICY.md §Authentication). Email and password sign-in is unchanged.
 *
 * A Google account opens only the ClubSync account with the same address. The first
 * time, Firebase will not join the two on Google's word alone (it does that only for
 * gmail.com), so the person confirms their ClubSync password once and the Google
 * account is linked to it. An SCC Google account with no ClubSync account is taken to
 * registration with its address fixed.
 *
 * Started from the adviser pages (?as=adviser), it opens only an adviser account, and an
 * SCC Google account with no ClubSync account is taken to adviser sign-up instead.
 */
class GoogleController extends Controller
{
    use ExplainsSignInFailures;

    /** How long Google's answer is held while the person confirms their password. */
    private const LINK_MINUTES = 10;

    /**
     * How long it is held while they fill in registration. Inside the hour Google's ID
     * token lasts, so step 1 can still link it to the new account.
     */
    public const REGISTER_MINUTES = 30;

    private const LINK_EXPIRED = 'Your Google sign-in has expired. Please tap Continue with Google again.';

    /** Started from the adviser sign-in or sign-up page: failures go back there. */
    private bool $adviserFlow = false;

    public function __construct(
        private GoogleSignIn $google,
        private AuthManager $auth,
        private SessionManager $session,
    ) {}

    /** Send the browser to Google's account chooser, narrowed to SCC accounts. */
    public function redirect(Request $request): RedirectResponse
    {
        if (! $this->google->isConfigured()) {
            return $this->toLogin('Google sign-in is not available yet. Please sign in with your email and password.');
        }

        $start = $this->google->start();

        session(['google.oauth' => [
            'state'    => $start['state'],
            'nonce'    => $start['nonce'],
            'verifier' => $start['verifier'],
            'remember' => $request->boolean('remember'),
            'adviser'  => $request->query('as') === 'adviser',
        ]]);

        return redirect()->away($start['url']);
    }

    public function callback(Request $request): RedirectResponse
    {
        // One use only: a reloaded or replayed callback finds nothing to match.
        $oauth = session()->pull('google.oauth');

        if (! is_array($oauth) || ! hash_equals((string) $oauth['state'], (string) $request->query('state'))) {
            return $this->toLogin(self::LINK_EXPIRED);
        }

        $this->adviserFlow = (bool) ($oauth['adviser'] ?? false);

        if ($request->filled('error') || ! $request->filled('code')) {
            // access_denied: they closed or cancelled Google's screen.
            return $this->toLogin('Google sign-in was cancelled.', 'info');
        }

        try {
            $google = $this->google->identity((string) $request->query('code'), $oauth['verifier'], $oauth['nonce']);
        } catch (GoogleSignInRefused $e) {
            return $this->toLogin($e->getMessage());
        } catch (ConnectionException) {
            return $this->toLogin(self::CONNECTION_FAILED);
        }

        try {
            $user = User::whereRaw('LOWER(email) = ?', [$google['email']])->first();
        } catch (QueryException $e) {
            report($e);

            return $this->toLogin(self::CONNECTION_FAILED);
        }

        // Not registered yet. The verified Google identity is carried into registration,
        // which fixes the email to it; no Firebase account is made here.
        if (! $user && $this->adviserFlow) {
            session(['register.adviser_google' => $google + ['expires' => now()->addMinutes(self::REGISTER_MINUTES)->timestamp]]);

            return redirect()->route('register.adviser')->with('info',
                'Your SCC Google account has no ClubSync account yet. Add your adviser number and pick your club to create your adviser account.');
        }

        if (! $user) {
            if ($resumed = $this->resumeRegistration($google)) {
                return $resumed;
            }

            session(['register.google' => $google + ['expires' => now()->addMinutes(self::REGISTER_MINUTES)->timestamp]]);

            return redirect()->route('register')->with('info',
                'Your SCC Google account is not registered in ClubSync yet. Complete the form below to create your account.');
        }

        if ($user->is_suspended) {
            return $this->toLogin(self::SUSPENDED);
        }

        // The adviser pages open adviser accounts only.
        if ($this->adviserFlow && $user->role !== 'adviser') {
            return $this->toLogin('This is not an adviser account. Students, officers and staff sign in on the Student tab.');
        }

        try {
            $firebase = $this->auth->signInWithGoogle($google['id_token']);
        } catch (ConnectionException) {
            return $this->toLogin(self::CONNECTION_FAILED);
        } catch (\Throwable $e) {
            return $this->googleRefusal($e);
        }

        if (! empty($firebase['needConfirmation'])) {
            session(['google.link' => [
                'id_token' => $google['id_token'],
                'user_id'  => $user->id,
                'remember' => (bool) $oauth['remember'],
                'expires'  => now()->addMinutes(self::LINK_MINUTES)->timestamp,
            ]]);

            return redirect()->route('auth.google.link');
        }

        return $this->finish($user, $firebase, (bool) $oauth['remember'], 'auth.google.signed_in');
    }

    /**
     * A Google registration that stopped before its proof of enrollment, picked up where
     * it stopped (POLICY.md §Registration). Firebase is asked only when such a draft
     * exists — its Firebase account was made at step 1 — so a first visit still makes
     * no Firebase account here. Null: nothing to resume, register as usual.
     *
     * @param  array{email: string, id_token: string}  $google
     */
    private function resumeRegistration(array $google): ?RedirectResponse
    {
        $draft = RegistrationDraft::forEmail($google['email']);

        if (! $draft || ! ($draft->details['via_google'] ?? false)) {
            return null;
        }

        try {
            $account = $this->auth->signInWithGoogle($google['id_token']);
        } catch (\Throwable $e) {
            Log::warning('Could not resume a Google registration: ' . $e->getMessage());

            return null;
        }

        // Not the Firebase account the draft was saved under: start afresh rather than mix them.
        if (! empty($account['needConfirmation']) || ($account['localId'] ?? null) !== $draft->firebase_uid) {
            $draft->delete();

            return null;
        }

        request()->session()->regenerate();

        AuditLog::record('auth.registration.resumed', null, ['method' => 'google']);

        return redirect()->route($draft->restore($account))->with('info', self::RESUMED);
    }

    /** First Google sign-in to an account that has a password: confirm it once. */
    public function showLink(): RedirectResponse|View
    {
        if (! $user = $this->pendingLinkUser()) {
            return $this->toLogin(self::LINK_EXPIRED);
        }

        return view('auth.google-link', ['email' => $user->email]);
    }

    public function link(Request $request): RedirectResponse
    {
        if (! $user = $this->pendingLinkUser()) {
            return $this->toLogin(self::LINK_EXPIRED);
        }

        $request->validate(['password' => ['required']]);

        if ($user->is_suspended) {
            session()->forget('google.link');

            return $this->toLogin(self::SUSPENDED);
        }

        try {
            $account = $this->auth->loginWithFirebase($user->email, $request->password, remember: false);
        } catch (ConnectionException) {
            return back()->with('error', self::CONNECTION_FAILED);
        } catch (\Throwable $e) {
            return $this->firebaseRefusal($e);
        }

        $pending = session()->pull('google.link');

        try {
            $linked = $this->auth->signInWithGoogle($pending['id_token'], $account['idToken']);
        } catch (\Throwable $e) {
            // The password was right, so they are signed in with it; Google can be linked
            // the next time they use it.
            Log::warning('Could not link a Google account to ClubSync user ' . $user->id . ': ' . $e->getMessage());

            return $this->finish($user, $account, $pending['remember'])->with('warning',
                'You are signed in, but your Google account could not be connected this time. '
                . 'You can try again the next time you tap Continue with Google.');
        }

        return $this->finish($user, $linked, $pending['remember'], 'auth.google.linked');
    }

    /** The account waiting for its password to be confirmed, if the wait has not run out. */
    private function pendingLinkUser(): ?User
    {
        $pending = session('google.link');

        if (! is_array($pending) || $pending['expires'] < time()) {
            session()->forget('google.link');

            return null;
        }

        return User::find($pending['user_id']);
    }

    /**
     * Open the session — unless Firebase signed in a different account from the one this
     * ClubSync account belongs to. Google's word never re-points an account; that needs
     * an administrator.
     */
    private function finish(User $user, array $firebase, bool $remember, ?string $auditAction = null): RedirectResponse
    {
        if (empty($firebase['idToken']) || empty($firebase['localId'])) {
            Log::error('Firebase answered a Google sign-in without tokens.', ['user_id' => $user->id]);

            return $this->toLogin('Google sign-in is unavailable right now. Please sign in with your email and password.');
        }

        if ($user->firebase_uid && $user->firebase_uid !== $firebase['localId']) {
            Log::error('Google sign-in reached a different Firebase account than the ClubSync record.', [
                'user_id' => $user->id,
                'expected_uid' => $user->firebase_uid,
                'firebase_uid' => $firebase['localId'],
            ]);

            return $this->toLogin(
                'This Google account cannot sign in to ClubSync yet. Please sign in with your email and password, '
                . 'or contact the ClubSync administrator.'
            );
        }

        if ($remember && ! empty($firebase['refreshToken'])) {
            $this->auth->rememberRefreshToken($firebase['refreshToken']);
        }

        if ($auditAction) {
            AuditLog::record($auditAction, $user, ['provider' => 'google.com'], $user->id);
        }

        return $this->session->signIn($user, $firebase);
    }

    /** Why Firebase turned down a Google sign-in or link. */
    private function googleRefusal(\Throwable $e): RedirectResponse
    {
        $code = trim(strtok($e->getMessage(), ':'));

        if ($code === 'USER_DISABLED') {
            return $this->toLogin(self::SUSPENDED);
        }

        if ($code === 'TOO_MANY_ATTEMPTS_TRY_LATER') {
            return $this->toLogin('Too many sign-in attempts. Please wait a few minutes and try again.');
        }

        // INVALID_IDP_RESPONSE (Firebase does not accept this app's Google client),
        // OPERATION_NOT_ALLOWED (Google is not switched on in Firebase), a bad API key:
        // all setup faults, none of them the person's.
        Log::error('Firebase refused a Google sign-in.', ['code' => $code]);

        return $this->toLogin('Google sign-in is unavailable right now. Please sign in with your email and password.');
    }

    /**
     * Always the sign-in page by name, never back(): the page before the callback is
     * /auth/google itself, and going back there would start another Google sign-in.
     */
    private function toLogin(string $message, string $kind = 'error'): RedirectResponse
    {
        return redirect()->route($this->adviserFlow ? 'login.adviser' : 'login')->with($kind, $message);
    }
}

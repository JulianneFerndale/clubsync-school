<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\EmailAlreadyRegistered;
use App\Http\Controllers\Auth\Concerns\ClaimsFirebaseAccount;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Club;
use App\Models\ClubNotification;
use App\Models\User;
use App\Services\AuthManager;
use App\Services\SessionManager;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Adviser sign-up (POLICY.md §Registration). Advisers are employees, not students, so
 * they have their own short form: adviser number, full name, SCC email and password — or
 * their SCC Google account in place of the email and password. There is no department,
 * course or proof of enrollment, and the account opens at once.
 *
 * The adviser picks the club they advise from the clubs that have no adviser. That is a
 * request: an administrator confirms it on Manage Users before the adviser is linked to
 * the club, so nobody who signs up can take a club alone, and a club never has two.
 */
class AdviserRegisterController extends Controller
{
    use ClaimsFirebaseAccount;

    public function __construct(
        private AuthManager $auth,
        private SessionManager $session,
    ) {}

    public function show(): View
    {
        return view('auth.register-adviser', [
            'google' => $this->googleIdentity(),
            'clubs'  => $this->openClubs(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $google = $this->googleIdentity();
        $clubs  = $this->openClubs();

        // Workspace accounts always carry a name, but never assume it.
        $askName = ! $google || blank($google['first_name']) || blank($google['last_name']);

        $request->merge(array_filter([
            'employee_number' => $request->filled('employee_number') ? strtoupper(trim((string) $request->employee_number)) : null,
            'email'           => $request->filled('email') ? strtolower(trim((string) $request->email)) : null,
        ]));

        $validated = $request->validate(array_filter([
            'employee_number' => ['required', 'string', 'max:30', 'unique:users,employee_number'],
            'first_name'      => $askName ? ['required', 'string', 'max:100'] : null,
            'last_name'       => $askName ? ['required', 'string', 'max:100'] : null,
            'email'           => $google ? null : ['required', 'email', 'ends_with:@sccpag.edu.ph', 'unique:users,email'],
            'password'        => $google ? null : ['required', 'confirmed', Password::defaults()],
            // Required while any club is free; with none, they sign up and wait to be given one.
            'club_id'         => [Rule::requiredIf($clubs->isNotEmpty()), 'nullable', 'integer', Rule::in($clubs->modelKeys())],
            'terms'           => ['accepted'],
        ]), [
            'employee_number.required' => 'Enter your adviser number.',
            'employee_number.unique'   => 'An account with this adviser number already exists. Please sign in instead.',
            'email.ends_with'          => 'Only Saint Columban College institutional emails (@sccpag.edu.ph) are accepted.',
            'email.unique'             => 'An account with this email address already exists. Please sign in instead.',
            'club_id.required'         => 'Choose the club you advise.',
            'club_id.in'               => 'That club has an adviser already. Choose a club that has none.',
            'terms.accepted'           => 'You must accept the Terms and Conditions to continue.',
        ]);

        $email = $google['email'] ?? $validated['email'];

        if ($google && User::where('email', $email)->exists()) {
            session()->forget('register.adviser_google');

            return redirect()->route('login.adviser')->with('error',
                'An account with this email address already exists. Tap Continue with Google to sign in.');
        }

        $account = $google ? $this->googleAccount($google) : $this->passwordAccount($email, $validated['password']);

        if ($account instanceof RedirectResponse) {
            return $account;
        }

        $club = isset($validated['club_id']) ? $clubs->find($validated['club_id']) : null;

        try {
            $user = User::create([
                'first_name'        => $askName ? $validated['first_name'] : $google['first_name'],
                'last_name'         => $askName ? $validated['last_name'] : $google['last_name'],
                'employee_number'   => $validated['employee_number'],
                'email'             => $email,
                // None for a Google sign-up: they sign in with Google (User::signsInWithGoogleOnly).
                'password'          => $google ? null : $validated['password'],
                'firebase_uid'      => $account['localId'],
                'role'              => 'adviser',
                'requested_club_id' => $club?->id,
                // Set here, not left to the column defaults: the session reads them off this model.
                'is_admin'          => false,
                'is_suspended'      => false,
            ]);
        } catch (QueryException $e) {
            report($e);

            return back()->withInput($request->except('password', 'password_confirmation'))
                ->with('error', 'Your account could not be created right now. Please try again.');
        }

        session()->forget('register.adviser_google');

        AuditLog::record('auth.adviser.registered', $user, array_filter([
            'method'            => $google ? 'google' : 'password',
            'requested_club_id' => $club?->id,
        ]), $user->id);

        $this->tellAdministrators($user, $club);

        return $this->session->signIn($user, $account)->with('success', $club
            ? "Welcome to ClubSync! Your adviser account is ready. An administrator will confirm you as the adviser of {$club->name}; your club's pages open once they do."
            : 'Welcome to ClubSync! Your adviser account is ready. Every club has an adviser right now, so an administrator will give you your club.');
    }

    /** "Use email and password instead": drop the Google account chosen. */
    public function cancelGoogle(): RedirectResponse
    {
        session()->forget('register.adviser_google');

        return redirect()->route('register.adviser');
    }

    /** The Firebase account for an email-and-password sign-up, or where to send them back to. */
    private function passwordAccount(string $email, string $password): array|RedirectResponse
    {
        try {
            return $this->claimFirebaseAccount($email, $password);
        } catch (EmailAlreadyRegistered $e) {
            return back()->withInput(request()->except('password', 'password_confirmation'))->withErrors(['email' => $e->getMessage()]);
        } catch (\Throwable $e) {
            Log::error('Firebase adviser registration failed for ' . $email . ': ' . $e->getMessage());

            return back()->withInput(request()->except('password', 'password_confirmation'))->withErrors([
                'email' => 'We could not check this email address right now. Please try again.',
            ]);
        }
    }

    /**
     * The Firebase account for a Google sign-up, made by signing in with Google: no
     * password is set. An earlier unfinished sign-up with a password holds the address,
     * and Firebase releases it only to that password, so it is finished on that form.
     */
    private function googleAccount(array $google): array|RedirectResponse
    {
        if (empty($google['id_token'])) {
            session()->forget('register.adviser_google');

            return redirect()->route('register.adviser')->with('error', 'Your Google sign-in has expired. Please tap Continue with Google again.');
        }

        try {
            $account = $this->auth->signInWithGoogle($google['id_token']);
        } catch (\Throwable $e) {
            Log::error('Firebase adviser registration with Google failed for ' . $google['email'] . ': ' . $e->getMessage());

            return back()->withInput()->with('error', 'We could not set up your account with Google right now. Please try again in a few minutes.');
        }

        if (! empty($account['needConfirmation']) || empty($account['localId']) || empty($account['idToken'])) {
            session()->forget('register.adviser_google');

            return redirect()->route('register.adviser')->withErrors(['email' =>
                'This address already has an unfinished sign-up that uses a password. Please sign up below with the same email '
                . 'and password to finish it, or reset the password with Forgot Password on the sign-in page.']);
        }

        return $account;
    }

    /**
     * The SCC Google account this sign-up is using, if one was chosen in the last
     * GoogleController::REGISTER_MINUTES.
     *
     * @return array{email: string, first_name: ?string, last_name: ?string, id_token: string}|null
     */
    private function googleIdentity(): ?array
    {
        $google = session('register.adviser_google');

        if (is_array($google) && $google['expires'] >= time()) {
            return $google;
        }

        session()->forget('register.adviser_google');

        return null;
    }

    /** Clubs an adviser can ask for: active, and with no adviser yet. */
    private function openClubs(): Collection
    {
        return Club::where('is_active', true)->whereNull('adviser_id')->orderBy('name')->get(['id', 'name', 'acronym']);
    }

    /** The administrators confirm the club; they are told a new adviser is waiting. */
    private function tellAdministrators(User $adviser, ?Club $club): void
    {
        $recipients = User::where('is_admin', true)->where('is_suspended', false)->pluck('id');

        foreach ($recipients as $recipientId) {
            ClubNotification::create([
                'recipient_id' => $recipientId,
                'sender_type'  => 'system',
                'club_id'      => $club?->id,
                'title'        => $club ? 'Adviser to confirm' : 'New adviser account',
                'body'         => $club
                    ? "{$adviser->name} ({$adviser->employee_number}) signed up as an adviser and asks to advise {$club->name}. Confirm or decline it on Manage Users."
                    : "{$adviser->name} ({$adviser->employee_number}) signed up as an adviser. Every club has an adviser, so give them one on Manage Users when a club is free.",
                'action_url'   => route('admin.users.index', $club ? ['filter' => 'club-requests'] : ['search' => $adviser->email]),
            ]);
        }
    }
}

<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuthManager;
use App\Services\SessionManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LoginController extends Controller
{
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

        try {
            $firebaseData = $this->auth->loginWithFirebase(
                $request->email,
                $request->password,
                $request->boolean('remember'),
            );
        } catch (\Throwable $e) {
            return back()->withErrors([
                'email' => 'The email or password you entered is incorrect.',
            ])->onlyInput('email');
        }

        $user = User::whereRaw('LOWER(email) = ?', [strtolower($request->email)])->first();

        if (! $user) {
            return back()->withErrors([
                'email' => 'The email or password you entered is incorrect.',
            ])->onlyInput('email');
        }

        if ($user->is_suspended) {
            return back()->withErrors([
                'email' => 'Your account has been suspended. Please contact the Director of Student Affairs for assistance.',
            ])->onlyInput('email');
        }

        // Sync firebase_uid if this user pre-dates the Firebase migration
        if (! $user->firebase_uid) {
            $user->update(['firebase_uid' => $firebaseData['localId']]);
        }

        $request->session()->regenerate();

        $this->session->store(
            $firebaseData['idToken'],
            $firebaseData['localId'],
            $user->role,
            $user->id,
            $user->is_admin,
            $firebaseData['refreshToken'] ?? null,
        );

        // Answered here so RequireCourseSelection does not have to ask the database
        // on every request of the session.
        session(['course_selected' => (bool) $user->course_id]);

        return $this->session->redirectByRole($user->role);
    }

    public function logout(Request $request): RedirectResponse
    {
        $this->auth->revokeSession();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('welcome');
    }
}

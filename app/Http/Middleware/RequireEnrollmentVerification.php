<?php

namespace App\Http\Middleware;

use App\Models\EnrollmentVerification;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A student whose proof of enrollment is still with the DSA, or was turned down, waits
 * on the verification page and reaches nothing else (POLICY.md §Registration).
 *
 * Only `pending` and `rejected` are held. A null status means the account never had to
 * verify — staff, and students who registered before verification existed — and those
 * pass untouched, as do verified students.
 */
class RequireEnrollmentVerification
{
    /** Roles held by students. Everyone else has nothing to verify. */
    private const STUDENT_ROLES = ['member', 'president', 'treasurer', 'mmo'];

    /** Routes that must stay reachable, or the redirect would loop. */
    private const ALWAYS_ALLOWED = [
        'verification.show', 'verification.store', 'verification.course', 'verification.course.update', 'logout',
        'onboarding.course', 'onboarding.course.store',
    ];

    private const HELD = [EnrollmentVerification::STATUS_PENDING, EnrollmentVerification::STATUS_REJECTED];

    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array(session('firebase_user_role'), self::STUDENT_ROLES, true)) {
            return $next($request);
        }

        if ($request->routeIs(self::ALWAYS_ALLOWED)) {
            return $next($request);
        }

        // A WebSocket handshake, kept free of user queries (see RequireCourseSelection).
        if ($request->is('broadcasting/auth')) {
            return $next($request);
        }

        // Cached once cleared — a verified account never goes back to pending.
        if (session('enrollment_cleared') === true) {
            return $next($request);
        }

        $user = User::select('id', 'enrollment_status')->find(auth_user_id());

        if (! $user) {
            return $next($request);
        }

        if (! in_array($user->enrollment_status, self::HELD, true)) {
            session(['enrollment_cleared' => true]);

            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message'  => 'Your proof of enrollment has not been verified yet.',
                'redirect' => route('verification.show'),
            ], 409);
        }

        return redirect()->route('verification.show');
    }
}

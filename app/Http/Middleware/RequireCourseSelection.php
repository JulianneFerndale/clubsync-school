<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A signed-in student with no course on record is sent to pick one before they can
 * use anything else.
 *
 * The course is what puts a student in the queue for their academic club
 * (POLICY.md §Registration), so an account without one belongs to no club and never
 * will until somebody asks. Registration collects it, but an account can still
 * arrive here without one — a half-finished registration that was later resumed, a
 * record created outside the wizard, or a course that was cleared since.
 *
 * Staff are exempt: advisers, the DSA and administrators have no course and never
 * will, and blocking them would lock them out of the system entirely.
 */
class RequireCourseSelection
{
    /** Roles held by students. Everyone else has no course by nature. */
    private const STUDENT_ROLES = ['member', 'president', 'treasurer', 'mmo'];

    /** Routes that must stay reachable, or the redirect would loop. */
    private const ALWAYS_ALLOWED = ['onboarding.course', 'onboarding.course.store', 'logout'];

    public function handle(Request $request, Closure $next): Response
    {
        $role = session('firebase_user_role');

        if (! in_array($role, self::STUDENT_ROLES, true)) {
            return $next($request);
        }

        if ($request->routeIs(self::ALWAYS_ALLOWED)) {
            return $next($request);
        }

        // Channel authorisation is a WebSocket handshake, not somewhere a student
        // navigates, and it is deliberately kept free of user queries. Gating it
        // would buy nothing — every page they can actually open is already gated.
        if ($request->is('broadcasting/auth')) {
            return $next($request);
        }

        // Cached once they have a course, so this costs one query per session rather
        // than one per request.
        if (session('course_selected') === true) {
            return $next($request);
        }

        $user = User::select('id', 'course_id')->find(auth_user_id());

        if (! $user) {
            return $next($request);   // no account to ask: let the auth layers deal with it
        }

        if ($user->course_id) {
            session(['course_selected' => true]);

            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message'  => 'Select your course before continuing.',
                'redirect' => route('onboarding.course'),
            ], 409);
        }

        return redirect()->route('onboarding.course');
    }
}

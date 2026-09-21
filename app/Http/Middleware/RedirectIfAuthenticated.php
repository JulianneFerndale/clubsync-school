<?php

namespace App\Http\Middleware;

use App\Services\SessionManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    public function __construct(private SessionManager $session) {}

    public function handle(Request $request, Closure $next): Response
    {
        // If the user has an active Firebase session, send them to their dashboard
        if (session('firebase_id_token')) {
            $role = session('firebase_user_role', 'member');
            return $this->session->redirectByRole($role);
        }

        $response = $next($request);

        // Auth pages must never be stored in the browser cache so that
        // the back button after login/logout doesn't re-show them.
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}

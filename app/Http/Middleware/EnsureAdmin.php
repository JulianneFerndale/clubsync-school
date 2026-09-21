<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! session('firebase_user_id') || ! auth_is_admin()) {
            abort(403, 'Unauthorized.');
        }

        return $next($request);
    }
}

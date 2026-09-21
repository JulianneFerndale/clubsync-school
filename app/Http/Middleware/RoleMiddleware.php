<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $userRole = session('firebase_user_role');

        if (! $userRole) {
            return redirect()->route('login');
        }

        // 'officer' is an alias that covers president, treasurer, and mmo.
        $allowed = array_map(fn ($r) => $r === 'officer' ? ['president', 'treasurer', 'mmo'] : [$r], $roles);
        $allowed = array_merge(...$allowed);

        if (! in_array($userRole, $allowed, true)) {
            abort(403);
        }

        return $next($request);
    }
}

<?php

use App\Support\Connectivity;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // Register the broadcasting auth route under firebase.token so auth()->user()
    // is populated from the Firebase session when Echo authorizes private channels.
    // The channel rules in routes/channels.php compare user ids only, so the user is
    // taken from the session without a database query (':session').
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        attributes: ['middleware' => ['web', 'firebase.token:session']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Honour X-Forwarded-* from a reverse proxy/tunnel (e.g. Expose/Herd
        // Share, ngrok, a production load balancer) so the app correctly detects
        // HTTPS and the public host. Without this, asset()/url() emit http:// URLs
        // behind an HTTPS tunnel, which browsers block as mixed content.
        $middleware->trustProxies(at: '*', headers:
            Request::HEADER_X_FORWARDED_FOR |
            Request::HEADER_X_FORWARDED_HOST |
            Request::HEADER_X_FORWARDED_PORT |
            Request::HEADER_X_FORWARDED_PROTO
        );

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            // Appended to the whole web group on purpose: a student with no course
            // belongs to no academic club, and there is no page they should reach
            // before answering that. Guests and staff pass straight through.
            \App\Http\Middleware\RequireCourseSelection::class,
        ]);
        $middleware->alias([
            'firebase.token' => \App\Http\Middleware\FirebaseTokenMiddleware::class,
            'role'           => \App\Http\Middleware\RoleMiddleware::class,
            'guest.firebase' => \App\Http\Middleware\RedirectIfAuthenticated::class,
            'admin'          => \App\Http\Middleware\EnsureAdmin::class,
            'course.required' => \App\Http\Middleware\RequireCourseSelection::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // When the server cannot reach the Supabase database or Firebase (i.e. the
        // internet is down), turn the would-be fatal 500 into the offline page so
        // the site never loads broken data or leaks a stack trace.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! Connectivity::isConnectivityFailure($e)) {
                return null; // not a connectivity issue — handle normally
            }

            Log::warning('Connectivity failure — serving offline page: ' . $e->getMessage());

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'No internet connection. Please check your network and try again.',
                ], 503, ['Retry-After' => 10]);
            }

            return response()
                ->view('offline', [], 503)
                ->header('Retry-After', 10);
        });
    })->create();

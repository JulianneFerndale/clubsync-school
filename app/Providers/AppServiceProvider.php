<?php

namespace App\Providers;

use App\Database\ResilientPostgresConnector;
use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Fail fast when the internet is down. Without a connect timeout, libpq
        // will hang for the OS default (~20s+) trying to reach Supabase, making
        // every page appear frozen instead of promptly showing the offline page.
        // Our connector writes connect_timeout into the DSN (portable, unlike the
        // PGCONNECT_TIMEOUT env var which putenv cannot set reliably on Windows).
        $this->app->bind('db.connector.pgsql', ResilientPostgresConnector::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Apply the same fail-fast policy to every outbound HTTP call (Firebase
        // auth/token refresh, Gemini). Only the *connect* timeout is constrained
        // so an offline request raises a ConnectionException in seconds; the
        // overall response timeout is left to Laravel's default so legitimately
        // slow calls (e.g. Gemini generations) are not cut short.
        Http::globalOptions([
            'connect_timeout' => (int) env('HTTP_CONNECT_TIMEOUT', 5),
        ]);

        // POLICY.md §Authentication: at least 8 characters, with an uppercase letter, a
        // lowercase letter and a number. Registration and password change both use
        // Password::defaults(), so the two can never ask for different things.
        Password::defaults(fn () => Password::min(8)->mixedCase()->numbers());

        // `php artisan serve` gives the server it starts only an allowlist of environment
        // variables. On Windows the list leaves out TEMP and TMP, so PHP has nowhere to
        // put an upload: every file upload fails with "unable to create a temporary
        // file" before the app sees it (the proof-of-enrollment step, profile photos).
        if (PHP_OS_FAMILY === 'Windows' && $this->app->runningInConsole()) {
            ServeCommand::$passthroughVariables = array_values(array_unique(
                array_merge(ServeCommand::$passthroughVariables, ['TEMP', 'TMP'])
            ));
        }
    }
}

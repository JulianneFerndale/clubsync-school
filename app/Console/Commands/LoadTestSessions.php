<?php

namespace App\Console\Commands;

use App\Models\ClubMember;
use App\Models\ClubOfficer;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Cookie\CookieValuePrefix;

/**
 * Signs the load test's fake students in, and writes their session cookies to a CSV
 * for k6 to send.
 *
 * Signing in for real would mean one Firebase call per virtual user, from one IP,
 * which measures Firebase's abuse protection rather than ClubSync. So the session is
 * built exactly as SessionManager::store() builds it after a Firebase login, with an
 * ID token that simply does not expire during the test.
 *
 * Only for the rehearsal: it refuses unless LOADTEST=true, and only ever signs in
 * accounts on the reserved loadtest.invalid domain, which no real user can have.
 */
class LoadTestSessions extends Command
{
    protected $signature = 'loadtest:sessions
        {--out= : Where to write the CSV (default: storage/app/loadtest-sessions.csv)}
        {--hours=24 : How long the sessions\' ID tokens stay valid}';

    protected $description = 'Create signed-in sessions for the load test\'s fake students (rehearsal only)';

    public function handle(): int
    {
        if (! config('loadtest.enabled')) {
            $this->error('Refusing: this signs users in without a password and only runs with LOADTEST=true.');

            return self::FAILURE;
        }

        $users = User::where('email', 'like', '%@' . config('loadtest.email_domain'))
            ->orderBy('id')
            ->get(['id', 'role', 'firebase_uid', 'is_admin']);

        if ($users->isEmpty()) {
            $this->error('No fake students. Run: php artisan db:seed --class=LoadTestSeeder --force');

            return self::FAILURE;
        }

        // A president's own club; otherwise one of the clubs the student belongs to.
        $officerClub = ClubOfficer::whereIn('user_id', $users->pluck('id'))->pluck('club_id', 'user_id');
        $memberClub  = ClubMember::whereIn('user_id', $users->pluck('id'))->where('status', 'active')
            ->orderBy('id')->pluck('club_id', 'user_id');

        $cookie = config('session.cookie');
        $prefix = CookieValuePrefix::create($cookie, app('encrypter')->getKey());
        $token  = $this->idToken(now()->addHours((int) $this->option('hours'))->timestamp);

        $path = $this->option('out') ?: storage_path('app/loadtest-sessions.csv');
        $file = fopen($path, 'w');
        fputcsv($file, ['user_id', 'role', 'club_id', 'cookie_name', 'cookie_value']);

        $bar = $this->output->createProgressBar($users->count());

        foreach ($users as $user) {
            // A fresh store and handler per session: the database handler remembers
            // whether its last session existed and would update instead of insert.
            app('session')->forgetDrivers();
            $session = app('session')->driver();
            $session->setId(null);
            $session->start();
            $session->put([
                'firebase_id_token'  => $token,
                'firebase_uid'       => $user->firebase_uid,
                'firebase_user_role' => $user->role,
                'firebase_user_id'   => $user->id,
                'firebase_is_admin'  => (bool) $user->is_admin,
            ]);
            $session->save();

            fputcsv($file, [
                $user->id,
                $user->role,
                $officerClub[$user->id] ?? $memberClub[$user->id] ?? '',
                $cookie,
                encrypt($prefix . $session->getId(), false),
            ]);
            $bar->advance();
        }

        fclose($file);
        $bar->finish();
        $this->newLine();
        $this->info("Wrote {$users->count()} sessions to {$path}");

        return self::SUCCESS;
    }

    /** An unsigned token carrying only an expiry: all FirebaseTokenMiddleware reads. */
    private function idToken(int $expiresAt): string
    {
        $part = fn (array $data) => rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');

        return $part(['alg' => 'none', 'typ' => 'JWT']) . '.' . $part(['exp' => $expiresAt, 'loadtest' => true]) . '.';
    }
}

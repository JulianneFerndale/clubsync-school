<?php

namespace Database\Seeders;

use App\Models\Club;
use App\Models\User;
use App\Services\AuthManager;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

/**
 * Creates one adviser account per club (up to 42), each with a unique employee
 * number and a Firebase Authentication account.
 *
 * Passwords are DETERMINISTIC (derived from the email) so the seeder is fully
 * idempotent and self-healing: a re-run recovers the Firebase UID by signing in
 * for any account that already exists but has no local record. Generated
 * passwords are printed to the console so they can be handed out.
 */
class AdviserSeeder extends Seeder
{
    /** 42 distinct family names (Filipino / local), one adviser per club. */
    private array $lastNames = [
        'Amilio', 'Bangued', 'Basilio', 'Catanduanes', 'Colot', 'Dela Rosa',
        'Encanto', 'Foton', 'Godod', 'Hemil', 'Isabel', 'Jacki',
        'Kathmandu', 'Lisbon', 'Milanilla', 'Nagis', 'Ocampo', 'Pascual',
        'Quinto', 'Reyes', 'Santos', 'Tolentino', 'Ubaldo', 'Villanueva',
        'Wenceslao', 'Ybanez', 'Zamora', 'Bautista', 'Cabrera', 'Dizon',
        'Espino', 'Fajardo', 'Gutierrez', 'Hidalgo', 'Ignacio', 'Jimenez',
        'Lacson', 'Macaraeg', 'Navarro', 'Ortega', 'Padilla', 'Quirino',
    ];

    /** First names paired by index (cycled if fewer than clubs). */
    private array $firstNames = [
        'Andres', 'Bianca', 'Carlo', 'Divina', 'Elias', 'Faith',
        'Gabriel', 'Helena', 'Ismael', 'Jasmine', 'Kevin', 'Liza',
        'Mateo', 'Nadia', 'Oscar', 'Patricia', 'Quintin', 'Rowena',
        'Samuel', 'Teresa', 'Ulysses', 'Veronica', 'Wilfredo', 'Ximena',
        'Yolanda', 'Zacarias', 'Benigno', 'Corazon', 'Dante', 'Emilia',
        'Ferdinand', 'Grace', 'Hector', 'Imelda', 'Joel', 'Katrina',
        'Lorenzo', 'Maricel', 'Nestor', 'Olivia', 'Pablo', 'Rosario',
    ];

    public function run(): void
    {
        $auth  = app(AuthManager::class);
        $clubs = Club::orderBy('id')->take(count($this->lastNames))->get();

        if ($clubs->isEmpty()) {
            $this->command?->warn('No clubs found — run ClubSeeder first. No advisers created.');
            return;
        }

        $created = [];
        $manual  = [];

        foreach ($clubs as $i => $club) {
            $first    = $this->firstNames[$i % count($this->firstNames)];
            $last     = $this->lastNames[$i % count($this->lastNames)];
            $slug     = strtolower(str_replace(' ', '', $last));
            $email    = "{$slug}.adviser@sccpag.edu.ph";
            $empNo    = sprintf('SCC-ADV-%04d', $i + 1);
            $password = $this->passwordFor($email, $slug);

            $existing = User::where('email', $email)->first();
            if ($existing && $existing->firebase_uid) {
                $club->update(['adviser_id' => $existing->id]);
                $this->command?->line("• {$email} already provisioned — skipped.");
                continue;
            }

            // Get a Firebase UID: create the account, or recover it by signing in
            // if it already exists (e.g. a prior partial run).
            $uid = null;
            try {
                $uid = $auth->registerWithFirebase($email, $password)['localId'] ?? null;
            } catch (\Throwable $e) {
                if (str_contains($e->getMessage(), 'EMAIL_EXISTS')) {
                    $uid = $this->recoverUid($email, $password);
                    if (! $uid) {
                        $manual[] = $email;
                        $this->command?->warn("• {$email} exists in Firebase with a different password — reset it in the console, then re-run.");
                        continue;
                    }
                } else {
                    $this->command?->error("• {$email} Firebase error: {$e->getMessage()}");
                    continue;
                }
            }

            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'first_name'            => $first,
                    'last_name'             => $last,
                    'employee_number'       => $empNo,
                    'password'              => Hash::make($password),
                    'role'                  => 'adviser',
                    'firebase_uid'          => $uid,
                    'is_admin'              => false,
                    'is_suspended'          => false,
                    'last_password_changed' => now(),
                ]
            );

            $club->update(['adviser_id' => $user->id]);
            $created[] = [$club->name, "{$first} {$last}", $email, $empNo, $password];
        }

        $this->report($created, $manual);
    }

    /**
     * Deterministic, policy-compliant password (>=8 chars, upper, lower, number, special).
     * Derived from the email so re-runs produce the same value.
     */
    private function passwordFor(string $email, string $slug): string
    {
        $num     = (crc32($email) % 900) + 100;              // 100–999
        $special = ['!', '@', '#', '$', '%'][crc32($email) % 5];

        return ucfirst($slug) . $num . $special;
    }

    /** Recover a Firebase UID by signing in (for accounts created in a prior run). */
    private function recoverUid(string $email, string $password): ?string
    {
        $response = Http::post(config('firebase.sign_in_url') . '?key=' . config('firebase.api_key'), [
            'email'             => $email,
            'password'          => $password,
            'returnSecureToken' => true,
        ]);

        return $response->successful() ? $response->json('localId') : null;
    }

    private function report(array $created, array $manual): void
    {
        if (! empty($created)) {
            $this->command?->newLine();
            $this->command?->info(count($created) . ' adviser account(s) provisioned. CREDENTIALS (store securely):');
            $this->command?->table(['Club', 'Adviser', 'Email', 'Employee No.', 'Password'], $created);
        } else {
            $this->command?->info('No new adviser accounts were created (all already provisioned).');
        }

        if (! empty($manual)) {
            $this->command?->newLine();
            $this->command?->warn('Needs manual Firebase password reset then re-run: ' . implode(', ', $manual));
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\ClubOfficer;
use App\Models\Course;
use App\Models\User;
use App\Services\AuthManager;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

/**
 * Creates one SAMPLE club president per club (19 academic + 23 non-academic),
 * each with a Firebase Authentication account, a student profile, an officer
 * row (position = president) and an approved, active membership in that club.
 *
 * Mirrors AdviserSeeder: passwords are DETERMINISTIC (derived from the email)
 * so the seeder is idempotent and self-healing — a re-run recovers the Firebase
 * UID by signing in for any account that already exists but has no local record.
 * Generated credentials are printed to the console so they can be handed out.
 *
 * POLICY.md compliance: institutional @sccpag.edu.ph addresses only, and every
 * password is >= 8 characters with an uppercase letter, a number and a special
 * character.
 */
class PresidentSeeder extends Seeder
{
    /** 42 distinct family names — one president per club, none reused from AdviserSeeder. */
    private array $lastNames = [
        'Abad', 'Agustin', 'Alonzo', 'Arellano', 'Balingit', 'Baltazar',
        'Bermudez', 'Buenaventura', 'Calderon', 'Carpio', 'Castillo', 'Corpuz',
        'Cuenca', 'Dagohoy', 'Damaso', 'Delgado', 'Domingo', 'Enriquez',
        'Estrada', 'Galang', 'Garcia', 'Gonzales', 'Hernandez', 'Ilagan',
        'Javier', 'Laurel', 'Legaspi', 'Magsaysay', 'Mabini', 'Mendoza',
        'Montano', 'Nieves', 'Olivares', 'Panganiban', 'Ramos', 'Rivera',
        'Salazar', 'Sarmiento', 'Tanada', 'Urbano', 'Valdez', 'Yabut',
    ];

    /** First names paired by index. */
    private array $firstNames = [
        'Aldrin', 'Bea', 'Christian', 'Danica', 'Ericka', 'Fritz',
        'Gerald', 'Hazel', 'Ivan', 'Jomar', 'Kyla', 'Lester',
        'Micah', 'Nikko', 'Odessa', 'Paulo', 'Queenie', 'Rhea',
        'Sherwin', 'Trisha', 'Vincent', 'Wendy', 'Yuri', 'Zeny',
        'Arjay', 'Brenda', 'Cyrus', 'Dianne', 'Edgar', 'Fely',
        'Glen', 'Honeylet', 'Isko', 'Jenny', 'Kirby', 'Lorna',
        'Marvin', 'Nica', 'Orlando', 'Precious', 'Rico', 'Shaina',
    ];

    public function run(): void
    {
        $auth  = app(AuthManager::class);
        $clubs = Club::with('courses')->orderBy('id')->take(count($this->lastNames))->get();

        if ($clubs->isEmpty()) {
            $this->command?->warn('No clubs found — run ClubSeeder first. No presidents created.');
            return;
        }

        // Fallback pool for non-academic clubs, which map to no course of their
        // own. Presidents are still students, so each gets a real course (spread
        // across the four colleges) rather than an empty academic profile.
        $allCourses     = Course::orderBy('id')->get();
        $nonAcademicSeq = 0;

        $created = [];
        $manual  = [];

        foreach ($clubs as $i => $club) {
            $first    = $this->firstNames[$i % count($this->firstNames)];
            $last     = $this->lastNames[$i % count($this->lastNames)];
            $slug     = self::slugFor($last);
            $email    = "{$slug}.president@sccpag.edu.ph";
            $edpNo    = sprintf('4%05d', $i + 1);   // 400001–400042, a reserved sample block
            $password = self::passwordFor($email);
            $yearLvl  = 3 + ($i % 2);               // 3rd / 4th year students

            // Academic clubs: the club's own mapped course. Non-academic clubs:
            // step through the course list in strides of 7 (coprime with the 31
            // courses) so their presidents come from all four colleges rather
            // than clustering in the first one.
            $course = $club->courses->sortBy('id')->first()
                ?: $allCourses->get(($nonAcademicSeq++ * 7) % max($allCourses->count(), 1));

            $existing = User::where('email', $email)->first();
            if ($existing && $existing->firebase_uid) {
                $this->wireIntoClub($club, $existing);
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
                    'first_name'    => $first,
                    'last_name'     => $last,
                    'edp_number'    => $edpNo,
                    'password'      => Hash::make($password),
                    'role'          => 'president',
                    'firebase_uid'  => $uid,
                    'department_id' => $course?->department_id,
                    'course_id'     => $course?->id,
                    'year_level'    => $yearLvl,
                    'is_admin'      => false,
                    'is_suspended'  => false,
                    // Left null on purpose: the account has never had a password
                    // change, so POLICY.md's 7-day cooldown does not lock the
                    // holder out of replacing this seeded password immediately.
                    'last_password_changed' => null,
                ]
            );

            $this->wireIntoClub($club, $user);

            $created[] = [
                $club->name,
                $club->club_type,
                "{$first} {$last}",
                $email,
                $edpNo,
                $password,
            ];
        }

        $this->report($created, $manual);
    }

    /** Email-safe form of a family name (also the local part of the address). */
    public static function slugFor(string $lastName): string
    {
        return strtolower(preg_replace('/[^a-z]/i', '', $lastName));
    }

    /**
     * Deterministic, policy-compliant password (>=8 chars, upper, lower, number, special).
     * Derived from the email so re-runs produce the same value.
     */
    public static function passwordFor(string $email): string
    {
        $slug    = strstr($email, '.', true) ?: $email;      // the surname portion
        $num     = (crc32($email) % 900) + 100;              // 100–999
        $special = ['!', '@', '#', '$', '%'][crc32($email) % 5];

        return ucfirst($slug) . $num . $special;
    }

    /** Give the president an officer post and an approved, active membership. */
    private function wireIntoClub(Club $club, User $user): void
    {
        ClubOfficer::updateOrCreate(
            ['club_id' => $club->id, 'user_id' => $user->id, 'position' => 'president'],
            ['assigned_at' => now()],
        );

        ClubMember::updateOrCreate(
            ['club_id' => $club->id, 'user_id' => $user->id],
            [
                'role'                => 'President',
                'status'              => 'active',
                'registration_status' => 'approved',
                'date_joined'         => now(),
                'joined_at'           => now(),
                'approved_by'         => $club->adviser_id,
                'approved_at'         => now(),
            ],
        );
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
            $this->command?->info(count($created) . ' president account(s) provisioned. CREDENTIALS (store securely):');
            $this->command?->table(['Club', 'Type', 'President', 'Email', 'EDP No.', 'Password'], $created);
        } else {
            $this->command?->info('No new president accounts were created (all already provisioned).');
        }

        if (! empty($manual)) {
            $this->command?->newLine();
            $this->command?->warn('Needs manual Firebase password reset then re-run: ' . implode(', ', $manual));
        }
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\ClubOfficer;
use App\Models\Course;
use App\Models\Department;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class SeedClubPresident extends Command
{
    protected $signature = 'app:seed-club-president
        {--email=manchester@sccpag.edu.ph}
        {--password=clubmanchester}
        {--first-name=Manny Chester}
        {--last-name=Topaz}
        {--birthday=2005-06-10}
        {--edp-number= : Student / EDP number (placeholder used if omitted)}
        {--club-slug=club_mentors}
        {--course-name=Psychology}
        {--department-slug=dept_cteas}
        {--firebase-uid= : Skip Firebase registration and use this UID directly}';

    protected $description = 'Create a club president account (Firebase + MySQL) wired as an officer of a club';

    public function handle(): int
    {
        $email     = $this->option('email');
        $password  = $this->option('password');
        $firstName = $this->option('first-name');
        $lastName  = $this->option('last-name');
        $apiKey    = config('firebase.api_key');

        // ── 1. Resolve club / course / department from the database ──────────
        $department = Department::where('slug', $this->option('department-slug'))->first();
        if (! $department) {
            $this->error('Department not found for slug: ' . $this->option('department-slug'));
            return self::FAILURE;
        }

        $course = Course::where('department_id', $department->id)
            ->where('name', 'ilike', '%' . $this->option('course-name') . '%')
            ->first();
        if (! $course) {
            $this->error('Course matching "' . $this->option('course-name') . '" not found in ' . $department->name);
            return self::FAILURE;
        }

        $club = Club::where('slug', $this->option('club-slug'))->first();
        if (! $club) {
            $this->error('Club not found for slug: ' . $this->option('club-slug'));
            return self::FAILURE;
        }

        $this->info('Resolved from database:');
        $this->table(['Entity', 'ID', 'Name'], [
            ['Department', $department->id, $department->name],
            ['Course',     $course->id,     $course->name],
            ['Club',       $club->id,       $club->name],
        ]);

        // ── 2. Firebase: register, sign in, or use a provided UID ────────────
        $firebaseUid = $this->option('firebase-uid') ?: null;

        if ($firebaseUid) {
            $this->info('Using provided Firebase UID: ' . $firebaseUid);
        } else {
            $signUpUrl = config('firebase.sign_up_url') . '?key=' . $apiKey;
            $response  = Http::post($signUpUrl, [
                'email'             => $email,
                'password'          => $password,
                'returnSecureToken' => true,
            ]);

            if ($response->successful()) {
                $firebaseUid = $response->json('localId');
                $this->info('Firebase account created. UID: ' . $firebaseUid);
            } elseif ($response->json('error.message') === 'EMAIL_EXISTS') {
                $signInUrl    = config('firebase.sign_in_url') . '?key=' . $apiKey;
                $signInResult = Http::post($signInUrl, [
                    'email'             => $email,
                    'password'          => $password,
                    'returnSecureToken' => true,
                ]);

                if ($signInResult->successful()) {
                    $firebaseUid = $signInResult->json('localId');
                    $this->info('Firebase account already exists. UID: ' . $firebaseUid);
                } else {
                    $this->error('Firebase account exists but the password differs. '
                        . 'Re-run with --firebase-uid=THE_UID from the Firebase console.');
                    return self::FAILURE;
                }
            } else {
                $this->error('Firebase registration failed: ' . $response->json('error.message', 'UNKNOWN'));
                return self::FAILURE;
            }
        }

        // ── 3. MySQL: create / update the user as a president ────────────────
        $user = User::firstOrNew(['email' => $email]);

        $user->first_name    = $firstName;
        $user->last_name     = $lastName;
        $user->email         = $email;
        $user->edp_number    = $this->option('edp-number') ?: ($user->edp_number ?: '0000000000');
        $user->password      = $password;            // hashed via the model cast
        $user->role          = 'president';
        $user->department_id = $department->id;
        $user->course_id     = $course->id;
        $user->firebase_uid  = $firebaseUid;
        $user->is_admin      = false;
        $user->is_suspended  = false;

        // Birthday only persists if the schema has a column for it.
        $birthdayStored = false;
        foreach (['birthday', 'birthdate', 'date_of_birth'] as $col) {
            if (Schema::hasColumn('users', $col)) {
                $user->{$col} = Carbon::parse($this->option('birthday'))->toDateString();
                $birthdayStored = true;
                break;
            }
        }

        $user->save();

        // ── 4. Wire the president into the club (officer + active member) ────
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
                'approved_at'         => now(),
            ],
        );

        $this->newLine();
        $this->info('✓ Club president ready.');
        $this->table(['Field', 'Value'], [
            ['Email',        $email],
            ['Password',     $password],
            ['Name',         $firstName . ' ' . $lastName],
            ['Role',         'president'],
            ['Club',         $club->name . ' (' . $club->acronym . ')'],
            ['Position',     'president'],
            ['Course',       $course->name],
            ['Department',   $department->name],
            ['Birthday',     $birthdayStored ? $this->option('birthday') : '(no users column — not stored)'],
            ['EDP number',   $user->edp_number],
            ['MySQL ID',     $user->id],
            ['Firebase UID', $firebaseUid],
        ]);

        if (! $birthdayStored) {
            $this->newLine();
            $this->warn('Note: the users table has no birthday/birthdate column, so the birthday was NOT stored.');
        }

        return self::SUCCESS;
    }
}

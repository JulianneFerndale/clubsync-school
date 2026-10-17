<?php

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\User;
use Database\Seeders\LoadTestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/*
 * The load-test tools sign users in without a password and fill the database with
 * fake students. They must do nothing unless the rehearsal switch is on, and even
 * then must never touch a real account.
 */

function ltUser(string $email, string $role = 'member'): User
{
    static $n = 0;
    $n++;

    return User::create([
        'first_name'   => 'Load',
        'last_name'    => "Tester{$n}",
        'edp_number'   => 'LT' . str_pad((string) $n, 6, '0', STR_PAD_LEFT),
        'email'        => $email,
        'password'     => Hash::make('Str0ng!Pass1'),
        'firebase_uid' => "loadtest-{$n}",
        'role'         => $role,
        'course_id'    => testCourse()->id,
        'is_admin'     => false,
        'is_suspended' => false,
    ]);
}

function ltCsv(string $path): array
{
    $rows = array_map('str_getcsv', file($path, FILE_IGNORE_NEW_LINES));
    $head = array_shift($rows);

    return array_map(fn ($row) => array_combine($head, $row), $rows);
}

it('refuses to mint sessions unless the rehearsal switch is on', function () {
    config(['loadtest.enabled' => false]);
    ltUser('student0001@loadtest.invalid');

    $this->artisan('loadtest:sessions', ['--out' => storage_path('framework/testing/lt.csv')])
        ->expectsOutputToContain('Refusing')
        ->assertFailed();
});

it('refuses to seed fake students unless the rehearsal switch is on', function () {
    config(['loadtest.enabled' => false]);

    $this->seed(LoadTestSeeder::class);

    expect(User::where('email', 'like', '%@loadtest.invalid')->count())->toBe(0);
});

it('signs in only the fake students, never a real account', function () {
    config(['loadtest.enabled' => true, 'session.driver' => 'file']);

    $fake = ltUser('student0001@loadtest.invalid');
    $real = ltUser('real.student@sccpag.edu.ph');

    $path = storage_path('framework/testing/lt.csv');
    @mkdir(dirname($path), 0777, true);

    $this->artisan('loadtest:sessions', ['--out' => $path])->assertSuccessful();

    $ids = array_column(ltCsv($path), 'user_id');

    expect($ids)->toBe([(string) $fake->id])
        ->not->toContain((string) $real->id);
});

it('mints a session the app accepts as that student', function () {
    config(['loadtest.enabled' => true, 'session.driver' => 'file']);

    $club = Club::create([
        'slug' => 'lt-club', 'acronym' => 'LTC', 'club_type' => 'Non-Academic', 'type' => 'non_academic',
        'course_slug' => 'all', 'department_slug' => 'all', 'name' => 'Load Test Club', 'is_active' => true,
    ]);
    $student = ltUser('student0001@loadtest.invalid');
    ClubMember::create(['club_id' => $club->id, 'user_id' => $student->id, 'status' => 'active', 'role' => 'Member']);

    $path = storage_path('framework/testing/lt.csv');
    @mkdir(dirname($path), 0777, true);
    $this->artisan('loadtest:sessions', ['--out' => $path])->assertSuccessful();

    $row = ltCsv($path)[0];
    expect($row['club_id'])->toBe((string) $club->id);

    // Sent exactly as k6 sends it: the cookie as written, which the app decrypts itself.
    $response = $this->call('GET', '/member/dashboard', [], [$row['cookie_name'] => $row['cookie_value']]);

    // Signed in as that student: their own dashboard, listing their club.
    $response->assertOk()
        ->assertViewHas('nonAcademicClubs', fn ($clubs) => $clubs->pluck('id')->all() === [$club->id]);
});

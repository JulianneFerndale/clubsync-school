<?php

use App\Models\AuditLog;
use App\Models\Club;
use App\Models\User;
use App\Support\PublicUpload;
use Database\Seeders\ClubImageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
 * Club logos and banners. The pictures uploaded on the development site are bundled
 * with the seeders so a fresh database gets them, and every upload is stored by the
 * public disk's own URL so it still shows when that disk is a Laravel Cloud bucket.
 */

// ── Fixtures ─────────────────────────────────────────────────────────────────

const CI_DEAD_FIREBASE_LOGO = 'https://storage.googleapis.com/clubsyncing.firebasestorage.app/club_images/1772884313_scc_syborg_logo.jpg';

function ciUser(string $role = 'dsa'): User
{
    static $n = 0;
    $n++;

    return User::create([
        'first_name'   => 'Ci',
        'last_name'    => "User{$n}",
        'edp_number'   => str_pad((string) (8100 + $n), 10, '0', STR_PAD_LEFT),
        'email'        => "ci{$n}@sccpag.edu.ph",
        'password'     => Hash::make('Password1!'),
        'role'         => $role,
        'course_id'    => testCourse()->id,
        'is_admin'     => false,
        'is_suspended' => false,
    ]);
}

function ciClub(array $overrides = []): Club
{
    static $n = 0;
    $n++;

    return Club::create(array_merge([
        'slug'            => "ci-club-{$n}",
        'acronym'         => "CI{$n}",
        'club_type'       => 'Non-Academic',
        'type'            => 'non_academic',
        'course_slug'     => 'all',
        'department_slug' => 'all',
        'name'            => "Image Test Club {$n}",
        'is_active'       => true,
    ], $overrides));
}

function ciSession(User $user): array
{
    $header  = rtrim(strtr(base64_encode(json_encode(['alg' => 'none'])), '+/', '-_'), '=');
    $payload = rtrim(strtr(base64_encode(json_encode(['exp' => time() + 3600])), '+/', '-_'), '=');

    return [
        'firebase_id_token'  => "{$header}.{$payload}.signature",
        'firebase_uid'       => 'uid-' . $user->id,
        'firebase_user_role' => $user->role,
        'firebase_user_id'   => $user->id,
        'firebase_is_admin'  => $user->is_admin,
    ];
}

/** The public disk as a Laravel Cloud bucket: same disk name, its own address. */
function ciBucket(): void
{
    config(['filesystems.disks.public.url' => 'https://bucket.example']);
    Storage::fake('public', ['url' => 'https://bucket.example']);
}

// ── Bundled pictures ─────────────────────────────────────────────────────────

test('the seeder gives clubs their bundled logo and banner, replacing a dead Firebase link', function () {
    Storage::fake('public');
    $psyms = ciClub(['slug' => 'club_psyms']);
    $chess = ciClub(['slug' => 'club_chess', 'profile_photo_url' => CI_DEAD_FIREBASE_LOGO]);

    $this->seed(ClubImageSeeder::class);

    expect($psyms->fresh())
        ->profile_photo_url->toBe('/storage/clubs/logos/club_psyms.jpg')
        ->banner_image->toBe('/storage/clubs/banners/club_psyms.jpg')
        ->and($chess->fresh()->profile_photo_url)->toBe('/storage/clubs/logos/club_chess.jpg')
        ->and(AuditLog::where('action', 'club.images.seeded')->sole()->metadata)->toBe(['logos' => 2, 'banners' => 1]);

    Storage::disk('public')->assertExists(['clubs/logos/club_psyms.jpg', 'clubs/banners/club_psyms.jpg', 'clubs/logos/club_chess.jpg']);
});

test('re-running the seeder never replaces a logo the DSA has uploaded since', function () {
    Storage::fake('public');
    Storage::disk('public')->put('clubs/logos/new-logo.png', 'the DSA\'s upload');
    $chess = ciClub(['slug' => 'club_chess', 'profile_photo_url' => '/storage/clubs/logos/new-logo.png']);

    $this->seed(ClubImageSeeder::class);

    expect($chess->fresh()->profile_photo_url)->toBe('/storage/clubs/logos/new-logo.png')
        ->and(AuditLog::where('action', 'club.images.seeded')->exists())->toBeFalse();
});

test('the seeder puts a logo back when its file is gone from the disk', function () {
    Storage::fake('public');
    $chess = ciClub(['slug' => 'club_chess', 'profile_photo_url' => '/storage/clubs/logos/wiped-by-a-deploy.jpg']);

    $this->seed(ClubImageSeeder::class);

    expect($chess->fresh()->profile_photo_url)->toBe('/storage/clubs/logos/club_chess.jpg');
});

test('on a bucket the seeded logo is stored by the bucket\'s address', function () {
    ciBucket();
    $chess = ciClub(['slug' => 'club_chess']);

    $this->seed(ClubImageSeeder::class);

    expect($chess->fresh()->profile_photo_url)->toBe('https://bucket.example/clubs/logos/club_chess.jpg');
    Storage::disk('public')->assertExists('clubs/logos/club_chess.jpg');
});

test('every bundled picture is an image the DSA form would have accepted', function () {
    $limits = ['logos' => 2048, 'banners' => 4096]; // KB, as in DSA\ClubController
    $count  = 0;

    foreach ($limits as $kind => $maxKb) {
        foreach (glob(database_path("seeders/club-images/{$kind}/*.*")) as $file) {
            expect(getimagesize($file))->not->toBeFalse(basename($file) . ' is not an image')
                ->and(filesize($file))->toBeLessThanOrEqual($maxKb * 1024)
                ->and(pathinfo($file, PATHINFO_FILENAME))->toStartWith('club_');
            $count++;
        }
    }

    expect($count)->toBe(46);
});

// ── Uploads on a bucket ──────────────────────────────────────────────────────

test('a logo and banner the DSA uploads to a bucket are stored by the bucket\'s address', function () {
    ciBucket();
    $club = ciClub();

    $this->withSession(ciSession(ciUser()))->patch(route('dsa.clubs.update', $club), [
        'name' => $club->name, 'acronym' => $club->acronym, 'type' => 'non_academic', 'is_active' => 1,
        'logo'   => UploadedFile::fake()->image('logo.png', 200, 200),
        'banner' => UploadedFile::fake()->image('banner.jpg', 1200, 400),
    ])->assertSessionHasNoErrors();

    $club->refresh();

    expect($club->profile_photo_url)->toStartWith('https://bucket.example/clubs/logos/')
        ->and($club->banner_image)->toStartWith('https://bucket.example/clubs/banners/');
    Storage::disk('public')->assertExists([PublicUpload::path($club->profile_photo_url), PublicUpload::path($club->banner_image)]);
});

test('replacing a logo on a bucket deletes the old file', function () {
    ciBucket();
    Storage::disk('public')->put('clubs/logos/old.png', 'old logo');
    $club = ciClub(['profile_photo_url' => 'https://bucket.example/clubs/logos/old.png']);

    $this->withSession(ciSession(ciUser()))->patch(route('dsa.clubs.update', $club), [
        'name' => $club->name, 'acronym' => $club->acronym, 'type' => 'non_academic', 'is_active' => 1,
        'logo' => UploadedFile::fake()->image('new.png', 200, 200),
    ])->assertSessionHasNoErrors();

    Storage::disk('public')->assertMissing('clubs/logos/old.png');
    expect($club->fresh()->profile_photo_url)->not->toBe('https://bucket.example/clubs/logos/old.png');
});

test('a new club\'s logo is stored by the disk\'s URL, a path on the local disk', function () {
    Storage::fake('public');

    $this->withSession(ciSession(ciUser()))->post(route('dsa.clubs.store'), [
        'name' => 'Photography Guild', 'acronym' => 'PG', 'type' => 'non_academic',
        'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
    ])->assertSessionHasNoErrors();

    expect(Club::where('name', 'Photography Guild')->sole()->profile_photo_url)->toStartWith('/storage/clubs/logos/');
});

test('a profile photo uploaded to a bucket replaces the old one there', function () {
    ciBucket();
    Storage::disk('public')->put('profile-photos/old.jpg', 'old photo');
    $user = ciUser('member');
    $user->update(['profile_photo_url' => 'https://bucket.example/profile-photos/old.jpg']);

    $this->withSession(ciSession($user))->post(route('profile.photo'), [
        'photo' => UploadedFile::fake()->image('me.jpg', 300, 300),
    ])->assertSessionHasNoErrors();

    Storage::disk('public')->assertMissing('profile-photos/old.jpg');
    expect($user->fresh()->profile_photo_url)->toStartWith('https://bucket.example/profile-photos/');
});

test('only our own uploads map to a path on the public disk', function (?string $url, ?string $path) {
    ciBucket();

    expect(PublicUpload::path($url))->toBe($path);
})->with([
    'local upload'       => ['/storage/clubs/logos/a.jpg', 'clubs/logos/a.jpg'],
    'bucket upload'      => ['https://bucket.example/clubs/logos/a.jpg', 'clubs/logos/a.jpg'],
    'old Firebase link'  => [CI_DEAD_FIREBASE_LOGO, null],
    'image under public' => ['/images/scc-crest.png', null],
    'another host'       => ['https://bucket.example.evil/clubs/logos/a.jpg', null],
    'nothing'            => [null, null],
]);

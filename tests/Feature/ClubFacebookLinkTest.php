<?php

use App\Models\AuditLog;
use App\Models\Club;
use App\Models\ClubMember;
use App\Models\ClubOfficer;
use App\Models\User;
use App\Rules\FacebookPageUrl;
use Database\Seeders\ClubFacebookLinkSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

/*
 * Club Facebook button: each club's official page, reachable from the club page by
 * every user. The link is validated so it can only ever point at Facebook.
 */

// ── Fixtures ─────────────────────────────────────────────────────────────────

function fbUser(string $role = 'member'): User
{
    static $n = 0;
    $n++;

    return User::create([
        'first_name'   => 'Fb',
        'last_name'    => "User{$n}",
        'edp_number'   => str_pad((string) (7000 + $n), 10, '0', STR_PAD_LEFT),
        'email'        => "fb{$n}@sccpag.edu.ph",
        'password'     => Hash::make('Password1!'),
        'role'         => $role,
        'course_id'    => testCourse()->id,
        'is_admin'     => false,
        'is_suspended' => false,
    ]);
}

function fbClub(array $overrides = []): Club
{
    static $n = 0;
    $n++;

    return Club::create(array_merge([
        'slug'            => "fb-club-{$n}",
        'acronym'         => "FB{$n}",
        'club_type'       => 'Non-Academic',
        'type'            => 'non_academic',
        'course_slug'     => 'all',
        'department_slug' => 'all',
        'name'            => "Facebook Test Club {$n}",
        'is_active'       => true,
    ], $overrides));
}

function fbSession(User $user): array
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

const FB_CHESS = 'https://www.facebook.com/SCCchessclub';

function fbAssertButton($response, string $url): void
{
    $response->assertOk()
        ->assertSee('href="' . e($url) . '"', false)
        ->assertSee('target="_blank" rel="noopener noreferrer"', false)
        ->assertSee('bg-[#1877F2]', false);
}

// ── Seeded links ─────────────────────────────────────────────────────────────

test('the seeder attaches each link to its club by slug and leaves clubs without a page blank', function () {
    $chess      = fbClub(['slug' => 'club_chess']);
    $ewe = fbClub(['slug' => 'club_ewe']);
    $kickboxing = fbClub(['slug' => 'club_kickboxing']);

    $this->seed(ClubFacebookLinkSeeder::class);

    expect($chess->fresh()->facebook_url)->toBe(FB_CHESS)
        ->and($ewe->fresh()->facebook_url)->toBe('https://www.facebook.com/profile.php?id=61559777806801')
        ->and($kickboxing->fresh()->facebook_url)->toBeNull()
        ->and(AuditLog::where('action', 'club.facebook_links.seeded')->sole()->metadata)->toBe(['clubs_updated' => 2]);
});

test('re-running the seeder never overwrites a link the DSA has corrected', function () {
    $chess = fbClub(['slug' => 'club_chess', 'facebook_url' => 'https://www.facebook.com/corrected.page']);

    $this->seed(ClubFacebookLinkSeeder::class);

    expect($chess->fresh()->facebook_url)->toBe('https://www.facebook.com/corrected.page');
});

test('the seeder holds the 37 supplied links, each a valid Facebook page', function () {
    expect(ClubFacebookLinkSeeder::LINKS)->toHaveCount(37)
        ->and(array_keys(ClubFacebookLinkSeeder::LINKS))->not->toContain('club_kickboxing', 'club_taekwondo');

    foreach (ClubFacebookLinkSeeder::LINKS as $slug => $url) {
        expect(Validator::make(['url' => $url], ['url' => [new FacebookPageUrl]])->passes())
            ->toBeTrue("{$slug} has an invalid link: {$url}");
    }
});

// ── Validation ───────────────────────────────────────────────────────────────

test('only real Facebook page links are accepted', function (string $url, bool $valid) {
    expect(Validator::make(['url' => $url], ['url' => [new FacebookPageUrl]])->passes())->toBe($valid);
})->with([
    'vanity page'              => ['https://www.facebook.com/SCCchessclub', true],
    'profile id'               => ['https://www.facebook.com/profile.php?id=61559777806801', true],
    'bare domain'              => ['https://facebook.com/sccelecom', true],
    'mobile'                   => ['https://m.facebook.com/sccelecom', true],
    'plain http'               => ['http://www.facebook.com/sccelecom', false],
    'no page, just the site'   => ['https://www.facebook.com/', false],
    'look-alike subdomain'     => ['https://www.facebook.com.evil.example/page', false],
    'credentials trick'        => ['https://www.facebook.com@evil.example/page', false],
    'another site'             => ['https://evil.example/www.facebook.com/page', false],
    'javascript scheme'        => ['javascript:alert(document.cookie)', false],
    'no scheme'                => ['www.facebook.com/sccelecom', false],
]);

// ── The button, for every kind of viewer ─────────────────────────────────────

test('a student who is not a member still sees the Facebook button on the club page', function () {
    $club = fbClub(['facebook_url' => FB_CHESS]);

    fbAssertButton($this->withSession(fbSession(fbUser()))->get(route('member.clubs.show', $club)), FB_CHESS);
});

test('a pending applicant sees the Facebook button', function () {
    $club = fbClub(['facebook_url' => FB_CHESS]);
    $student = fbUser();
    ClubMember::create(['club_id' => $club->id, 'user_id' => $student->id, 'status' => 'pending']);

    fbAssertButton($this->withSession(fbSession($student))->get(route('member.clubs.show', $club)), FB_CHESS);
});

test('an officer sees the Facebook button on their club page', function () {
    $club = fbClub(['facebook_url' => FB_CHESS]);
    $officer = fbUser('president');
    ClubOfficer::create(['club_id' => $club->id, 'user_id' => $officer->id, 'position' => 'president']);

    fbAssertButton($this->withSession(fbSession($officer))->get(route('officer.clubs.show', $club)), FB_CHESS);
    fbAssertButton($this->withSession(fbSession($officer))->get(route('member.clubs.show', $club)), FB_CHESS);
});

test('the adviser sees the Facebook button on their club dashboard', function () {
    $adviser = fbUser('adviser');
    fbClub(['facebook_url' => FB_CHESS, 'adviser_id' => $adviser->id]);

    fbAssertButton($this->withSession(fbSession($adviser))->get(route('adviser.dashboard')), FB_CHESS);
});

test('the DSA sees the Facebook button on the club profile', function () {
    $club = fbClub(['facebook_url' => FB_CHESS]);

    fbAssertButton($this->withSession(fbSession(fbUser('dsa')))->get(route('dsa.clubs.show', $club)), FB_CHESS);
});

test('a club with no Facebook page shows no button', function () {
    $club = fbClub(['facebook_url' => null]);

    $this->withSession(fbSession(fbUser()))->get(route('member.clubs.show', $club))
        ->assertOk()
        ->assertDontSee('bg-[#1877F2]', false)
        ->assertDontSee('on Facebook');
});

// ── DSA maintains the link ───────────────────────────────────────────────────

test('the DSA can set, change and clear a club\'s Facebook link, and each change is audited', function () {
    $club = fbClub();
    $dsa = fbUser('dsa');
    $base = ['name' => $club->name, 'acronym' => $club->acronym, 'type' => 'non_academic', 'is_active' => 1];

    $this->withSession(fbSession($dsa))->get(route('dsa.clubs.edit', $club))->assertOk()->assertSee('name="facebook_url"', false);

    $this->withSession(fbSession($dsa))->patch(route('dsa.clubs.update', $club), $base + ['facebook_url' => FB_CHESS])
        ->assertSessionHasNoErrors();
    expect($club->fresh()->facebook_url)->toBe(FB_CHESS);

    $this->withSession(fbSession($dsa))->patch(route('dsa.clubs.update', $club), $base + ['facebook_url' => ''])
        ->assertSessionHasNoErrors();
    expect($club->fresh()->facebook_url)->toBeNull();

    expect(AuditLog::where('action', 'club.updated')->where('resource_id', $club->id)->pluck('metadata')->all())
        ->toBe([['changed' => ['facebook_url']], ['changed' => ['facebook_url']]]);
});

test('an update that does not send the field keeps the existing link', function () {
    $club = fbClub(['facebook_url' => FB_CHESS]);

    $this->withSession(fbSession(fbUser('dsa')))->patch(route('dsa.clubs.update', $club), [
        'name' => 'Renamed Club', 'acronym' => $club->acronym, 'type' => 'non_academic', 'is_active' => 1,
    ])->assertSessionHasNoErrors();

    expect($club->fresh())->name->toBe('Renamed Club')->facebook_url->toBe(FB_CHESS);
});

test('the DSA form rejects a link that is not a Facebook page', function () {
    $club = fbClub(['facebook_url' => FB_CHESS]);

    $this->withSession(fbSession(fbUser('dsa')))->patch(route('dsa.clubs.update', $club), [
        'name' => $club->name, 'acronym' => $club->acronym, 'type' => 'non_academic',
        'facebook_url' => 'https://www.facebook.com.evil.example/chess',
    ])->assertSessionHasErrors('facebook_url');

    expect($club->fresh()->facebook_url)->toBe(FB_CHESS);
});

test('the DSA can give a new club its Facebook link', function () {
    $this->withSession(fbSession(fbUser('dsa')))->post(route('dsa.clubs.store'), [
        'name' => 'Photography Guild', 'acronym' => 'PG', 'type' => 'non_academic',
        'facebook_url' => 'https://www.facebook.com/sccphotoguild',
    ])->assertSessionHasNoErrors();

    expect(Club::where('name', 'Photography Guild')->sole()->facebook_url)->toBe('https://www.facebook.com/sccphotoguild')
        ->and(AuditLog::where('action', 'club.created')->exists())->toBeTrue();
});

test('members and officers cannot change the link', function (string $role) {
    $club = fbClub(['facebook_url' => FB_CHESS]);

    $this->withSession(fbSession(fbUser($role)))->patch(route('dsa.clubs.update', $club), [
        'name' => $club->name, 'acronym' => $club->acronym, 'type' => 'non_academic',
        'facebook_url' => 'https://www.facebook.com/hijacked',
    ])->assertForbidden();

    expect($club->fresh()->facebook_url)->toBe(FB_CHESS);
})->with(['member', 'president', 'adviser']);

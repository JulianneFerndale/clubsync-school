<?php

use App\Models\AuditLog;
use App\Models\Club;
use App\Models\ClubMember;
use App\Models\ClubNotification;
use App\Models\ClubOfficer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/*
 * The admin "Manage Users" screen lets a DSA/Admin change another user's role
 * and requires that change to be written to the tamper-evident audit log
 * (POLICY.md §Data Privacy). See TASK/tests-logs.md Test 5, which found this
 * feature did not exist prior to this test.
 */

function makeUser(string $role, bool $isAdmin = false, ?string $email = null): User
{
    static $n = 0;
    $n++;

    return User::create([
        'first_name'   => 'Test',
        'last_name'    => "User{$n}",
        'edp_number'   => str_pad((string) $n, 10, '0', STR_PAD_LEFT),
        'email'        => $email ?? "user{$n}@sccpag.edu.ph",
        'password'     => Hash::make('Password1!'),
        'role'         => $role,
        'course_id'    => testCourse()->id,
        'is_admin'     => $isAdmin,
        'is_suspended' => false,
    ]);
}

/** A JWT-shaped (but unsigned) token whose payload decodes locally as unexpired. */
function fakeIdToken(): string
{
    $header  = rtrim(strtr(base64_encode(json_encode(['alg' => 'none'])), '+/', '-_'), '=');
    $payload = rtrim(strtr(base64_encode(json_encode(['exp' => time() + 3600])), '+/', '-_'), '=');

    return "{$header}.{$payload}.signature";
}

function adminSession(User $admin): array
{
    return [
        'firebase_id_token'  => fakeIdToken(),
        'firebase_uid'       => 'admin-uid',
        'firebase_user_role' => $admin->role,
        'firebase_user_id'   => $admin->id,
        'firebase_is_admin'  => $admin->is_admin,
    ];
}

it('lets an admin change another user\'s role and records it in the audit log', function () {
    $admin  = makeUser('dsa', isAdmin: true);
    $target = makeUser('member');

    $club = arClub('PSITS');
    arJoin($target, $club);

    $response = $this->withSession(adminSession($admin))
        ->from(route('admin.users.index'))
        ->patch(route('admin.users.role.update', $target), ['role' => 'president', 'club_id' => $club->id]);

    $response->assertRedirect(route('admin.users.index'));
    $response->assertSessionHas('success');

    $target->refresh();
    expect($target->role)->toBe('president');

    $entry = AuditLog::where('action', 'admin.user.role_changed')
        ->where('resource_id', $target->id)
        ->latest('id')
        ->first();

    expect($entry)->not->toBeNull()
        ->and($entry->actor_id)->toBe($admin->id)
        ->and($entry->resource_type)->toBe(User::class)
        ->and($entry->metadata['old_role'])->toBe('member')
        ->and($entry->metadata['new_role'])->toBe('president')
        ->and($entry->hash)->not->toBeNull();
});

it('rejects a role outside the allowed enum and writes no audit entry', function () {
    $admin  = makeUser('dsa', isAdmin: true);
    $target = makeUser('member');

    $response = $this->withSession(adminSession($admin))
        ->from(route('admin.users.index'))
        ->patch(route('admin.users.role.update', $target), ['role' => 'superadmin']);

    $response->assertSessionHasErrors('role');

    $target->refresh();
    expect($target->role)->toBe('member');
    expect(AuditLog::where('action', 'admin.user.role_changed')->count())->toBe(0);
});

it('blocks an admin from changing their own role', function () {
    $admin = makeUser('dsa', isAdmin: true);

    $response = $this->withSession(adminSession($admin))
        ->from(route('admin.users.index'))
        ->patch(route('admin.users.role.update', $admin), ['role' => 'member']);

    $response->assertRedirect(route('admin.users.index'));
    $response->assertSessionHas('error');

    $admin->refresh();
    expect($admin->role)->toBe('dsa');
    expect(AuditLog::where('action', 'admin.user.role_changed')->count())->toBe(0);
});

it('blocks a non-admin session from reaching the role-change route', function () {
    $nonAdmin = makeUser('dsa', isAdmin: false);
    $target   = makeUser('member');

    $response = $this->withSession(adminSession($nonAdmin))
        ->patch(route('admin.users.role.update', $target), ['role' => 'president']);

    $response->assertForbidden();

    $target->refresh();
    expect($target->role)->toBe('member');
});

// ── Officer positions are held in a named club ──────────────────────────────

function arClub(string $acronym, ?User $adviser = null): Club
{
    return Club::create([
        'slug'            => strtolower($acronym) . '-club',
        'acronym'         => $acronym,
        'club_type'       => 'Non-Academic',
        'type'            => 'non_academic',
        'course_slug'     => 'all',
        'department_slug' => 'all',
        'name'            => $acronym . ' Society',
        'is_active'       => true,
        'adviser_id'      => $adviser?->id,
    ]);
}

function arJoin(User $user, Club $club, string $status = 'active'): void
{
    ClubMember::create(['club_id' => $club->id, 'user_id' => $user->id, 'status' => $status, 'role' => 'Member']);
}

/** An employee's account: an adviser, with an adviser number and no EDP number or course. */
function arAdviser(?string $email = null): User
{
    static $n = 0;
    $n++;

    return User::create([
        'first_name'      => 'Adviser',
        'last_name'       => "Number{$n}",
        'employee_number' => 'SCC-ADV-9' . str_pad((string) $n, 3, '0', STR_PAD_LEFT),
        'email'           => $email ?? "adviser{$n}@sccpag.edu.ph",
        'password'        => Hash::make('Password1!'),
        'role'            => 'adviser',
        'is_admin'        => false,
        'is_suspended'    => false,
    ]);
}

function arChangeRole(User $admin, User $target, array $data)
{
    return test()->withSession(adminSession($admin))
        ->from(route('admin.users.index'))
        ->patch(route('admin.users.role.update', $target), $data);
}

it('offers every officer position, with the clubs the student belongs to', function () {
    $admin  = makeUser('dsa', isAdmin: true);
    $target = makeUser('member');
    arJoin($target, $psits = arClub('PSITS'));
    arJoin($target, $byte = arClub('BYTE'));
    arJoin($target, arClub('DEBATE'), 'pending');

    $this->withSession(adminSession($admin))
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertSee('Vice President')
        ->assertSee('Secretary')
        ->assertSee('Auditor')
        ->assertSee('Media Mass Officer (Internal)')
        ->assertSee('Media Mass Officer (External)')
        ->assertSee('In which club?')
        ->assertSee($psits->name)
        ->assertSee($byte->name)
        // A pending application is not a club they can hold a post in.
        ->assertDontSee('DEBATE Society');
});

it('makes a student an officer of the club the administrator names', function () {
    $admin  = makeUser('dsa', isAdmin: true);
    $target = makeUser('member');
    arJoin($target, $psits = arClub('PSITS'));
    arJoin($target, $byte = arClub('BYTE'));

    arChangeRole($admin, $target, ['role' => 'secretary', 'club_id' => $byte->id])
        ->assertSessionHas('success', fn ($m) => str_contains($m, 'is now Secretary of BYTE Society'));

    expect($target->fresh()->role)->toBe('secretary')
        ->and(ClubOfficer::where('user_id', $target->id)->get(['club_id', 'position'])->toArray())
            ->toBe([['club_id' => $byte->id, 'position' => 'secretary']])
        ->and(ClubMember::where('user_id', $target->id)->where('club_id', $byte->id)->value('role'))->toBe('Secretary')
        ->and(ClubMember::where('user_id', $target->id)->where('club_id', $psits->id)->value('role'))->toBe('Member');

    expect(AuditLog::where('action', 'admin.user.role_changed')->latest('id')->first()->metadata['club_id'])->toBe($byte->id);
});

it('needs the club for an officer position', function () {
    $admin  = makeUser('dsa', isAdmin: true);
    $target = makeUser('member');
    arJoin($target, arClub('PSITS'));

    arChangeRole($admin, $target, ['role' => 'auditor'])->assertSessionHasErrors('club_id');

    expect($target->fresh()->role)->toBe('member')
        ->and(ClubOfficer::count())->toBe(0);
});

it('refuses a position in a club the student is not an active member of', function () {
    $admin  = makeUser('dsa', isAdmin: true);
    $target = makeUser('member');
    arJoin($target, $pending = arClub('DEBATE'), 'pending');
    $other = arClub('BYTE');

    foreach ([$pending, $other] as $club) {
        arChangeRole($admin, $target, ['role' => 'president', 'club_id' => $club->id])->assertSessionHas('error');
    }

    expect($target->fresh()->role)->toBe('member')
        ->and(ClubOfficer::count())->toBe(0);
});

it('hands a post over: whoever held it becomes a member of the club', function () {
    $admin   = makeUser('dsa', isAdmin: true);
    $club    = arClub('PSITS');
    $sitting = makeUser('president');
    arJoin($sitting, $club);
    ClubOfficer::create(['club_id' => $club->id, 'user_id' => $sitting->id, 'position' => 'president']);

    $target = makeUser('member');
    arJoin($target, $club);

    arChangeRole($admin, $target, ['role' => 'president', 'club_id' => $club->id])
        ->assertSessionHas('success', fn ($m) => str_contains($m, $sitting->name . ' held that post and is now a Member'));

    expect(ClubOfficer::where('club_id', $club->id)->where('position', 'president')->pluck('user_id')->all())->toBe([$target->id])
        ->and($sitting->fresh()->role)->toBe('member')
        ->and(ClubMember::where('user_id', $sitting->id)->value('role'))->toBe('Member');
});

it('lets a student hold one post at a time, moving it to the club named', function () {
    $admin  = makeUser('dsa', isAdmin: true);
    $target = makeUser('president');
    arJoin($target, $psits = arClub('PSITS'));
    arJoin($target, $byte = arClub('BYTE'));
    ClubOfficer::create(['club_id' => $psits->id, 'user_id' => $target->id, 'position' => 'president']);

    arChangeRole($admin, $target, ['role' => 'treasurer', 'club_id' => $byte->id])
        ->assertSessionHas('success', fn ($m) => str_contains($m, 'no longer President of PSITS Society'));

    expect(ClubOfficer::where('user_id', $target->id)->pluck('club_id')->all())->toBe([$byte->id])
        ->and(ClubMember::where('user_id', $target->id)->where('club_id', $psits->id)->value('role'))->toBe('Member');
});

it('takes the post away when an officer is made a member again', function () {
    $admin  = makeUser('dsa', isAdmin: true);
    $target = makeUser('vice_president');
    arJoin($target, $club = arClub('PSITS'));
    ClubOfficer::create(['club_id' => $club->id, 'user_id' => $target->id, 'position' => 'vice_president']);

    arChangeRole($admin, $target, ['role' => 'member'])->assertSessionHas('success');

    expect($target->fresh()->role)->toBe('member')
        ->and(ClubOfficer::where('user_id', $target->id)->exists())->toBeFalse();
});

it('opens the officer pages to every new position', function (string $position) {
    $club    = arClub('PSITS');
    $officer = makeUser($position);
    arJoin($officer, $club);
    ClubOfficer::create(['club_id' => $club->id, 'user_id' => $officer->id, 'position' => $position]);

    $this->withSession(adminSession($officer))
        ->get(route('officer.activities.index'))
        ->assertOk();
})->with(['vice_president', 'secretary', 'auditor', 'mmo_internal', 'mmo_external']);

// ── Adviser accounts are employees', never students' ────────────────────────

it('never makes a student account an adviser', function () {
    $admin  = makeUser('dsa', isAdmin: true);
    $target = makeUser('member');

    arChangeRole($admin, $target, ['role' => 'adviser'])->assertSessionHas('error');

    expect($target->fresh()->role)->toBe('member');

    // Not even offered.
    $this->withSession(adminSession($admin))
        ->get(route('admin.users.index', ['search' => $target->email]))
        ->assertDontSee('<option value="adviser"', false);
});

it('never makes an adviser account a member or a club officer', function (string $role) {
    $admin   = makeUser('dsa', isAdmin: true);
    $adviser = arAdviser();
    $club    = arClub('PSITS');
    arJoin($adviser, $club);

    arChangeRole($admin, $adviser, ['role' => $role, 'club_id' => $club->id])->assertSessionHas('error');

    expect($adviser->fresh()->role)->toBe('adviser')
        ->and(ClubOfficer::count())->toBe(0);
})->with(['member', 'president', 'secretary']);

// ── The club an adviser advises: one adviser per club ───────────────────────

it('confirms the club an adviser asked for at sign-up', function () {
    $admin   = makeUser('dsa', isAdmin: true);
    $club    = arClub('PSITS');
    $adviser = arAdviser();
    $adviser->update(['requested_club_id' => $club->id]);

    $this->withSession(adminSession($admin))
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertSee('waiting for you to confirm the club they asked to advise')
        ->assertSee('Asked to advise PSITS Society')
        ->assertSee('Confirm club');

    $this->withSession(adminSession($admin))
        ->from(route('admin.users.index'))
        ->patch(route('admin.users.club.update', $adviser), ['club_id' => $club->id])
        ->assertSessionHas('success');

    expect($club->fresh()->adviser_id)->toBe($adviser->id)
        ->and($adviser->fresh()->requested_club_id)->toBeNull()
        ->and(ClubNotification::where('recipient_id', $adviser->id)->where('title', 'You are now a club adviser')->exists())->toBeTrue()
        ->and(AuditLog::where('action', 'admin.adviser.club_assigned')->exists())->toBeTrue();
});

it('never gives a club a second adviser', function () {
    $admin   = makeUser('dsa', isAdmin: true);
    $sitting = arAdviser();
    $club    = arClub('PSITS', $sitting);
    $adviser = arAdviser();

    $this->withSession(adminSession($admin))
        ->from(route('admin.users.index'))
        ->patch(route('admin.users.club.update', $adviser), ['club_id' => $club->id])
        ->assertSessionHas('error', fn ($m) => str_contains($m, 'already has an adviser'));

    expect($club->fresh()->adviser_id)->toBe($sitting->id);
});

it('moves an adviser to another club, leaving the old one with none', function () {
    $admin   = makeUser('dsa', isAdmin: true);
    $adviser = arAdviser();
    $old     = arClub('PSITS', $adviser);
    $new     = arClub('BYTE');

    $this->withSession(adminSession($admin))
        ->from(route('admin.users.index'))
        ->patch(route('admin.users.club.update', $adviser), ['club_id' => $new->id])
        ->assertSessionHas('success');

    expect($new->fresh()->adviser_id)->toBe($adviser->id)
        ->and($old->fresh()->adviser_id)->toBeNull();
});

it('declines an adviser\'s club request', function () {
    $admin   = makeUser('dsa', isAdmin: true);
    $club    = arClub('PSITS');
    $adviser = arAdviser();
    $adviser->update(['requested_club_id' => $club->id]);

    $this->withSession(adminSession($admin))
        ->from(route('admin.users.index'))
        ->delete(route('admin.users.club-request.destroy', $adviser))
        ->assertSessionHas('success');

    expect($adviser->fresh()->requested_club_id)->toBeNull()
        ->and($club->fresh()->adviser_id)->toBeNull()
        ->and(ClubNotification::where('recipient_id', $adviser->id)->where('title', 'Club request declined')->exists())->toBeTrue();
});

it('takes an adviser off their club when they stop being an adviser', function () {
    $admin   = makeUser('dsa', isAdmin: true);
    $adviser = arAdviser();
    $club    = arClub('PSITS', $adviser);

    arChangeRole($admin, $adviser, ['role' => 'dsa'])
        ->assertSessionHas('success', fn ($m) => str_contains($m, 'PSITS Society has no adviser now'));

    expect($club->fresh()->adviser_id)->toBeNull();
});

it('lets the DSA pick only an adviser who advises no other club', function () {
    $dsa     = makeUser('dsa');
    $adviser = arAdviser();
    arClub('PSITS', $adviser);
    $club    = arClub('BYTE');

    $this->withSession(adminSession($dsa))
        ->patch(route('dsa.clubs.update', $club), [
            'name' => $club->name, 'acronym' => $club->acronym, 'type' => 'non_academic', 'adviser_id' => $adviser->id,
        ])
        ->assertSessionHasErrors('adviser_id');

    expect($club->fresh()->adviser_id)->toBeNull();
});

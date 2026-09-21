<?php

use App\Models\AuditLog;
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

    $response = $this->withSession(adminSession($admin))
        ->from(route('admin.users.index'))
        ->patch(route('admin.users.role.update', $target), ['role' => 'president']);

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

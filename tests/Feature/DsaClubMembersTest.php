<?php

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/*
 * The DSA's read-only member list for a club shows each member's profile photo,
 * which opens larger on click, and falls back to initials when there is none.
 */

function dsaMembersUser(string $role, ?string $photo = null): User
{
    static $n = 0;
    $n++;

    return User::create([
        'first_name'        => 'Dsa',
        'last_name'         => "Member{$n}",
        'edp_number'        => str_pad((string) (8300 + $n), 10, '0', STR_PAD_LEFT),
        'email'             => "dsamembers{$n}@sccpag.edu.ph",
        'password'          => Hash::make('Password1!'),
        'role'              => $role,
        'course_id'         => testCourse()->id,
        'profile_photo_url' => $photo,
        'is_admin'          => false,
        'is_suspended'      => false,
    ]);
}

function dsaMembersSession(User $user): array
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

it('shows each member\'s profile photo to the DSA', function () {
    $dsa         = dsaMembersUser('dsa');
    $withPhoto   = dsaMembersUser('member', '/storage/profile-photos/abc123.jpg');
    $withoutOne  = dsaMembersUser('member');

    $club = Club::create([
        'slug'            => 'dsa-members-club',
        'acronym'         => 'DMC',
        'club_type'       => 'Non-Academic',
        'type'            => 'non_academic',
        'course_slug'     => 'all',
        'department_slug' => 'all',
        'name'            => 'DSA Members Club',
        'is_active'       => true,
    ]);

    ClubMember::create(['club_id' => $club->id, 'user_id' => $withPhoto->id, 'status' => 'active']);
    ClubMember::create(['club_id' => $club->id, 'user_id' => $withoutOne->id, 'status' => 'active']);

    $this->withSession(dsaMembersSession($dsa))
        ->get(route('dsa.clubs.members.index', $club))
        ->assertOk()
        ->assertSee('src="/storage/profile-photos/abc123.jpg"', false)
        ->assertSee('data-photo-name="' . $withPhoto->name . '"', false)
        ->assertSee('id="member-photo-dialog"', false)
        // Only the member who has a photo gets a photo button.
        ->assertDontSee('data-photo-name="' . $withoutOne->name . '"', false)
        ->assertSee($withoutOne->name);
});

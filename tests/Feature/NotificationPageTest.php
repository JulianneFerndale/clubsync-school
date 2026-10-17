<?php

use App\Events\NotificationsRead;
use App\Models\Club;
use App\Models\ClubNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/*
 * The Notifications page on desktop: the layout's top bar names the page (the
 * green mobile header is hidden there), the list sits in a readable column, and
 * each notification with a destination is one clickable card.
 */

function npUser(string $role = 'member'): User
{
    static $n = 0;
    $n++;

    return User::create([
        'first_name'   => 'Notif',
        'last_name'    => "Page{$n}",
        'edp_number'   => str_pad((string) (8500 + $n), 10, '0', STR_PAD_LEFT),
        'email'        => "notifpage{$n}@sccpag.edu.ph",
        'password'     => Hash::make('Password1!'),
        'role'         => $role,
        'course_id'    => testCourse()->id,
        'is_admin'     => false,
        'is_suspended' => false,
    ]);
}

function npSession(User $user): array
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

beforeEach(fn () => Event::fake([NotificationsRead::class]));

test('the desktop top bar names the page for every role', function (string $role, string $route) {
    $user = npUser($role);

    $this->withSession(npSession($user))
        ->get(route($route))
        ->assertOk()
        ->assertSee('<h1 class="text-lg font-semibold text-white">Notifications</h1>', false)
        // The green header is the page title on mobile only.
        ->assertSee('md:hidden bg-[#07873e]', false);
})->with([
    'member'  => ['member', 'member.notifications.index'],
    'adviser' => ['adviser', 'notifications.index'],
    'dsa'     => ['dsa', 'notifications.index'],
]);

test('a notification with a destination is one clickable card naming its club', function () {
    $user = npUser();
    $club = Club::create([
        'slug'            => 'np-club',
        'acronym'         => 'NPC',
        'club_type'       => 'Non-Academic',
        'type'            => 'non_academic',
        'course_slug'     => 'all',
        'department_slug' => 'all',
        'name'            => 'Notification Page Club',
        'is_active'       => true,
    ]);

    ClubNotification::create([
        'recipient_id' => $user->id,
        'sender_type'  => 'system',
        'club_id'      => $club->id,
        'title'        => 'New activity posted',
        'body'         => 'Chess night is on Friday.',
        'action_url'   => '/member/clubs/' . $club->id,
    ]);

    $html = $this->withSession(npSession($user))
        ->get(route('member.notifications.index'))
        ->assertOk()
        ->assertSee('1 new notification')
        ->assertSee('Showing 1–1 of 1')
        ->getContent();

    // The card itself is the link, and it shows the club and an exact time on hover.
    expect($html)->toMatch('#<a\s+href="/member/clubs/' . $club->id . '"\s+class="group flex#')
        ->and($html)->toContain('<span class="font-medium text-gray-500">NPC</span>')
        ->and($html)->toMatch('#<time datetime="[^"]+"\s+title="#');
});

test('a notification without a destination is not a link', function () {
    $user = npUser();

    ClubNotification::create([
        'recipient_id' => $user->id,
        'sender_type'  => 'system',
        'title'        => 'Heads up',
        'body'         => 'Nothing to open.',
    ]);

    $html = $this->withSession(npSession($user))
        ->get(route('member.notifications.index'))
        ->assertOk()
        ->assertSee('Nothing to open.')
        ->getContent();

    expect($html)->toMatch('#<div\s+class="group flex#')
        ->and($html)->not->toMatch('#<a\s+[^>]*class="group flex#');
});

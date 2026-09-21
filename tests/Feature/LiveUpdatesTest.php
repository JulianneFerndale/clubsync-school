<?php

use App\Events\ChatActivity;
use App\Events\NotificationCreated;
use App\Events\NotificationsRead;
use App\Jobs\NotifyMembersOfActivity;
use App\Models\ActivityChangeLog;
use App\Models\ChatMessage;
use App\Models\Club;
use App\Models\ClubActivity;
use App\Models\ClubMember;
use App\Models\ClubNotification;
use App\Models\ClubOfficer;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/*
 * STD 7.1.5 Live Notification and 7.1.6 Chat — the defects found in the Playwright run of
 * 2026-09-15 and the server-side half of their fixes (the browser half was re-verified with
 * Playwright):
 *   1. Every role has a Notifications page and badge; reading clears the badge on other tabs
 *   2. Badge counts can be re-read after Back, a reconnect or returning to a tab
 *   3. Sign-in and sign-out do a full page load, so no live channel outlives a user
 *   4. Club pages opt in to refreshing their content when a notification arrives
 *   5. Chat tells the user's other tabs when a conversation is read
 *   6. An activity can be edited without re-entering its times, and unchanged times
 *      neither log a change nor notify members
 */

// ── Fixtures ─────────────────────────────────────────────────────────────────

function luUser(string $role = 'member'): User
{
    static $n = 0;
    $n++;

    return User::create([
        'first_name'                   => 'Live',
        'last_name'                    => "Tester{$n}",
        'edp_number'                   => str_pad((string) (7000 + $n), 10, '0', STR_PAD_LEFT),
        'email'                        => "live{$n}@sccpag.edu.ph",
        'password'                     => Hash::make('Password1!'),
        'role'                         => $role,
        'course_id'                    => testCourse()->id,
        'is_admin'                     => false,
        'is_suspended'                 => false,
        'chat_privacy_acknowledged_at' => now(),
    ]);
}

function luSession(User $user): array
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

function luClub(): Club
{
    static $n = 0;
    $n++;

    return Club::create([
        'slug'            => 'live-club-' . $n,
        'acronym'         => 'LC' . $n,
        'club_type'       => 'Non-Academic',
        'type'            => 'non_academic',
        'course_slug'     => 'all',
        'department_slug' => 'all',
        'name'            => 'Live Club ' . $n,
        'is_active'       => true,
    ]);
}

function luNotify(User $user, ?Club $club = null, string $title = 'Club update'): ClubNotification
{
    return ClubNotification::create([
        'recipient_id' => $user->id,
        'sender_type'  => 'system',
        'club_id'      => $club?->id,
        'title'        => $title,
        'body'         => 'Something changed.',
        'action_url'   => '/member/dashboard',
    ]);
}

// ── 1. Notifications page and badge for every role ──────────────────────────

test('every role can open a Notifications page in its own layout', function (string $role, string $sidebarMarker) {
    Event::fake([NotificationCreated::class, NotificationsRead::class]);
    $user = luUser($role);
    luNotify($user, title: 'A notification for ' . $role);

    $this->withSession(luSession($user))
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertSee('A notification for ' . $role)
        ->assertSee($sidebarMarker, false)
        ->assertSee('js-notif-badge', false);
})->with([
    'adviser'   => ['adviser', 'Adviser Panel'],
    'dsa'       => ['dsa', 'DSA'],
    'president' => ['president', 'officer'],
    'member'    => ['member', 'Bulletin'],
]);

test('officer, adviser and DSA layouts link to Notifications with a live badge', function (string $role, string $page) {
    $user = luUser($role);
    luNotify($user);

    $html = $this->withSession(luSession($user))->get(route('notifications.index'))->assertOk()->getContent();

    expect($html)->toContain('href="' . route('notifications.index') . '"')
        ->and(substr_count($html, 'js-notif-badge'))->toBeGreaterThanOrEqual(2); // sidebar + mobile drawer
})->with([
    'adviser'   => ['adviser', 'adviser.dashboard'],
    'dsa'       => ['dsa', 'dsa.dashboard'],
    'president' => ['president', 'officer.dashboard'],
]);

test('the member bottom navigation keeps a live Notifications badge even at zero', function () {
    $user = luUser('member');

    $html = $this->withSession(luSession($user))->get(route('member.notifications.index'))->assertOk()->getContent();

    expect(substr_count($html, 'js-notif-badge'))->toBe(3); // sidebar, header, bottom nav
});

test('reading notifications marks them read and tells the user\'s other tabs', function () {
    Event::fake([NotificationCreated::class, NotificationsRead::class]);
    $user = luUser('adviser');
    luNotify($user);
    luNotify($user);

    $this->withSession(luSession($user))->get(route('notifications.index'))->assertOk()->assertSee('2 new notifications');

    expect(ClubNotification::unreadCountFor($user->id))->toBe(0);
    Event::assertDispatched(NotificationsRead::class, fn (NotificationsRead $e) => $e->userId === $user->id
        && $e->broadcastOn()->name === 'private-notifications.' . $user->id
        && $e->broadcastWith() === ['unread' => 0]);
});

test('opening notifications with nothing unread sends no read signal', function () {
    Event::fake([NotificationsRead::class]);
    $user = luUser('dsa');

    $this->withSession(luSession($user))->get(route('notifications.index'))->assertOk();

    Event::assertNotDispatched(NotificationsRead::class);
});

test('a notification can be marked read only by its recipient, from any role', function () {
    Event::fake([NotificationCreated::class, NotificationsRead::class]);
    [$adviser, $other] = [luUser('adviser'), luUser('adviser')];
    $notification = luNotify($adviser);

    $this->withSession(luSession($other))->post(route('notifications.read', $notification))->assertForbidden();
    $this->withSession(luSession($adviser))->post(route('notifications.read', $notification))->assertRedirect('/member/dashboard');

    expect($notification->fresh()->is_read)->toBeTrue();
    Event::assertDispatchedTimes(NotificationsRead::class, 1);
});

test('the live notification payload names the club so only that club\'s page refreshes', function () {
    $user = luUser();
    $club = luClub();
    $payload = (new NotificationCreated(luNotify($user, $club)))->broadcastWith();

    expect($payload['club_id'])->toBe($club->id)
        ->and($payload)->toHaveKeys(['id', 'club_id', 'title', 'body', 'action_url', 'created_at', 'unread']);
});

// ── 2. Badge counts can be re-read ───────────────────────────────────────────

test('live counts return the unread notification and chat totals for the signed-in user only', function () {
    Event::fake([NotificationCreated::class, ChatActivity::class]);
    [$a, $b] = [luUser(), luUser()];
    luNotify($a);
    luNotify($a);
    luNotify($b);

    $conversation = Conversation::between($a, $b);
    $this->withSession(luSession($b))->postJson(route('chat.messages.store', $conversation), ['body' => 'hello'])->assertCreated();

    $this->withSession(luSession($a))->getJson(route('live.counts'))
        ->assertOk()
        ->assertExactJson(['notifications' => 2, 'chat' => 1]);
});

test('authorising a private channel does not query the database', function () {
    $user = luUser();
    \Illuminate\Support\Facades\DB::flushQueryLog();
    \Illuminate\Support\Facades\DB::enableQueryLog();

    $this->withSession(luSession($user))
        ->postJson('/broadcasting/auth', ['socket_id' => '1.1', 'channel_name' => 'private-notifications.' . $user->id]);

    $userQueries = collect(\Illuminate\Support\Facades\DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], '"users"'));
    expect($userQueries)->toHaveCount(0);
});

test('a page reads both unread badge counts in one query', function () {
    Event::fake([NotificationCreated::class]);
    $user = luUser('adviser');
    luNotify($user);

    \Illuminate\Support\Facades\DB::flushQueryLog();
    \Illuminate\Support\Facades\DB::enableQueryLog();
    $this->withSession(luSession($user))->get(route('profile'))->assertOk()->assertSee('js-notif-badge', false);

    $countQueries = collect(\Illuminate\Support\Facades\DB::getQueryLog())
        ->filter(fn ($q) => str_contains($q['query'], '"notifications"') || str_contains($q['query'], '"chat_messages"'));

    expect($countQueries)->toHaveCount(1);
});

test('live counts require a signed-in user', function () {
    $this->getJson(route('live.counts'))->assertUnauthorized();
});

// ── 3. Sign-in and sign-out leave no live channel behind ─────────────────────

test('sign-in, sign-out and the signing-in registration step do full page loads', function () {
    expect(file_get_contents(resource_path('views/auth/login.blade.php')))->toContain('action="{{ route(\'login\') }}" data-turbo="false"')
        ->and(file_get_contents(resource_path('views/auth/register/course.blade.php')))->toContain('data-turbo="false"');

    foreach (['member', 'officer', 'adviser', 'dsa', 'admin'] as $layout) {
        $html = file_get_contents(resource_path("views/layouts/app-{$layout}.blade.php"));
        expect(substr_count($html, 'action="{{ route(\'logout\') }}" data-turbo="false"'))->toBe(substr_count($html, "route('logout')"));
    }
});

test('the browser listener reconnects when the signed-in user changes', function () {
    $js = file_get_contents(resource_path('js/app.js'));

    expect($js)->toContain("document.addEventListener('turbo:load', syncLiveUser)")
        ->and($js)->toContain('window.Echo.disconnect()')
        ->and($js)->not->toContain("addEventListener('DOMContentLoaded', subscribeToNotifications)");
});

// ── 4. Club pages refresh their content ──────────────────────────────────────

test('a club page opts in to live refresh for its own club only', function () {
    $user = luUser('member');
    $club = luClub();
    ClubMember::create(['club_id' => $club->id, 'user_id' => $user->id, 'role' => 'Member', 'status' => 'active']);

    $this->withSession(luSession($user))
        ->get(route('member.clubs.show', $club))
        ->assertOk()
        ->assertSee('id="cs-page"', false)
        ->assertSee('data-live-refresh="club:' . $club->id . '"', false);
});

test('pages without club updates do not opt in to live refresh', function () {
    $user = luUser('member');

    $this->withSession(luSession($user))->get(route('profile'))->assertOk()->assertDontSee('data-live-refresh', false);
});

// ── 5. Chat read signal ──────────────────────────────────────────────────────

test('opening a conversation with unread messages tells the reader\'s other tabs', function () {
    [$a, $b] = [luUser(), luUser()];
    $conversation = Conversation::between($a, $b);

    Event::fake([ChatActivity::class]);
    $this->withSession(luSession($a))->postJson(route('chat.messages.store', $conversation), ['body' => 'one'])->assertCreated();

    Event::assertDispatched(ChatActivity::class, fn (ChatActivity $e) => $e->userId === $a->id && $e->broadcastWith()['unread'] === null);
    Event::assertDispatched(ChatActivity::class, fn (ChatActivity $e) => $e->userId === $b->id && $e->broadcastWith()['unread'] === 1);

    Event::fake([ChatActivity::class]);
    $this->withSession(luSession($b))->get(route('chat.show', $conversation))->assertOk();

    Event::assertDispatchedTimes(ChatActivity::class, 1);
    Event::assertDispatched(ChatActivity::class, fn (ChatActivity $e) => $e->userId === $b->id
        && $e->type === 'read'
        && $e->broadcastOn()->name === 'private-chat.' . $b->id
        && $e->broadcastWith()['unread'] === 0);

    // Nothing new to read: no further signal.
    Event::fake([ChatActivity::class]);
    $this->withSession(luSession($b))->get(route('chat.show', $conversation))->assertOk();
    Event::assertNotDispatched(ChatActivity::class);
});

test('fetching new messages in an open conversation also sends the read signal', function () {
    [$a, $b] = [luUser(), luUser()];
    $conversation = Conversation::between($a, $b);

    Event::fake([ChatActivity::class]);
    $this->withSession(luSession($a))->postJson(route('chat.messages.store', $conversation), ['body' => 'two'])->assertCreated();

    Event::fake([ChatActivity::class]);
    $this->withSession(luSession($b))
        ->getJson(route('chat.messages.index', $conversation) . '?after=0')
        ->assertOk()
        ->assertJsonPath('unread', 0);

    Event::assertDispatched(ChatActivity::class, fn (ChatActivity $e) => $e->userId === $b->id && $e->type === 'read');
    expect(ChatMessage::unreadCountFor($a->id))->toBe(0);
});

test('a fetch triggered by a new-message signal skips the unsend and can-send checks', function () {
    [$a, $b] = [luUser(), luUser()];
    $conversation = Conversation::between($a, $b);
    Event::fake([ChatActivity::class]);
    $this->withSession(luSession($a))->postJson(route('chat.messages.store', $conversation), ['body' => 'first'])->assertCreated();
    $first = ChatMessage::latest('id')->first();
    $this->withSession(luSession($a))->deleteJson(route('chat.messages.retract', [$conversation, $first]))->assertOk();

    $light = $this->withSession(luSession($b))->getJson(route('chat.messages.index', $conversation) . '?after=0&since_id=' . $first->id . '&new=1')->assertOk();
    $full  = $this->withSession(luSession($b))->getJson(route('chat.messages.index', $conversation) . '?after=0&since_id=' . $first->id)->assertOk();

    expect($light->json('can_send'))->toBeNull()
        ->and($light->json('retracted_ids'))->toBe([])
        ->and($full->json('can_send'))->toBeTrue()
        ->and($full->json('retracted_ids'))->toBe([$first->id]);
});

test('blocking and unblocking tell the other participant\'s open conversation, and no one else', function () {
    [$a, $b, $c] = [luUser(), luUser(), luUser()];
    $conversation = Conversation::between($a, $b);

    Event::fake([ChatActivity::class]);
    $this->withSession(luSession($a))->post(route('chat.people.block', $b))->assertRedirect();

    Event::assertDispatchedTimes(ChatActivity::class, 1);
    Event::assertDispatched(ChatActivity::class, fn (ChatActivity $e) => $e->userId === $b->id
        && $e->type === 'access'
        && $e->conversationId === $conversation->id
        && $e->broadcastWith()['unread'] === null);

    Event::fake([ChatActivity::class]);
    $this->withSession(luSession($a))->delete(route('chat.people.unblock', $b))->assertRedirect();
    Event::assertDispatched(ChatActivity::class, fn (ChatActivity $e) => $e->userId === $b->id && $e->type === 'access');

    // No conversation between them yet: nothing to update, no signal.
    Event::fake([ChatActivity::class]);
    $this->withSession(luSession($a))->post(route('chat.people.block', $c))->assertRedirect();
    Event::assertNotDispatched(ChatActivity::class);
});

test('the chat page queues sends and never drops a signal that arrives mid-fetch', function () {
    $view = file_get_contents(resource_path('views/chat/show.blade.php'));

    expect($view)->toContain("if (polling) { pollAgain = (pollAgain === 'full' || mode === 'full') ? 'full' : 'new'; return; }")
        ->and($view)->toContain('const outbox = [];')
        ->and($view)->not->toContain("textarea.value = '';\n                autosize();\n                toBottom();\n            } else if");
});

// ── 6. Activity edits and PostgreSQL time values ─────────────────────────────

function luOfficerActivity(): array
{
    $club = luClub();
    $officer = luUser('president');
    ClubOfficer::create(['club_id' => $club->id, 'user_id' => $officer->id, 'position' => 'president']);

    $member = luUser('member');
    ClubMember::create(['club_id' => $club->id, 'user_id' => $member->id, 'role' => 'Member', 'status' => 'active']);

    // PostgreSQL returns time columns as H:i:s; store them that way here too.
    $activity = ClubActivity::create([
        'club_id'               => $club->id,
        'title'                 => 'Weekly Meeting',
        'description'           => 'Regular meeting.',
        'date'                  => now()->addDays(7)->toDateString(),
        'time_start'            => '07:10:00',
        'time_end'              => '08:10:00',
        'venue'                 => 'Others',
        'purpose'               => 'Planning.',
        'expected_participants' => 20,
        'activity_type'         => 'internal_meeting',
        'approval_status'       => 'no_approval_needed',
        'status'                => 'scheduled',
        'created_by'            => $officer->id,
    ]);

    return [$officer, $activity];
}

function luEditPayload(ClubActivity $activity, array $overrides = []): array
{
    return array_merge([
        'title'                 => $activity->title,
        'description'           => $activity->description,
        'date'                  => $activity->date->toDateString(),
        'time_start'            => $activity->time_start,
        'time_end'              => $activity->time_end,
        'venue'                 => $activity->venue,
        'purpose'               => $activity->purpose,
        'expected_participants' => $activity->expected_participants,
        'activity_type'         => $activity->activity_type,
    ], $overrides);
}

test('the activity edit form shows times as HH:MM', function () {
    [$officer, $activity] = luOfficerActivity();

    $this->withSession(luSession($officer))
        ->get(route('officer.activities.edit', $activity))
        ->assertOk()
        ->assertSee('name="time_start" value="07:10"', false)
        ->assertSee('name="time_end" value="08:10"', false);
});

test('an officer can save an edit that sends the times back with seconds', function () {
    Queue::fake();
    [$officer, $activity] = luOfficerActivity();

    $this->withSession(luSession($officer))
        ->patch(route('officer.activities.update', $activity), luEditPayload($activity, ['expected_participants' => 25]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('officer.activities.show', $activity));

    expect($activity->fresh()->expected_participants)->toBe(25);
});

test('an edit that leaves the times unchanged neither logs a time change nor notifies members', function () {
    Queue::fake();
    [$officer, $activity] = luOfficerActivity();

    $this->withSession(luSession($officer))
        ->patch(route('officer.activities.update', $activity), luEditPayload($activity, ['time_start' => '07:10', 'time_end' => '08:10', 'expected_participants' => 30]))
        ->assertSessionHasNoErrors();

    $fields = collect(ActivityChangeLog::where('club_activity_id', $activity->id)->get())
        ->flatMap(fn ($log) => array_column($log->changes, 'field'))
        ->all();

    expect($fields)->toBe(['expected_participants']);
    Queue::assertNotPushed(NotifyMembersOfActivity::class);
});

test('a real time change on edit still notifies members', function () {
    Queue::fake();
    [$officer, $activity] = luOfficerActivity();

    $this->withSession(luSession($officer))
        ->patch(route('officer.activities.update', $activity), luEditPayload($activity, ['time_start' => '07:30', 'time_end' => '08:30']))
        ->assertSessionHasNoErrors();

    Queue::assertPushed(NotifyMembersOfActivity::class);
});

// ── Assets ───────────────────────────────────────────────────────────────────

test('the college crest used by the layouts exists', function () {
    expect(file_exists(public_path('images/scc-crest.png')))->toBeTrue();
});

<?php

use App\Events\ChatActivity;
use App\Models\AuditLog;
use App\Models\ChatBlock;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/*
 * Private messaging — POLICY.md §Private Messaging and §Data Privacy (RA 10173).
 *
 * The rules under test:
 *   1. Only the two participants can read or write a conversation (others get 404)
 *   2. Message text is encrypted at rest and never copied into the audit log or a broadcast
 *   3. The privacy notice must be acknowledged before chat opens
 *   4. People search discloses name, coarse role and photo only
 *   5. Users control their data: unsend, delete conversation, block
 *   6. Messages past the retention period are erased
 */

// ── Fixtures ─────────────────────────────────────────────────────────────────

function chatUser(string $role = 'member', array $overrides = []): User
{
    static $n = 0;
    $n++;

    return User::create(array_merge([
        'first_name'                   => 'Chat',
        'last_name'                    => "Person{$n}",
        'edp_number'                   => str_pad((string) (5000 + $n), 10, '0', STR_PAD_LEFT),
        'email'                        => "chat{$n}@sccpag.edu.ph",
        'password'                     => Hash::make('Password1!'),
        'role'                         => $role,
        'course_id'                    => testCourse()->id,
        'is_admin'                     => false,
        'is_suspended'                 => false,
        'chat_privacy_acknowledged_at' => now(),
    ], $overrides));
}

function chatSession(User $user): array
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

/** Send a message as $from through the real endpoint and return the stored row. */
function chatSend($test, User $from, Conversation $conversation, string $body): ChatMessage
{
    $test->withSession(chatSession($from))
        ->postJson(route('chat.messages.store', $conversation), ['body' => $body])
        ->assertCreated();

    return ChatMessage::latest('id')->firstOrFail();
}

// ── 1. Only participants can reach a conversation ────────────────────────────

test('a guest is sent to login', function () {
    $this->get(route('chat.index'))->assertRedirect(route('login'));
});

test('every role can open Messages', function (string $role) {
    $user = chatUser($role);

    $this->withSession(chatSession($user))->get(route('chat.index'))->assertOk()->assertSee('New message');
})->with(['member', 'president', 'treasurer', 'mmo', 'adviser', 'dsa']);

test('a non-participant gets 404 on every conversation endpoint, including the DSA and admins', function (User $outsider) {
    [$a, $b] = [chatUser(), chatUser()];
    $conversation = Conversation::between($a, $b);
    $message = chatSend($this, $a, $conversation, 'Just between us');

    $session = chatSession($outsider);

    $this->withSession($session)->get(route('chat.show', $conversation))->assertNotFound();
    $this->withSession($session)->getJson(route('chat.messages.index', $conversation))->assertNotFound();
    $this->withSession($session)->postJson(route('chat.messages.store', $conversation), ['body' => 'hi'])->assertNotFound();
    $this->withSession($session)->deleteJson(route('chat.messages.retract', [$conversation, $message]))->assertNotFound();
    $this->withSession($session)->delete(route('chat.clear', $conversation))->assertNotFound();

    expect(ChatMessage::count())->toBe(1)
        ->and($message->fresh()->body)->toBe('Just between us');
})->with([
    'a student'  => fn () => chatUser(),
    'the DSA'    => fn () => chatUser('dsa'),
    'an admin'   => fn () => chatUser('adviser', ['is_admin' => true]),
]);

test('a message id from another conversation cannot be unsent through this one', function () {
    [$a, $b, $c] = [chatUser(), chatUser(), chatUser()];
    $mine = Conversation::between($a, $b);
    $theirs = Conversation::between($b, $c);
    $message = chatSend($this, $b, $theirs, 'Not yours');

    $this->withSession(chatSession($a))
        ->deleteJson(route('chat.messages.retract', [$mine, $message]))
        ->assertNotFound();

    expect($message->fresh()->isRetracted())->toBeFalse();
});

test('only the sender can unsend a message', function () {
    [$a, $b] = [chatUser(), chatUser()];
    $conversation = Conversation::between($a, $b);
    $message = chatSend($this, $a, $conversation, 'Mine to take back');

    $this->withSession(chatSession($b))
        ->deleteJson(route('chat.messages.retract', [$conversation, $message]))
        ->assertForbidden();

    expect($message->fresh()->body)->toBe('Mine to take back');
});

test('the same two people always share one conversation, whichever of them starts it', function () {
    [$a, $b] = [chatUser(), chatUser()];

    $first = $this->withSession(chatSession($a))->postJson(route('chat.start'), ['recipient_id' => $b->id])->assertOk()->json('url');
    $second = $this->withSession(chatSession($b))->postJson(route('chat.start'), ['recipient_id' => $a->id])->assertOk()->json('url');

    expect($first)->toBe($second)
        ->and(Conversation::count())->toBe(1)
        ->and(ConversationParticipant::count())->toBe(2);
});

test('nobody can start a conversation with themselves or a suspended user', function () {
    $a = chatUser();
    $suspended = chatUser('member', ['is_suspended' => true]);

    $this->withSession(chatSession($a))->postJson(route('chat.start'), ['recipient_id' => $a->id])->assertStatus(422);
    $this->withSession(chatSession($a))->postJson(route('chat.start'), ['recipient_id' => $suspended->id])->assertStatus(422);
    $this->withSession(chatSession($a))->postJson(route('chat.start'), ['recipient_id' => 999999])->assertStatus(422);

    expect(Conversation::count())->toBe(0);
});

test('a suspended user cannot use chat even with a live session', function () {
    $user = chatUser('member', ['is_suspended' => true]);

    $this->withSession(chatSession($user))->get(route('chat.index'))->assertForbidden();
});

// ── 2. Encryption at rest, and no plaintext copies ──────────────────────────

test('message text is stored encrypted, and decrypts only through the model', function () {
    [$a, $b] = [chatUser(), chatUser()];
    $conversation = Conversation::between($a, $b);
    $message = chatSend($this, $a, $conversation, 'My EDP is 12345, please keep it private');

    $raw = DB::table('chat_messages')->where('id', $message->id)->value('body');

    expect($raw)->not->toContain('12345')
        ->and($raw)->not->toContain('private')
        ->and($message->fresh()->body)->toBe('My EDP is 12345, please keep it private');
});

test('sending writes an audit entry that names the people but never the text', function () {
    [$a, $b] = [chatUser(), chatUser()];
    $conversation = Conversation::between($a, $b);
    $message = chatSend($this, $a, $conversation, 'secret-phrase-xyz');

    $entry = AuditLog::where('action', 'chat.message.sent')->sole();

    expect($entry->actor_id)->toBe($a->id)
        ->and($entry->resource_type)->toBe(ChatMessage::class)
        ->and($entry->resource_id)->toBe($message->id)
        ->and($entry->metadata)->toBe(['conversation_id' => $conversation->id, 'recipient_id' => $b->id])
        ->and(json_encode(AuditLog::all()->toArray()))->not->toContain('secret-phrase-xyz');
});

test('the real-time signal reaches both participants and carries no message text', function () {
    Event::fake([ChatActivity::class]);

    [$a, $b] = [chatUser(), chatUser()];
    $conversation = Conversation::between($a, $b);
    chatSend($this, $a, $conversation, 'secret-phrase-xyz');

    Event::assertDispatched(ChatActivity::class, 2);
    Event::assertDispatched(ChatActivity::class, function (ChatActivity $event) use ($b, $conversation) {
        $payload = $event->broadcastWith();

        return $event->userId === $b->id
            && $event->broadcastOn()->name === 'private-chat.' . $b->id
            && $payload['conversation_id'] === $conversation->id
            && $payload['unread'] === 1
            && array_keys($payload) === ['type', 'conversation_id', 'message_id', 'sender_id', 'unread']
            && ! str_contains(json_encode($payload), 'secret-phrase-xyz');
    });
});

test('a user may only subscribe to their own chat channel', function () {
    [$a, $b] = [chatUser(), chatUser()];
    $callback = app(\Illuminate\Contracts\Broadcasting\Broadcaster::class)->getChannels()['chat.{userId}'];

    expect($callback($a, $a->id))->toBeTrue()
        ->and($callback($a, $b->id))->toBeFalse();
});

test('chat pages are never cached by the service worker', function () {
    $sw = file_get_contents(public_path('sw.js'));

    expect($sw)->toContain("url.pathname === '/chat' || url.pathname.startsWith('/chat/')");
});

// ── 3. Privacy notice ───────────────────────────────────────────────────────

test('chat stays closed until the privacy notice is acknowledged, and acknowledging is audited', function () {
    $newcomer = chatUser('member', ['chat_privacy_acknowledged_at' => null]);
    $other = chatUser();
    $conversation = Conversation::between($newcomer, $other);
    $session = chatSession($newcomer);

    $this->withSession($session)->get(route('chat.index'))
        ->assertOk()
        ->assertSee('Private messages and your privacy')
        ->assertSee('Messages are deleted automatically after 365 days')
        ->assertDontSee('New message');

    $this->withSession($session)->get(route('chat.show', $conversation))->assertRedirect(route('chat.index'));
    $this->withSession($session)->postJson(route('chat.messages.store', $conversation), ['body' => 'hi'])->assertStatus(409);
    $this->withSession($session)->getJson(route('chat.people.search', ['q' => 'Chat']))->assertOk()->assertExactJson(['results' => []]);

    $this->withSession($session)->post(route('chat.privacy.acknowledge'))->assertRedirect(route('chat.index'));

    expect($newcomer->fresh()->hasAcknowledgedChatPrivacy())->toBeTrue()
        ->and(AuditLog::where('action', 'chat.privacy.acknowledged')->where('actor_id', $newcomer->id)->exists())->toBeTrue();

    $this->withSession($session)->postJson(route('chat.messages.store', $conversation), ['body' => 'hi'])->assertCreated();
});

// ── 4. People search discloses the minimum ──────────────────────────────────

test('people search returns only name, coarse role and photo', function () {
    $me = chatUser();
    chatUser('treasurer', ['first_name' => 'Maricel', 'last_name' => 'Santos']);

    $response = $this->withSession(chatSession($me))
        ->getJson(route('chat.people.search', ['q' => 'maricel']))
        ->assertOk();

    $result = $response->json('results.0');

    expect(array_keys($result))->toBe(['id', 'name', 'role_label', 'photo_url'])
        ->and($result['name'])->toBe('Maricel Santos')
        ->and($result['role_label'])->toBe('Club Officer'); // position (treasurer) not revealed
    expect($response->getContent())->not->toContain('@sccpag.edu.ph');
});

test('people search needs two letters, caps its results, and leaves out yourself and suspended users', function () {
    $me = chatUser('member', ['first_name' => 'Zed', 'last_name' => 'Searcher']);
    chatUser('member', ['first_name' => 'Zed', 'last_name' => 'Suspended', 'is_suspended' => true]);
    foreach (range(1, 12) as $i) {
        chatUser('member', ['first_name' => 'Zed', 'last_name' => "Match{$i}"]);
    }
    $session = chatSession($me);

    $this->withSession($session)->getJson(route('chat.people.search', ['q' => 'z']))->assertExactJson(['results' => []]);

    $names = collect($this->withSession($session)->getJson(route('chat.people.search', ['q' => 'zed']))->json('results'))->pluck('name');

    expect($names)->toHaveCount(10)
        ->and($names)->not->toContain('Zed Searcher')
        ->and($names)->not->toContain('Zed Suspended');
});

test('LIKE wildcards in a search are treated as plain text', function () {
    $me = chatUser();
    chatUser('member', ['first_name' => 'Ana', 'last_name' => 'Reyes']);

    $this->withSession(chatSession($me))
        ->getJson(route('chat.people.search', ['q' => '%%']))
        ->assertExactJson(['results' => []]);

    $this->withSession(chatSession($me))
        ->getJson(route('chat.people.search', ['q' => 'a_a']))
        ->assertExactJson(['results' => []]); // unescaped, "a_a" would match "Ana"

    $this->withSession(chatSession($me))
        ->getJson(route('chat.people.search', ['q' => 'ana re']))
        ->assertJsonPath('results.0.name', 'Ana Reyes');
});

// ── Messaging behaviour ─────────────────────────────────────────────────────

test('a sent message reaches the recipient, counts as unread, and is read once they open the thread', function () {
    [$a, $b] = [chatUser(), chatUser()];
    $conversation = Conversation::between($a, $b);
    chatSend($this, $a, $conversation, 'Meeting moved to 3 PM');

    expect(ChatMessage::unreadCountFor($b->id))->toBe(1);
    ChatMessage::forgetUnreadCount($a->id);
    expect(ChatMessage::unreadCountFor($a->id))->toBe(0); // your own messages are never unread

    $this->withSession(chatSession($b))->get(route('chat.index'))
        ->assertOk()
        ->assertSee($a->name)
        ->assertSee('Meeting moved to 3 PM');

    $this->withSession(chatSession($b))->get(route('chat.show', $conversation))
        ->assertOk()
        ->assertSee('Meeting moved to 3 PM')
        ->assertSee('Only you and ' . $a->first_name . ' can see this conversation');

    ChatMessage::forgetUnreadCount($b->id);
    expect(ChatMessage::unreadCountFor($b->id))->toBe(0);
});

test('message text is escaped when rendered', function () {
    [$a, $b] = [chatUser(), chatUser()];
    $conversation = Conversation::between($a, $b);
    chatSend($this, $a, $conversation, '<script>alert(1)</script>');

    $this->withSession(chatSession($b))->get(route('chat.show', $conversation))
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
});

test('the JSON feed returns new messages, reports unsent ones, and pages back through history', function () {
    [$a, $b] = [chatUser(), chatUser()];
    $conversation = Conversation::between($a, $b);
    $first = chatSend($this, $a, $conversation, 'one');
    $second = chatSend($this, $a, $conversation, 'two');

    $this->withSession(chatSession($b))
        ->getJson(route('chat.messages.index', [$conversation, 'after' => $first->id]))
        ->assertOk()
        ->assertJsonPath('messages.0.body', 'two')
        ->assertJsonPath('messages.0.mine', false)
        ->assertJsonCount(1, 'messages')
        ->assertJsonPath('unread', 0)
        ->assertJsonPath('can_send', true);

    $this->withSession(chatSession($a))->deleteJson(route('chat.messages.retract', [$conversation, $first]))->assertOk();

    $this->withSession(chatSession($b))
        ->getJson(route('chat.messages.index', [$conversation, 'after' => $second->id, 'since_id' => $first->id]))
        ->assertJsonPath('retracted_ids', [$first->id]);

    $this->withSession(chatSession($b))
        ->getJson(route('chat.messages.index', [$conversation, 'before' => $second->id]))
        ->assertJsonPath('messages.0.retracted', true)
        ->assertJsonPath('messages.0.body', null)
        ->assertJsonPath('has_earlier', false);
});

test('empty and over-long messages are rejected with JSON errors', function () {
    [$a, $b] = [chatUser(), chatUser()];
    $conversation = Conversation::between($a, $b);
    $session = chatSession($a);

    $this->withSession($session)->postJson(route('chat.messages.store', $conversation), ['body' => '   '])
        ->assertStatus(422)->assertJsonPath('errors.body.0', 'Type a message first.');

    $this->withSession($session)->postJson(route('chat.messages.store', $conversation), ['body' => str_repeat('a', 2001)])
        ->assertStatus(422)->assertJsonPath('errors.body.0', 'Messages can be at most 2000 characters.');

    expect(ChatMessage::count())->toBe(0);
});

test('a form post without JavaScript still sends and redirects back to the thread', function () {
    [$a, $b] = [chatUser(), chatUser()];
    $conversation = Conversation::between($a, $b);

    $this->withSession(chatSession($a))
        ->post(route('chat.messages.store', $conversation), ['body' => 'no-js hello'])
        ->assertRedirect(route('chat.show', $conversation));

    expect(ChatMessage::sole()->body)->toBe('no-js hello');
});

// ── 5. User control: unsend, delete, block ──────────────────────────────────

test('unsending destroys the text rather than hiding it, and is audited', function () {
    [$a, $b] = [chatUser(), chatUser()];
    $conversation = Conversation::between($a, $b);
    $message = chatSend($this, $a, $conversation, 'sent by mistake');

    $this->withSession(chatSession($a))
        ->deleteJson(route('chat.messages.retract', [$conversation, $message]))
        ->assertOk()
        ->assertJsonPath('message.retracted', true);

    expect(DB::table('chat_messages')->where('id', $message->id)->value('body'))->toBeNull()
        ->and(AuditLog::where('action', 'chat.message.retracted')->where('resource_id', $message->id)->exists())->toBeTrue();

    $this->withSession(chatSession($b))->get(route('chat.show', $conversation))
        ->assertSee('Message deleted')
        ->assertDontSee('sent by mistake');
});

test('deleting a conversation hides it only for the person who deleted it', function () {
    [$a, $b] = [chatUser(), chatUser()];
    $conversation = Conversation::between($a, $b);
    chatSend($this, $a, $conversation, 'old history');

    $this->withSession(chatSession($b))->delete(route('chat.clear', $conversation))->assertRedirect(route('chat.index'));

    $this->withSession(chatSession($b))->get(route('chat.index'))->assertDontSee('old history');
    $this->withSession(chatSession($b))->get(route('chat.show', $conversation))->assertDontSee('old history');
    $this->withSession(chatSession($a))->get(route('chat.show', $conversation))->assertSee('old history');

    // A new message brings the conversation back — without the cleared history.
    chatSend($this, $a, $conversation, 'fresh start');
    $this->withSession(chatSession($b))->get(route('chat.index'))->assertSee('fresh start');
    $this->withSession(chatSession($b))->get(route('chat.show', $conversation))
        ->assertSee('fresh start')
        ->assertDontSee('old history');
});

test('once both people delete a conversation, its messages are erased from the database', function () {
    [$a, $b] = [chatUser(), chatUser()];
    $conversation = Conversation::between($a, $b);
    chatSend($this, $a, $conversation, 'one');
    chatSend($this, $b, $conversation, 'two');

    $this->withSession(chatSession($a))->delete(route('chat.clear', $conversation));
    expect(ChatMessage::count())->toBe(2);

    $this->withSession(chatSession($b))->delete(route('chat.clear', $conversation));

    expect(ChatMessage::count())->toBe(0)
        ->and(Conversation::count())->toBe(0)
        ->and(ConversationParticipant::count())->toBe(0)
        ->and(AuditLog::where('action', 'chat.conversation.cleared')->count())->toBe(2);
});

test('a block stops both people sending until it is lifted', function () {
    [$a, $b] = [chatUser(), chatUser()];
    $conversation = Conversation::between($a, $b);

    $this->withSession(chatSession($b))->post(route('chat.people.block', $a))->assertRedirect();
    expect(AuditLog::where('action', 'chat.user.blocked')->where('actor_id', $b->id)->exists())->toBeTrue();

    $this->withSession(chatSession($a))->postJson(route('chat.messages.store', $conversation), ['body' => 'hello?'])
        ->assertForbidden()
        ->assertJsonPath('message', "You can't send messages in this conversation.");
    $this->withSession(chatSession($b))->postJson(route('chat.messages.store', $conversation), ['body' => 'hello?'])
        ->assertForbidden();

    // The blocked person is not told they were blocked; the blocker sees how to undo it.
    $this->withSession(chatSession($a))->get(route('chat.show', $conversation))
        ->assertSee('data-can-send="0"', false)
        ->assertDontSee('You blocked')
        ->assertDontSee('blocked you');
    $this->withSession(chatSession($b))->get(route('chat.show', $conversation))
        ->assertSee('data-can-send="0"', false)
        ->assertSee('You blocked ' . $a->first_name);

    $this->withSession(chatSession($b))->delete(route('chat.people.unblock', $a))->assertRedirect();

    expect(ChatBlock::count())->toBe(0);
    $this->withSession(chatSession($a))->get(route('chat.show', $conversation))->assertSee('data-can-send="1"', false);
    $this->withSession(chatSession($a))->postJson(route('chat.messages.store', $conversation), ['body' => 'hello again'])->assertCreated();
});

test('nobody can message a user who has since been suspended', function () {
    [$a, $b] = [chatUser(), chatUser()];
    $conversation = Conversation::between($a, $b);
    $b->update(['is_suspended' => true]);

    $this->withSession(chatSession($a))->postJson(route('chat.messages.store', $conversation), ['body' => 'hi'])->assertForbidden();
});

// ── 6. Retention ────────────────────────────────────────────────────────────

test('chat:prune erases messages past the retention period and the conversations they leave empty', function () {
    [$a, $b, $c] = [chatUser(), chatUser(), chatUser()];

    $stale = Conversation::between($a, $b);
    $old = chatSend($this, $a, $stale, 'long ago');

    $active = Conversation::between($a, $c);
    $oldInActive = chatSend($this, $a, $active, 'also long ago');
    $recent = chatSend($this, $c, $active, 'yesterday');

    DB::table('chat_messages')->whereIn('id', [$old->id, $oldInActive->id])->update(['created_at' => now()->subDays(366)]);
    DB::table('conversations')->update(['updated_at' => now()->subDays(2)]);

    $this->artisan('chat:prune')->assertSuccessful();

    expect(ChatMessage::pluck('id')->all())->toBe([$recent->id])
        ->and(Conversation::pluck('id')->all())->toBe([$active->id])
        ->and(AuditLog::where('action', 'chat.messages.pruned')->sole()->metadata)
            ->toMatchArray(['messages_deleted' => 2, 'conversations_deleted' => 1]);
});

test('the retention period cannot be configured below 30 days', function () {
    putenv('CHAT_RETENTION_DAYS=1');
    $config = require config_path('chat.php');
    putenv('CHAT_RETENTION_DAYS');

    expect($config['retention_days'])->toBe(30);
});

test('chat:prune is scheduled daily', function () {
    $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
        ->filter(fn ($event) => str_contains($event->command ?? '', 'chat:prune'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('30 2 * * *');
});

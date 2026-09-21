<?php

namespace App\Http\Controllers;

use App\Events\ChatActivity;
use App\Models\AuditLog;
use App\Models\ChatBlock;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Private one-to-one messaging for every role (POLICY.md §Private Messaging).
 *
 * The rules this controller holds, and which must survive later changes:
 *  - Only the two participants can read or write a conversation. Anyone else gets
 *    404, so a conversation's existence is not disclosed either.
 *  - Nothing can be sent until the user has acknowledged the privacy notice.
 *  - Message text is never written to the audit log, a notification or a broadcast.
 *  - People search returns name, coarse role and photo only — never email, EDP
 *    number, course or year level.
 */
class ChatController extends Controller
{
    private const NOT_ALLOWED = "You can't send messages in this conversation.";

    // ── Inbox ────────────────────────────────────────────────────────────────

    public function index(): View
    {
        $me = $this->me();

        $conversations = $me->hasAcknowledgedChatPrivacy()
            ? $this->inboxFor($me)
            : null;

        return view('chat.index', [
            'layout'        => role_layout($me->role),
            'me'            => $me,
            'conversations' => $conversations,
            'retentionDays' => (int) config('chat.retention_days'),
        ]);
    }

    public function acknowledge(): RedirectResponse
    {
        $me = $this->me();

        if (! $me->hasAcknowledgedChatPrivacy()) {
            $me->update(['chat_privacy_acknowledged_at' => now()]);
            AuditLog::record('chat.privacy.acknowledged', $me);
        }

        return redirect()->route('chat.index');
    }

    // ── People search + starting a conversation ─────────────────────────────

    public function search(Request $request): JsonResponse
    {
        $me = $this->me();
        $term = trim((string) $request->query('q', ''));

        if (! $me->hasAcknowledgedChatPrivacy() || mb_strlen($term) < (int) config('chat.search_min_length')) {
            return response()->json(['results' => []]);
        }

        // '!' rather than backslash as the LIKE escape: PDO's pre-8.4 SQL parser treats a
        // backslash inside quotes as escaping the quote.
        $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($term)) . '%';

        $results = User::query()
            ->whereKeyNot($me->id)
            ->where(fn ($q) => $q->where('is_suspended', false)->orWhereNull('is_suspended'))
            ->whereRaw("LOWER(first_name || ' ' || last_name) LIKE ? ESCAPE '!'", [$like])
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit((int) config('chat.search_limit'))
            ->get(['id', 'first_name', 'last_name', 'role', 'profile_photo_url'])
            ->map(fn (User $user) => [
                'id'         => $user->id,
                'name'       => $user->name,
                'role_label' => $user->chat_role_label,
                'photo_url'  => $user->profile_photo_url,
            ]);

        return response()->json(['results' => $results]);
    }

    public function start(Request $request): JsonResponse|RedirectResponse
    {
        $me = $this->me();

        if (! $me->hasAcknowledgedChatPrivacy()) {
            return $this->fail($request, 'Please read the privacy notice before starting a conversation.', 409, route('chat.index'));
        }

        $data = $request->validate(['recipient_id' => ['required', 'integer']]);
        $recipient = User::find($data['recipient_id']);

        if (! $recipient || $recipient->id === $me->id || $recipient->is_suspended) {
            return $this->fail($request, 'That person cannot be messaged.', 422, route('chat.index'));
        }

        $conversation = Conversation::between($me, $recipient);

        if ($conversation->wasRecentlyCreated) {
            AuditLog::record('chat.conversation.started', $conversation, ['with_user_id' => $recipient->id]);
        }

        $url = route('chat.show', $conversation);

        return $request->expectsJson()
            ? response()->json(['url' => $url])
            : redirect($url);
    }

    // ── A conversation ──────────────────────────────────────────────────────

    public function show(Conversation $conversation): View|RedirectResponse
    {
        $me = $this->me();
        $this->ensureParticipant($conversation, $me);

        if (! $me->hasAcknowledgedChatPrivacy()) {
            return redirect()->route('chat.index');
        }

        $participant = $conversation->participantFor($me->id);
        $other = $conversation->otherUser($me->id);

        // One extra row tells us whether older messages exist, without a second query.
        $page = $conversation->visibleMessagesFor($participant)
            ->reorder('id', 'desc')
            ->limit(51)
            ->get();
        $messages = $page->take(50)->reverse()->values();
        $blockers = ChatBlock::blockersBetween($me->id, $other->id);

        // Opening the thread reads it; tell this user's other tabs and devices.
        if ($participant->markReadThrough($conversation->last_message_id)) {
            ChatMessage::forgetUnreadCount($me->id);
            ChatActivity::read($me->id, $conversation->id);
        }

        return view('chat.show', [
            'layout'        => role_layout($me->role),
            'me'            => $me,
            'conversation'  => $conversation,
            'other'         => $other,
            'messages'      => $messages,
            'hasEarlier'    => $page->count() > 50,
            'blockedByMe'   => $blockers->contains($me->id),
            'canSend'       => ! $other->is_suspended && $blockers->isEmpty(),
            'retentionDays' => (int) config('chat.retention_days'),
        ]);
    }

    /**
     * JSON feed for the open conversation: `after` returns newer messages (and marks
     * them read), `before` pages back through history. `since_id` asks which of the
     * messages already on screen have been unsent, so polling also catches retractions.
     */
    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        $me = $this->me();
        $this->ensureParticipant($conversation, $me);

        if (! $me->hasAcknowledgedChatPrivacy()) {
            return response()->json(['message' => 'Please read the privacy notice first.'], 409);
        }

        $participant = $conversation->participantFor($me->id);
        $visible = fn () => $conversation->visibleMessagesFor($participant);

        if ($request->filled('before')) {
            $page = $visible()->where('id', '<', (int) $request->query('before'))
                ->reorder('id', 'desc')->limit(51)->get();
            $messages = $page->take(50)->reverse()->values();
            $hasEarlier = $page->count() > 50;
        } else {
            $messages = $visible()->where('id', '>', (int) $request->query('after', 0))->limit(100)->get();
            if ($participant->markReadThrough($messages->last()?->id)) {
                ChatMessage::forgetUnreadCount($me->id);
                ChatActivity::read($me->id, $conversation->id);
            }
            $hasEarlier = null;
        }

        // `new=1`: the browser is fetching because a new-message signal arrived. Unsends
        // and block changes send their own signals and the 15-second poll re-checks both,
        // so skip those three queries and get the message on screen sooner.
        $newMessageOnly = $request->boolean('new');

        $retractedIds = $request->filled('since_id') && ! $newMessageOnly
            ? $visible()->where('id', '>=', (int) $request->query('since_id'))
                ->whereNotNull('retracted_at')->pluck('id')
            : collect();

        return response()->json([
            'messages'      => $messages->map(fn (ChatMessage $m) => $m->toClientArray($me->id))->values(),
            'retracted_ids' => $retractedIds->values(),
            'has_earlier'   => $hasEarlier,
            'can_send'      => $newMessageOnly ? null : $this->canSend($me, $conversation->otherUser($me->id)),
            'unread'        => ChatMessage::unreadCountFor($me->id),
        ]);
    }

    public function send(Request $request, Conversation $conversation): JsonResponse|RedirectResponse
    {
        $me = $this->me();
        $this->ensureParticipant($conversation, $me);

        if (! $me->hasAcknowledgedChatPrivacy()) {
            return $this->fail($request, 'Please read the privacy notice before sending messages.', 409, route('chat.index'));
        }

        $data = $request->validate([
            'body' => ['required', 'string', 'max:' . (int) config('chat.max_length')],
        ], [
            'body.required' => 'Type a message first.',
            'body.max'      => 'Messages can be at most :max characters.',
        ]);

        $other = $conversation->otherUser($me->id);

        if (! $this->canSend($me, $other)) {
            return $this->fail($request, self::NOT_ALLOWED, 403, route('chat.show', $conversation));
        }

        $message = DB::transaction(function () use ($conversation, $me, $data) {
            $message = $conversation->messages()->create([
                'sender_id' => $me->id,
                'body'      => $data['body'],
            ]);

            $conversation->update([
                'last_message_id' => $message->id,
                'last_message_at' => $message->created_at,
            ]);

            // Your own message is read by definition. One UPDATE instead of a
            // select-then-update: ids only grow, so the new id is always the latest.
            ConversationParticipant::where('conversation_id', $conversation->id)
                ->where('user_id', $me->id)
                ->update(['last_read_message_id' => $message->id]);

            return $message;
        });

        // Signal first: the recipient's screen starts fetching the message while the
        // audit entry below is still being written.
        ChatActivity::notify([$other->id, $me->id], 'message', $message);

        // Who wrote to whom, and when — never what was said.
        AuditLog::record('chat.message.sent', $message, [
            'conversation_id' => $conversation->id,
            'recipient_id'    => $other->id,
        ]);

        return $request->expectsJson()
            ? response()->json(['message' => $message->toClientArray($me->id)], 201)
            : redirect()->route('chat.show', $conversation);
    }

    /** "Unsend": only the sender, at any time. The text is destroyed, not hidden. */
    public function retract(Request $request, Conversation $conversation, ChatMessage $message): JsonResponse|RedirectResponse
    {
        $me = $this->me();
        $this->ensureParticipant($conversation, $me);

        abort_unless($message->sender_id === $me->id, 403, 'You can only unsend your own messages.');

        if (! $message->isRetracted()) {
            $message->retract();

            ChatActivity::notify([$conversation->otherUserId($me->id), $me->id], 'retracted', $message);

            AuditLog::record('chat.message.retracted', $message, ['conversation_id' => $conversation->id]);
        }

        return $request->expectsJson()
            ? response()->json(['message' => $message->toClientArray($me->id)])
            : redirect()->route('chat.show', $conversation);
    }

    /**
     * "Delete conversation" — removes the history from this user's view only. Once
     * both people have deleted it, the messages are erased from the database.
     */
    public function clear(Conversation $conversation): RedirectResponse
    {
        $me = $this->me();
        $this->ensureParticipant($conversation, $me);

        $conversation->participantFor($me->id)->update([
            'cleared_through_message_id' => $conversation->last_message_id,
            'last_read_message_id'       => $conversation->last_message_id,
        ]);

        $purged = $conversation->purgeUnreachableMessages();

        AuditLog::record('chat.conversation.cleared', $conversation, ['messages_erased' => $purged]);

        return redirect()->route('chat.index')->with('success', 'Conversation deleted from your inbox.');
    }

    // ── Blocking ────────────────────────────────────────────────────────────

    public function block(User $user): RedirectResponse
    {
        $me = $this->me();
        abort_if($user->id === $me->id, 422);

        $block = ChatBlock::firstOrCreate(['blocker_id' => $me->id, 'blocked_id' => $user->id]);

        if ($block->wasRecentlyCreated) {
            $this->signalAccessChange($me, $user);
            AuditLog::record('chat.user.blocked', $block, ['blocked_user_id' => $user->id]);
        }

        return back()->with('success', "{$user->name} can no longer message you.");
    }

    public function unblock(User $user): RedirectResponse
    {
        $me = $this->me();

        $deleted = ChatBlock::where('blocker_id', $me->id)->where('blocked_id', $user->id)->delete();

        if ($deleted) {
            $this->signalAccessChange($me, $user);
            AuditLog::record('chat.user.unblocked', null, ['unblocked_user_id' => $user->id]);
        }

        return back()->with('success', "{$user->name} is unblocked.");
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /**
     * After a block or unblock, tell the other person's open conversation to re-check
     * whether it can send, so their message box updates at once instead of at the next
     * 15-second poll or a refused send. They already learn this from the composer, so
     * the signal discloses nothing new; it carries no reason and names no one.
     */
    private function signalAccessChange(User $me, User $other): void
    {
        [$low, $high] = $me->id < $other->id ? [$me->id, $other->id] : [$other->id, $me->id];
        $conversationId = Conversation::where('user_low_id', $low)->where('user_high_id', $high)->value('id');

        if ($conversationId) {
            ChatActivity::access($other->id, (int) $conversationId);
        }
    }

    private function me(): User
    {
        $me = auth()->user();

        abort_unless($me, 401);
        abort_if($me->is_suspended, 403, 'Your account has been suspended. Please contact the Director of Student Affairs for assistance.');

        return $me;
    }

    /** 404, not 403: a non-participant must not learn that the conversation exists. */
    private function ensureParticipant(Conversation $conversation, User $me): void
    {
        abort_unless($conversation->hasParticipant($me->id), 404);
    }

    private function canSend(User $me, ?User $other): bool
    {
        return $other !== null
            && ! $other->is_suspended
            && ! ChatBlock::existsBetween($me->id, $other->id);
    }

    private function inboxFor(User $me)
    {
        $uid = $me->id;

        $unread = ChatMessage::query()
            ->selectRaw('count(*)')
            ->whereColumn('chat_messages.conversation_id', 'conversations.id')
            ->where('chat_messages.sender_id', '!=', $uid)
            ->whereNull('chat_messages.retracted_at')
            ->whereRaw('chat_messages.id > COALESCE(me.last_read_message_id, 0)')
            ->whereRaw('chat_messages.id > COALESCE(me.cleared_through_message_id, 0)');

        $userColumns = 'id,first_name,last_name,role,profile_photo_url,is_suspended';

        return Conversation::query()
            ->join('conversation_participants as me', function ($join) use ($uid) {
                $join->on('me.conversation_id', '=', 'conversations.id')->where('me.user_id', '=', $uid);
            })
            ->whereNotNull('conversations.last_message_id')
            ->whereRaw('conversations.last_message_id > COALESCE(me.cleared_through_message_id, 0)')
            ->select('conversations.*')
            ->selectSub($unread, 'unread_count')
            ->with(["userLow:{$userColumns}", "userHigh:{$userColumns}", 'lastMessage'])
            ->orderByDesc('conversations.last_message_at')
            ->orderByDesc('conversations.id')
            ->simplePaginate(30);
    }

    private function fail(Request $request, string $message, int $status, string $redirectTo): JsonResponse|RedirectResponse
    {
        return $request->expectsJson()
            ? response()->json(['message' => $message], $status)
            : redirect($redirectTo)->withErrors(['chat' => $message]);
    }
}

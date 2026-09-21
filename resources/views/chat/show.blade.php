@extends($layout)

@section('title', 'Messages — ClubSync')
@section('page-title', 'Messages')
@section('club-name', 'ClubSync')

@section('content')
<div class="max-w-3xl mx-auto px-0 sm:px-4 sm:py-6">
    <div class="bg-white sm:rounded-2xl sm:shadow-sm sm:border border-gray-100 flex flex-col h-[calc(100dvh-8.5rem)] md:h-[calc(100dvh-9.5rem)] overflow-hidden"
         id="chat-thread"
         data-conversation-id="{{ $conversation->id }}"
         data-can-send="{{ $canSend ? 1 : 0 }}"
         data-messages-url="{{ route('chat.messages.index', $conversation) }}"
         data-send-url="{{ route('chat.messages.store', $conversation) }}"
         data-retract-url="{{ route('chat.messages.retract', [$conversation, '__ID__']) }}"
         data-index-url="{{ route('chat.index') }}">

        {{-- Header --}}
        <div class="flex items-center gap-3 px-3 sm:px-4 py-3 border-b border-gray-100">
            <a href="{{ route('chat.index') }}" class="p-1.5 rounded-lg text-gray-500 hover:bg-gray-100" aria-label="Back to messages">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 19.5 8.25 12l7.5-7.5"/>
                </svg>
            </a>
            <div class="rounded-full bg-[#07873e] flex-shrink-0">
                <x-avatar :user="$other" size="sm" />
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-gray-900 truncate">{{ $other->name }}</p>
                <p class="text-xs text-gray-400">{{ $other->chat_role_label }}</p>
            </div>

            <details class="relative">
                <summary class="list-none cursor-pointer p-2 rounded-lg text-gray-500 hover:bg-gray-100" aria-label="Conversation options">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                        <circle cx="12" cy="5" r="1.75"/><circle cx="12" cy="12" r="1.75"/><circle cx="12" cy="19" r="1.75"/>
                    </svg>
                </summary>
                <div class="absolute right-0 mt-1 w-56 bg-white border border-gray-200 rounded-xl shadow-lg z-20 py-1 text-sm">
                    @if($blockedByMe)
                        <form method="POST" action="{{ route('chat.people.unblock', $other) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="w-full text-left px-4 py-2.5 hover:bg-gray-50 text-gray-700">Unblock {{ $other->first_name }}</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('chat.people.block', $other) }}"
                              data-confirm="Block {{ $other->first_name }}? Neither of you will be able to send messages in this conversation until you unblock them.">
                            @csrf
                            <button type="submit" class="w-full text-left px-4 py-2.5 hover:bg-gray-50 text-gray-700">Block {{ $other->first_name }}</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('chat.clear', $conversation) }}"
                          data-confirm="Delete this conversation from your inbox? You won't be able to see these messages again. They are erased for good once {{ $other->first_name }} deletes it too.">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="w-full text-left px-4 py-2.5 hover:bg-red-50 text-red-600">Delete conversation</button>
                    </form>
                </div>
            </details>
        </div>

        @if(session('success'))
            <div class="bg-green-50 border-b border-green-100 text-green-800 text-sm px-4 py-2">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="bg-red-50 border-b border-red-100 text-red-800 text-sm px-4 py-2">{{ $errors->first() }}</div>
        @endif

        {{-- Messages --}}
        <div id="chat-scroller" class="flex-1 overflow-y-auto bg-gray-50 px-3 sm:px-4 py-4">
            <div class="text-center mb-4">
                <button type="button" id="chat-load-earlier"
                        class="{{ $hasEarlier ? '' : 'hidden' }} text-xs font-medium text-[#07873e] bg-white border border-gray-200 rounded-full px-3 py-1 hover:bg-gray-100">
                    Load earlier messages
                </button>
                <p class="text-[11px] text-gray-400 mt-3 flex items-center justify-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/>
                    </svg>
                    Only you and {{ $other->first_name }} can see this conversation. Messages are deleted after {{ $retentionDays }} days.
                </p>
            </div>

            <div id="chat-messages" class="space-y-3">
                @foreach($messages as $message)
                    @include('chat.partials.message', ['message' => $message, 'me' => $me])
                @endforeach
            </div>

            <p id="chat-empty" class="{{ $messages->isEmpty() ? '' : 'hidden' }} text-center text-sm text-gray-400 py-8">
                Say hello to {{ $other->first_name }}.
            </p>
        </div>

        {{-- Composer --}}
        <div class="border-t border-gray-100 px-3 sm:px-4 py-3 bg-white">
            <p id="chat-blocked" class="{{ $canSend ? 'hidden' : '' }} text-sm text-gray-500 text-center py-2">
                @if($blockedByMe)
                    You blocked {{ $other->first_name }}. Unblock them from the menu to send messages.
                @else
                    You can't send messages in this conversation.
                @endif
            </p>

            <form id="chat-form" method="POST" action="{{ route('chat.messages.store', $conversation) }}"
                  class="{{ $canSend ? '' : 'hidden' }}">
                @csrf
                <div class="flex items-end gap-2">
                    <label for="chat-body" class="sr-only">Message</label>
                    <textarea id="chat-body" name="body" rows="1" required maxlength="{{ config('chat.max_length') }}"
                              placeholder="Write a message…"
                              class="flex-1 resize-none max-h-40 rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#07873e]/40 focus:border-[#07873e]"></textarea>
                    <button type="submit" id="chat-send"
                            class="flex-shrink-0 h-10 px-4 rounded-xl bg-[#07873e] text-white text-sm font-semibold hover:bg-[#066f33] disabled:opacity-50 transition-colors">
                        Send
                    </button>
                </div>
                <p id="chat-error" class="hidden text-xs text-red-600 mt-1.5" role="alert"></p>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    const root = document.getElementById('chat-thread');
    if (!root) return;

    // Private pages must not be kept in Turbo's snapshot cache (Back after logout
    // would otherwise redisplay the conversation).
    if (!document.querySelector('meta[name="turbo-cache-control"]')) {
        const meta = document.createElement('meta');
        meta.name = 'turbo-cache-control';
        meta.content = 'no-cache';
        document.head.appendChild(meta);
    }

    // Turbo keeps one JS context across visits: tear down the previous thread's
    // timer and listeners before wiring this one.
    if (window.__csChatThread) window.__csChatThread.destroy();

    const conversationId = Number(root.dataset.conversationId);
    const myId = Number(document.querySelector('meta[name="cs-user-id"]')?.content) || 0;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const scroller = document.getElementById('chat-scroller');
    const list = document.getElementById('chat-messages');
    const empty = document.getElementById('chat-empty');
    const form = document.getElementById('chat-form');
    const textarea = document.getElementById('chat-body');
    const errorEl = document.getElementById('chat-error');
    const blockedEl = document.getElementById('chat-blocked');
    const earlierBtn = document.getElementById('chat-load-earlier');

    const ids = () => Array.from(list.querySelectorAll('[data-message-id]')).map((el) => Number(el.dataset.messageId));
    const lastId = () => Math.max(0, ...ids());
    const firstId = () => (ids().length ? Math.min(...ids()) : 0);
    const nearBottom = () => scroller.scrollHeight - scroller.scrollTop - scroller.clientHeight < 120;
    const toBottom = () => { scroller.scrollTop = scroller.scrollHeight; };

    // Mirrors chat/partials/message.blade.php. Text goes in via textContent only.
    function renderMessage(m) {
        const row = document.createElement('div');
        row.className = 'flex ' + (m.mine ? 'justify-end' : 'justify-start');
        row.dataset.messageId = m.id;
        row.dataset.mine = m.mine ? '1' : '0';

        const col = document.createElement('div');
        col.className = 'max-w-[80%] min-w-0';

        const bubble = document.createElement('div');
        if (m.retracted) {
            bubble.className = 'rounded-2xl px-3.5 py-2 text-sm italic text-gray-400 border border-dashed border-gray-300';
            bubble.textContent = 'Message deleted';
        } else {
            bubble.className = 'rounded-2xl px-3.5 py-2 text-sm whitespace-pre-wrap break-words ' + (m.mine
                ? 'bg-[#07873e] text-white rounded-br-md'
                : 'bg-white border border-gray-200 text-gray-800 rounded-bl-md');
            bubble.textContent = m.body;
        }

        const meta = document.createElement('div');
        meta.className = 'flex items-center gap-2 mt-1 px-1 text-[11px] text-gray-400' + (m.mine ? ' justify-end' : '');
        const time = document.createElement('time');
        if (m.sent_at) time.dateTime = m.sent_at;
        time.textContent = m.time || '';
        meta.appendChild(time);

        if (m.mine && !m.retracted) {
            const f = document.createElement('form');
            f.method = 'POST';
            f.action = root.dataset.retractUrl.replace('__ID__', m.id);
            f.dataset.chatRetract = '';
            const btn = document.createElement('button');
            btn.type = 'submit';
            btn.className = 'hover:text-red-600 hover:underline';
            btn.textContent = 'Unsend';
            f.appendChild(btn);
            meta.appendChild(f);
        }

        col.append(bubble, meta);
        row.appendChild(col);
        return row;
    }

    function upsert(messages, { prepend = false } = {}) {
        const fragment = document.createDocumentFragment();
        messages.forEach((m) => {
            const existing = list.querySelector(`[data-message-id="${m.id}"]`);
            if (existing) { existing.replaceWith(renderMessage(m)); return; }
            fragment.appendChild(renderMessage(m));
        });
        if (prepend) list.prepend(fragment);
        else list.insertBefore(fragment, list.querySelector('[data-temp-id]')); // above bubbles still sending
        empty.classList.toggle('hidden', list.children.length > 0);
    }

    function markRetracted(id) {
        const el = list.querySelector(`[data-message-id="${id}"]`);
        if (!el) return;
        const time = el.querySelector('time');
        el.replaceWith(renderMessage({
            id, mine: el.dataset.mine === '1', retracted: true,
            sent_at: time?.dateTime, time: time?.textContent,
        }));
    }

    function setBadge(count) {
        const n = Number(count) || 0;
        document.querySelectorAll('.js-chat-badge').forEach((el) => {
            el.textContent = n > 9 ? '9+' : String(n);
            el.style.display = n > 0 ? '' : 'none';
        });
    }

    function setCanSend(canSend) {
        if (canSend === undefined || canSend === null) return;
        form.classList.toggle('hidden', !canSend);
        blockedEl.classList.toggle('hidden', canSend);
    }

    function showError(text) {
        errorEl.textContent = text;
        errorEl.classList.toggle('hidden', !text);
    }

    async function getJson(query) {
        const res = await fetch(root.dataset.messagesUrl + '?' + new URLSearchParams(query), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (res.status === 404) { // conversation erased (e.g. both sides deleted it)
            window.Turbo ? window.Turbo.visit(root.dataset.indexUrl) : (window.location.href = root.dataset.indexUrl);
            return null;
        }
        return res.ok ? res.json() : null;
    }

    // A signal that arrives while a fetch is already running is not dropped: that fetch
    // may have started before the new message was saved, so run once more afterwards.
    // mode 'new': fetching because a new-message signal arrived, so the server can skip
    // the unsend and can-send checks (those have their own signals and the 15-second poll).
    let polling = false;
    let pollAgain = null; // null | 'new' | 'full'
    async function poll(mode = 'full') {
        if (!document.body.contains(root)) return;
        if (polling) { pollAgain = (pollAgain === 'full' || mode === 'full') ? 'full' : 'new'; return; }
        polling = true;
        try {
            const stick = nearBottom();
            const query = { after: lastId(), since_id: firstId() };
            if (mode === 'new') query.new = 1;
            const data = await getJson(query);
            if (!data) return;
            if (data.messages.length) { upsert(data.messages); if (stick) toBottom(); }
            data.retracted_ids.forEach(markRetracted);
            setCanSend(data.can_send);
            setBadge(data.unread);
        } catch { /* offline — the connection banner already tells the user */ }
        finally {
            polling = false;
            if (pollAgain) { const next = pollAgain; pollAgain = null; poll(next); }
        }
    }

    // Sending. The box clears as soon as a message is queued, so the next one can be
    // typed straight away; queued messages go out one at a time, in order, each shown
    // at once as a "Sending…" bubble. If a send fails, the unsent text is put back.
    const outbox = [];
    let sending = false;
    let tempSeq = 0;

    function renderPending(tempId, body) {
        const row = document.createElement('div');
        row.className = 'flex justify-end';
        row.dataset.tempId = tempId;
        const col = document.createElement('div');
        col.className = 'max-w-[80%] min-w-0';
        const bubble = document.createElement('div');
        bubble.className = 'rounded-2xl px-3.5 py-2 text-sm whitespace-pre-wrap break-words bg-[#07873e] text-white rounded-br-md opacity-70';
        bubble.textContent = body;
        const meta = document.createElement('div');
        meta.className = 'flex items-center gap-2 mt-1 px-1 text-[11px] text-gray-400 justify-end';
        meta.textContent = 'Sending…';
        col.append(bubble, meta);
        row.appendChild(col);
        return row;
    }

    function sendError(status, data) {
        if (status === 422) return data.errors?.body?.[0] || data.message || 'That message could not be sent.';
        if (status === 403) return data.message || "You can't send messages in this conversation.";
        if (status === 429) return "You're sending messages too quickly. Please wait a moment.";
        if (status === 419 || status === 401) return 'Your session has expired. Refresh the page and log in again.';
        return data.message || 'Your message was not sent. Please try again.';
    }

    // Put every unsent message back in the box, ahead of anything typed since.
    function returnUnsent(message) {
        const texts = outbox.splice(0).map((item) => {
            list.querySelector(`[data-temp-id="${item.tempId}"]`)?.remove();
            return item.body;
        });
        const typed = textarea.value.trim();
        textarea.value = texts.concat(typed ? [typed] : []).join('\n\n');
        autosize();
        empty.classList.toggle('hidden', list.children.length > 0);
        showError(message);
    }

    async function flush() {
        if (sending) return;
        sending = true;
        try {
            while (outbox.length) {
                const { tempId, body } = outbox[0];
                let res;
                let data = {};
                try {
                    res = await fetch(root.dataset.sendUrl, {
                        method: 'POST',
                        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                        credentials: 'same-origin',
                        body: JSON.stringify({ body }),
                    });
                    data = await res.json().catch(() => ({}));
                } catch {
                    returnUnsent('No connection. Your message was not sent — try again when you are back online.');
                    return;
                }

                if (res.status !== 201) {
                    if (res.status === 403) setCanSend(false);
                    returnUnsent(sendError(res.status, data));
                    return;
                }

                outbox.shift();
                const stick = nearBottom();
                const pending = list.querySelector(`[data-temp-id="${tempId}"]`);
                if (list.querySelector(`[data-message-id="${data.message.id}"]`)) pending?.remove(); // a fetch already showed it
                else if (pending) pending.replaceWith(renderMessage(data.message));
                else upsert([data.message]);
                if (stick) toBottom();
            }
        } finally {
            sending = false;
        }
    }

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const body = textarea.value.trim();
        if (!body) { showError('Type a message first.'); return; }

        showError('');
        const tempId = 'tmp-' + (++tempSeq);
        list.appendChild(renderPending(tempId, body));
        empty.classList.add('hidden');
        outbox.push({ tempId, body });

        textarea.value = '';
        autosize();
        toBottom();
        textarea.focus();
        flush();
    });

    // Enter sends; Shift+Enter adds a line.
    textarea.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
            e.preventDefault();
            form.requestSubmit();
        }
    });

    function autosize() {
        textarea.style.height = 'auto';
        textarea.style.height = Math.min(textarea.scrollHeight, 160) + 'px';
    }
    textarea.addEventListener('input', autosize);

    // Unsend (delegated — covers server-rendered and live-rendered bubbles).
    list.addEventListener('submit', async (e) => {
        const f = e.target.closest('form[data-chat-retract]');
        if (!f) return;
        e.preventDefault();
        if (!confirm('Unsend this message? Its text will be erased for both of you.')) return;

        try {
            const res = await fetch(f.action, {
                method: 'DELETE',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                credentials: 'same-origin',
            });
            const data = await res.json().catch(() => ({}));
            if (res.ok && data.message) upsert([data.message]);
            else alert(data.message || 'That message could not be unsent. Please try again.');
        } catch {
            alert('No connection. The message was not unsent.');
        }
    });

    // Confirmations for block / delete conversation.
    root.addEventListener('submit', (e) => {
        const f = e.target.closest('form[data-confirm]');
        if (f && !confirm(f.dataset.confirm)) e.preventDefault();
    });

    earlierBtn.addEventListener('click', async () => {
        earlierBtn.disabled = true;
        try {
            const data = await getJson({ before: firstId() });
            if (!data) return;
            const previousHeight = scroller.scrollHeight;
            upsert(data.messages, { prepend: true });
            scroller.scrollTop += scroller.scrollHeight - previousHeight; // keep the reader's place
            earlierBtn.classList.toggle('hidden', !data.has_earlier);
        } catch { /* offline */ }
        finally { earlierBtn.disabled = false; }
    });

    // Real-time: app.js re-dispatches Reverb chat signals as a DOM event. The signal
    // has no message text; this fetches it. Polling covers a Reverb outage.
    function onActivity(e) {
        const d = e.detail || {};
        if (Number(d.conversation_id) !== conversationId || d.type === 'read') return;
        // This tab's own send: the response renders it, so skip the extra fetch.
        if (d.type === 'message' && Number(d.sender_id) === myId && (sending || outbox.length)) return;
        poll(d.type === 'message' ? 'new' : 'full');
    }
    const fullPoll = () => poll('full');
    document.addEventListener('cs:chat-activity', onActivity);
    // After a reconnect or on returning to the tab, pick up anything missed.
    document.addEventListener('cs:live-resync', fullPoll);
    const timer = setInterval(fullPoll, 15000);

    function destroy() {
        clearInterval(timer);
        document.removeEventListener('cs:chat-activity', onActivity);
        document.removeEventListener('cs:live-resync', fullPoll);
        document.removeEventListener('turbo:before-render', destroy);
        if (window.__csChatThread?.destroy === destroy) window.__csChatThread = null;
    }
    document.addEventListener('turbo:before-render', destroy);
    window.__csChatThread = { conversationId, destroy };

    toBottom();
})();
</script>
@endsection

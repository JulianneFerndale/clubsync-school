@extends($layout)

@section('title', 'Messages — ClubSync')
@section('page-title', 'Messages')
@section('club-name', 'ClubSync')

@section('content')
<div id="chat-inbox" class="max-w-3xl mx-auto px-4 py-6 space-y-4">

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 text-sm rounded-xl px-4 py-3">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-800 text-sm rounded-xl px-4 py-3">{{ $errors->first() }}</div>
    @endif

    @if($conversations === null)
        {{-- First use: the notice must be read before chat opens (RA 10173 transparency). --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="bg-[#07873e] px-6 py-5 text-white">
                <div class="flex items-center gap-3">
                    <svg class="w-7 h-7 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/>
                    </svg>
                    <div>
                        <h2 class="text-lg font-bold leading-tight">Private messages and your privacy</h2>
                        <p class="text-white/80 text-sm">Please read this before you start chatting.</p>
                    </div>
                </div>
            </div>
            <div class="px-6 py-5">
                @include('chat.partials.privacy-notice')
            </div>
            <form method="POST" action="{{ route('chat.privacy.acknowledge') }}" class="px-6 pb-6">
                @csrf
                <button type="submit" class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-[#07873e] text-white text-sm font-semibold hover:bg-[#066f33] transition-colors">
                    I understand, open Messages
                </button>
            </form>
        </div>
    @else
        {{-- Start a conversation --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
            <label for="chat-people-search" class="block text-sm font-semibold text-gray-800 mb-2">New message</label>
            <div class="relative">
                <input id="chat-people-search" type="search" autocomplete="off" maxlength="80"
                       placeholder="Search by name…"
                       data-search-url="{{ route('chat.people.search') }}"
                       data-start-url="{{ route('chat.start') }}"
                       class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#07873e]/40 focus:border-[#07873e]">
                <div id="chat-people-results" class="hidden absolute left-0 right-0 mt-1 bg-white border border-gray-200 rounded-xl shadow-lg z-20 overflow-hidden"></div>
            </div>
            <p class="mt-2 text-xs text-gray-400">Type at least {{ config('chat.search_min_length') }} letters of a name.</p>
        </div>

        {{-- Inbox --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            @forelse($conversations as $conversation)
                @php
                    $other   = $conversation->otherUser($me->id);
                    $last    = $conversation->lastMessage;
                    $unread  = (int) $conversation->unread_count;
                    $preview = ! $last ? '' : ($last->isRetracted() ? 'Message deleted' : \Illuminate\Support\Str::limit($last->body, 80));
                @endphp
                <a href="{{ route('chat.show', $conversation) }}"
                   class="flex items-center gap-3 px-4 py-3 border-b border-gray-100 last:border-b-0 hover:bg-gray-50 transition-colors">
                    <div class="rounded-full bg-[#07873e] flex-shrink-0">
                        <x-avatar :user="$other" size="md" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-baseline justify-between gap-2">
                            <p class="text-sm truncate {{ $unread ? 'font-bold text-gray-900' : 'font-semibold text-gray-800' }}">{{ $other?->name }}</p>
                            <span class="text-[11px] text-gray-400 flex-shrink-0">{{ $conversation->last_message_at?->diffForHumans(short: true) }}</span>
                        </div>
                        <p class="text-xs text-gray-400">{{ $other?->chat_role_label }}</p>
                        <div class="flex items-center justify-between gap-2 mt-0.5">
                            <p class="text-sm truncate {{ $unread ? 'text-gray-900' : 'text-gray-500' }} {{ $last?->isRetracted() ? 'italic' : '' }}">
                                @if($last && $last->sender_id === $me->id)<span class="text-gray-400">You: </span>@endif{{ $preview }}
                            </p>
                            @if($unread)
                                <span class="flex-shrink-0 min-w-5 h-5 px-1.5 rounded-full bg-[#F9A825] text-white text-[11px] font-bold flex items-center justify-center">{{ $unread > 99 ? '99+' : $unread }}</span>
                            @endif
                        </div>
                    </div>
                </a>
            @empty
                <div class="px-6 py-12 text-center">
                    <svg class="w-10 h-10 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z"/>
                    </svg>
                    <p class="text-sm font-semibold text-gray-700">No conversations yet</p>
                    <p class="text-sm text-gray-400 mt-1">Search for someone above to send your first message.</p>
                </div>
            @endforelse
        </div>

        @if($conversations->hasPages())
            <div class="flex justify-between text-sm">
                @if($conversations->previousPageUrl())
                    <a href="{{ $conversations->previousPageUrl() }}" class="text-[#07873e] font-medium hover:underline">← Newer</a>
                @else
                    <span></span>
                @endif
                @if($conversations->nextPageUrl())
                    <a href="{{ $conversations->nextPageUrl() }}" class="text-[#07873e] font-medium hover:underline">Older →</a>
                @endif
            </div>
        @endif

        <details class="bg-white rounded-2xl shadow-sm border border-gray-100 px-4 py-3 group">
            <summary class="cursor-pointer text-sm font-medium text-gray-600 flex items-center gap-2 list-none">
                <svg class="w-4 h-4 text-[#07873e]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/>
                </svg>
                How your messages are protected
            </summary>
            <div class="pt-4">
                @include('chat.partials.privacy-notice')
            </div>
        </details>
    @endif
</div>

<script>
(function () {
    // Private pages must not be kept in Turbo's snapshot cache (Back after logout
    // would otherwise redisplay them).
    if (!document.querySelector('meta[name="turbo-cache-control"]')) {
        const meta = document.createElement('meta');
        meta.name = 'turbo-cache-control';
        meta.content = 'no-cache';
        document.head.appendChild(meta);
    }

    const input = document.getElementById('chat-people-search');
    const box = document.getElementById('chat-people-results');
    if (!input || !box) return;

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const minLength = {{ (int) config('chat.search_min_length') }};
    let timer = null;
    let seq = 0;

    function hide() { box.classList.add('hidden'); box.replaceChildren(); }

    function note(text) {
        const p = document.createElement('p');
        p.className = 'px-4 py-3 text-sm text-gray-500';
        p.textContent = text;
        box.replaceChildren(p);
        box.classList.remove('hidden');
    }

    function render(results) {
        if (!results.length) { note('No one found with that name.'); return; }

        box.replaceChildren(...results.map((person) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'w-full flex items-center gap-3 px-4 py-2.5 text-left hover:bg-gray-50';

            const avatar = document.createElement('span');
            avatar.className = 'w-9 h-9 rounded-full bg-[#07873e] text-white text-sm font-semibold flex items-center justify-center flex-shrink-0 overflow-hidden';
            if (person.photo_url) {
                const img = document.createElement('img');
                img.src = person.photo_url;
                img.alt = '';
                img.className = 'w-full h-full object-cover';
                avatar.appendChild(img);
            } else {
                avatar.textContent = (person.name || '?').charAt(0).toUpperCase();
            }

            const text = document.createElement('span');
            text.className = 'min-w-0';
            const name = document.createElement('span');
            name.className = 'block text-sm font-medium text-gray-900 truncate';
            name.textContent = person.name;
            const role = document.createElement('span');
            role.className = 'block text-xs text-gray-400';
            role.textContent = person.role_label;
            text.append(name, role);

            btn.append(avatar, text);
            btn.addEventListener('click', () => start(person.id, btn));
            return btn;
        }));
        box.classList.remove('hidden');
    }

    async function search(term) {
        const mine = ++seq;
        try {
            const res = await fetch(input.dataset.searchUrl + '?q=' + encodeURIComponent(term), {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (mine !== seq) return; // a newer keystroke already fired
            if (res.status === 429) { note('Too many searches. Please wait a moment.'); return; }
            if (!res.ok) { note('Search is unavailable right now.'); return; }
            render((await res.json()).results || []);
        } catch {
            if (mine === seq) note('No connection. Check your network and try again.');
        }
    }

    async function start(recipientId, btn) {
        btn.disabled = true;
        try {
            const res = await fetch(input.dataset.startUrl, {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                credentials: 'same-origin',
                body: JSON.stringify({ recipient_id: recipientId }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.url) { note(data.message || 'Could not open that conversation.'); return; }
            window.Turbo ? window.Turbo.visit(data.url) : (window.location.href = data.url);
        } catch {
            note('No connection. Check your network and try again.');
        } finally {
            btn.disabled = false;
        }
    }

    input.addEventListener('input', () => {
        clearTimeout(timer);
        const term = input.value.trim();
        if (term === '' && refreshPending) { refreshInbox(); return; } // catch up on what arrived while searching
        if (term.length < minLength) { seq++; hide(); return; }
        timer = setTimeout(() => search(term), 250);
    });

    input.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') { input.value = ''; hide(); if (refreshPending) refreshInbox(); }
    });

    document.addEventListener('click', function outside(e) {
        if (!document.body.contains(input)) { document.removeEventListener('click', outside); return; }
        if (!box.contains(e.target) && e.target !== input) hide();
    });

    // Refresh the inbox when a message arrives or is read elsewhere. While a search is
    // being typed the refresh waits, and runs as soon as the search box is cleared.
    let refreshPending = false;
    let refreshTimer = null;
    let leaving = false;

    // Once the user opens a conversation (or submits anything), never refresh the inbox:
    // opening a thread sends this user a "read" signal while the thread is still loading,
    // and refreshing then would cancel that navigation and land back on the inbox.
    function stopRefreshing() {
        leaving = true;
        clearTimeout(refreshTimer);
        document.removeEventListener('turbo:before-visit', stopRefreshing);
        document.removeEventListener('turbo:submit-start', stopRefreshing);
    }
    document.addEventListener('turbo:before-visit', stopRefreshing);
    document.addEventListener('turbo:submit-start', stopRefreshing);

    function refreshInbox() {
        refreshPending = false;
        clearTimeout(refreshTimer);
        // Several signals in a burst (e.g. rapid messages) cause one refresh, not several.
        refreshTimer = setTimeout(() => {
            if (leaving || !document.body.contains(input)) return;
            window.Turbo ? window.Turbo.visit(window.location.href, { action: 'replace' }) : window.location.reload();
        }, 250);
    }

    function onActivity() {
        if (leaving) return;
        if (!document.body.contains(input)) {
            document.removeEventListener('cs:chat-activity', onActivity);
            document.removeEventListener('cs:live-resync', onActivity);
            return;
        }
        if (input.value.trim() === '') refreshInbox();
        else refreshPending = true;
    }
    document.addEventListener('cs:chat-activity', onActivity);
    document.addEventListener('cs:live-resync', onActivity);
})();
</script>
@endsection

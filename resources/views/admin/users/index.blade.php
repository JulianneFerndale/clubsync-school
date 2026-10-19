@extends('layouts.app-admin')
@section('title', 'Manage Users — Admin')
@section('page-title', 'Manage Users')

@php
    $staffBadge = [
        'dsa'     => 'bg-purple-50 text-purple-600 border-purple-200',
        'vpa'     => 'bg-teal-50 text-teal-700 border-teal-200',
        'adviser' => 'bg-blue-50 text-blue-600 border-blue-200',
        'member'  => 'bg-gray-50 text-gray-600 border-gray-200',
    ];
    $officerBadge = 'bg-amber-50 text-amber-600 border-amber-200';
    $field = 'border border-gray-300 rounded-lg text-xs px-2.5 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-[#07873e]/30';
@endphp

@section('content')

<div class="bg-[#07873e] px-5 pt-5 pb-6">
    <h1 class="text-white font-bold text-xl">Manage Users</h1>
    <p class="text-white/60 text-xs mt-1">Change a user's role, and confirm which club each adviser advises. Every change is recorded in the audit log.</p>
</div>

<div class="px-4 py-5 space-y-4 max-w-5xl">

    {{-- Advisers who signed up and are waiting for their club --}}
    @if($requestCount > 0 && $filter !== 'club-requests')
        <a href="{{ route('admin.users.index', ['filter' => 'club-requests']) }}"
           class="flex items-center justify-between gap-3 bg-[#F9A825]/10 border border-[#F9A825]/40 rounded-xl px-4 py-3 hover:bg-[#F9A825]/20 transition-colors">
            <span class="text-sm text-gray-800">
                <span class="font-semibold">{{ $requestCount }} {{ \Illuminate\Support\Str::plural('adviser', $requestCount) }}</span>
                {{ $requestCount === 1 ? 'is' : 'are' }} waiting for you to confirm the club they asked to advise.
            </span>
            <span class="text-xs font-semibold text-[#07873e] flex-shrink-0">Review</span>
        </a>
    @endif

    {{-- Search --}}
    <form method="GET" action="{{ route('admin.users.index') }}" class="bg-white rounded-xl border border-gray-100 shadow-sm p-3">
        @if($filter)
            <input type="hidden" name="filter" value="{{ $filter }}">
        @endif
        <div class="flex items-center gap-2">
            <input type="text" name="search" value="{{ $search }}" placeholder="Search by name or email…"
                   class="flex-1 min-w-0 border border-gray-300 rounded-lg text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#07873e]/30">
            <button type="submit" class="bg-[#07873e] text-white text-xs font-semibold rounded-lg px-4 py-2 hover:opacity-90 transition-opacity">
                Search
            </button>
            @if($search || $filter)
                <a href="{{ route('admin.users.index') }}" class="text-xs text-gray-400 font-medium hover:text-gray-600">Clear</a>
            @endif
        </div>
        @if($filter === 'club-requests')
            <p class="text-xs text-gray-500 mt-2">Showing advisers waiting for their club to be confirmed.</p>
        @endif
    </form>

    {{-- User list --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm divide-y divide-gray-50">
        @forelse($users as $user)
            @php
                $isOfficer   = \App\Models\User::isOfficerRole($user->role);
                $memberships = $user->clubMemberships->filter(fn ($m) => $m->club)->sortBy(fn ($m) => $m->club->name)->values();
                $post        = $user->officerPosts->first();
                $canStudent  = ! $user->isEmployeeAccount();
                $canAdviser  = ! $user->isStudentAccount();
            @endphp
            <div class="px-4 py-4 space-y-3">
                <div class="flex flex-col sm:flex-row sm:items-start gap-2">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-gray-800 truncate">{{ $user->name }}</p>
                        <p class="text-xs text-gray-500 truncate">{{ $user->email }}</p>
                        @if($user->department || $user->course)
                            <p class="text-[11px] text-gray-400 mt-0.5">{{ $user->department?->short_name }}{{ $user->department && $user->course ? ' · ' : '' }}{{ $user->course?->name }}</p>
                        @endif
                        @if($user->employee_number)
                            <p class="text-[11px] text-gray-400 mt-0.5">Adviser no. {{ $user->employee_number }}</p>
                        @endif

                        {{-- Where this person stands in the clubs --}}
                        @if($user->role === 'adviser')
                            <p class="text-xs mt-1 {{ $user->advisedClub ? 'text-gray-600' : 'text-gray-400' }}">
                                {{ $user->advisedClub ? 'Adviser of ' . $user->advisedClub->name : 'Not advising a club yet' }}
                            </p>
                            @if($user->requestedClub)
                                <p class="text-xs mt-0.5 text-[#b7791f] font-medium">Asked to advise {{ $user->requestedClub->name }}</p>
                            @endif
                        @elseif($memberships->isNotEmpty())
                            <p class="text-xs text-gray-600 mt-1">
                                @if($post)
                                    <span class="font-semibold">{{ \App\Models\User::roleLabel($post->position) }}</span> of {{ $post->club?->name }}.
                                @endif
                                Member of {{ $memberships->map(fn ($m) => $m->club->acronym ?: $m->club->name)->join(', ', ' and ') }}
                            </p>
                        @elseif(in_array($user->role, \App\Models\User::STUDENT_ROLES, true))
                            <p class="text-xs text-gray-400 mt-1">Not an active member of any club</p>
                        @endif
                    </div>

                    <span class="inline-flex items-center justify-center px-2.5 py-0.5 rounded-full text-xs font-semibold border w-fit {{ $isOfficer ? $officerBadge : ($staffBadge[$user->role] ?? $staffBadge['member']) }}">
                        {{ \App\Models\User::roleLabel($user->role) }}
                    </span>
                </div>

                @if($user->id === auth_user_id())
                    <p class="text-xs text-gray-400 italic">(your account)</p>
                @else
                    {{-- Role, and for an officer position the club it is held in --}}
                    <form method="POST" action="{{ route('admin.users.role.update', $user) }}"
                          class="flex flex-wrap items-center gap-2" data-role-form
                          data-user-id="{{ $user->id }}" data-user-name="{{ $user->name }}">
                        @csrf
                        @method('PATCH')
                        <select name="role" class="{{ $field }}" data-role-select aria-label="Role for {{ $user->name }}">
                            @if($canStudent)
                                <optgroup label="Student">
                                    <option value="member" @selected($user->role === 'member')>Member</option>
                                </optgroup>
                                <optgroup label="Club officer{{ $memberships->isEmpty() ? ' (needs a club membership)' : '' }}">
                                    @foreach($positions as $value => $label)
                                        <option value="{{ $value }}" @selected($user->role === $value) @disabled($memberships->isEmpty())>{{ $label }}</option>
                                    @endforeach
                                </optgroup>
                            @endif
                            <optgroup label="Staff">
                                @if($canAdviser)
                                    <option value="adviser" @selected($user->role === 'adviser')>Adviser</option>
                                @endif
                                <option value="dsa" @selected($user->role === 'dsa')>DSA</option>
                                <option value="vpa" @selected($user->role === 'vpa')>VPA</option>
                            </optgroup>
                            @if(! in_array($user->role, ['member', 'adviser', 'dsa', 'vpa', ...array_keys($positions)], true))
                                <option value="" selected disabled>{{ \App\Models\User::roleLabel($user->role) }} (pick a new role)</option>
                            @endif
                        </select>

                        @if($canStudent && $memberships->isNotEmpty())
                            <select name="club_id" class="{{ $field }} {{ $isOfficer ? '' : 'hidden' }}" data-club-select aria-label="Club for the position" @disabled(! $isOfficer)>
                                <option value="">In which club?</option>
                                @foreach($memberships as $membership)
                                    <option value="{{ $membership->club_id }}" @selected($post?->club_id === $membership->club_id)>{{ $membership->club->name }}</option>
                                @endforeach
                            </select>
                        @endif

                        <button type="submit" class="bg-[#07873e] text-white text-xs font-semibold rounded-lg px-3 py-2 hover:opacity-90 transition-opacity flex-shrink-0">
                            Update role
                        </button>
                    </form>

                    {{-- The club an adviser advises: one adviser per club --}}
                    @if($user->role === 'adviser')
                        <div class="flex flex-wrap items-center gap-2">
                            <form method="POST" action="{{ route('admin.users.club.update', $user) }}"
                                  class="flex flex-wrap items-center gap-2" data-club-form data-user-name="{{ $user->name }}">
                                @csrf
                                @method('PATCH')
                                @php $selectedClub = $user->advisedClub?->id ?? $user->requestedClub?->id; @endphp
                                <select name="club_id" class="{{ $field }}" aria-label="Club {{ $user->name }} advises">
                                    <option value="">No club</option>
                                    @if($user->advisedClub)
                                        <option value="{{ $user->advisedClub->id }}" @selected($selectedClub === $user->advisedClub->id)>{{ $user->advisedClub->name }} (current)</option>
                                    @endif
                                    @if($user->requestedClub && $user->requestedClub->adviser_id && $user->requestedClub->id !== $user->advisedClub?->id)
                                        <option value="" disabled>{{ $user->requestedClub->name }} (has an adviser now)</option>
                                    @endif
                                    @foreach($openClubs as $open)
                                        <option value="{{ $open->id }}" @selected($selectedClub === $open->id && ! $user->advisedClub)>
                                            {{ $open->name }}{{ $user->requested_club_id === $open->id ? ' (requested)' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                <button type="submit" class="border border-[#07873e] text-[#07873e] text-xs font-semibold rounded-lg px-3 py-2 hover:bg-[#07873e]/5 transition-colors flex-shrink-0">
                                    {{ $user->requestedClub && ! $user->advisedClub ? 'Confirm club' : 'Save club' }}
                                </button>
                            </form>

                            @if($user->requestedClub)
                                <form method="POST" action="{{ route('admin.users.club-request.destroy', $user) }}" data-confirm-message="Decline {{ $user->name }}'s request to advise {{ $user->requestedClub->name }}?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs font-semibold text-red-500 hover:text-red-700 px-2 py-2">Decline request</button>
                                </form>
                            @endif
                        </div>
                        @if($openClubs->isEmpty() && ! $user->advisedClub)
                            <p class="text-[11px] text-gray-400">Every active club has an adviser. Take an adviser off a club first to free it.</p>
                        @endif
                    @endif
                @endif
            </div>
        @empty
            <p class="text-gray-400 text-sm text-center py-6">No users found.</p>
        @endforelse
    </div>

    @if($users->hasPages())
        <div class="pt-2">{{ $users->links() }}</div>
    @endif

</div>

{{-- Who holds each post in the clubs on this page, so the confirmation can say who an appointment replaces. --}}
<script type="application/json" id="post-holders">@json($holders)</script>

<script>
(function () {
    const holders = JSON.parse(document.getElementById('post-holders')?.textContent || '{}');

    document.querySelectorAll('[data-role-form]').forEach(function (form) {
        const role = form.querySelector('[data-role-select]');
        const club = form.querySelector('[data-club-select]');
        const isPosition = () => role.selectedOptions[0]?.parentElement?.label?.startsWith('Club officer');

        // The club picker is there only for an officer position, which is always held in one club.
        const sync = () => {
            if (!club) return;
            club.classList.toggle('hidden', !isPosition());
            club.disabled = !isPosition();
            club.required = isPosition();
        };
        role.addEventListener('change', sync);
        sync();

        form.addEventListener('submit', function (e) {
            const name = form.dataset.userName;
            const label = role.selectedOptions[0]?.textContent.trim();
            let message = 'Change ' + name + "'s role to " + label + '?';

            if (isPosition() && club) {
                const clubName = club.selectedOptions[0]?.textContent.trim();
                const holder = (holders[club.value] || {})[role.value];
                message = 'Make ' + name + ' ' + label + ' of ' + clubName + '?';
                if (holder && String(holder.id) !== form.dataset.userId) {
                    message += '\n\n' + holder.name + ' is ' + label + ' there now and will become a Member of the club.';
                }
                message += '\n\nA student holds one position at a time, so any position they hold in another club is given up.';
            }

            if (!confirm(message + '\n\nThis is recorded in the audit log.')) e.preventDefault();
        });
    });

    document.querySelectorAll('[data-club-form]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            const select = form.querySelector('select');
            const clubName = select.selectedOptions[0]?.textContent.replace(/\s*\((current|requested)\)\s*$/, '').trim();
            const message = select.value
                ? 'Make ' + form.dataset.userName + ' the adviser of ' + clubName + '? A club has one adviser.'
                : 'Take ' + form.dataset.userName + ' off their club? The club will have no adviser.';
            if (!confirm(message)) e.preventDefault();
        });
    });

    document.querySelectorAll('[data-confirm-message]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (!confirm(form.dataset.confirmMessage)) e.preventDefault();
        });
    });
})();
</script>
@endsection

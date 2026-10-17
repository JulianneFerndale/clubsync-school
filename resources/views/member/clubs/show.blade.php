@extends($layout ?? 'layouts.app-member')
@section('title', $club->name . ' — ClubSync')

@php
    $typeStatus = $club->type ?? ($club->club_type === 'Academic' ? 'academic' : 'non_academic');
    $initials   = strtoupper(substr($club->acronym ?? $club->name, 0, 2));
@endphp

@section('live-refresh', 'club:' . $club->id)
@section('content')

{{-- Banner / cover --}}
<div class="relative">
    <a href="{{ route($dashboardRoute ?? 'member.dashboard') }}"
       class="absolute top-4 left-4 z-10 w-9 h-9 rounded-full bg-black/30 text-white flex items-center justify-center hover:bg-black/50 transition">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
        </svg>
    </a>
    @if($club->banner_image)
        <img src="{{ $club->banner_image }}" alt="" class="w-full h-40 object-cover"
             onerror="this.onerror=null;this.replaceWith(Object.assign(document.createElement('div'),{className:'w-full h-40 bg-gradient-to-br from-[#07873e] to-[#0d3311]'}))">
    @else
        <div class="w-full h-40 bg-gradient-to-br from-[#07873e] to-[#0d3311]"></div>
    @endif
</div>

{{-- Identity --}}
<div class="px-4 -mt-10 relative">
    @if($club->profile_photo_url)
        <img src="{{ $club->profile_photo_url }}" alt="{{ $club->acronym }}"
             class="w-20 h-20 rounded-full object-cover border-4 border-white shadow-md bg-white"
             onerror="this.onerror=null;this.outerHTML='<div class=\'w-20 h-20 rounded-full border-4 border-white shadow-md bg-[#07873e] flex items-center justify-center\'><span class=\'text-white font-bold text-xl\'>{{ $initials }}</span></div>'">
    @else
        <div class="w-20 h-20 rounded-full border-4 border-white shadow-md bg-[#07873e] flex items-center justify-center">
            <span class="text-white font-bold text-xl">{{ $initials }}</span>
        </div>
    @endif

    <h1 class="text-xl font-bold text-gray-900 mt-2">{{ $club->name }}</h1>
    <div class="flex items-center gap-2 mt-0.5">
        <x-status-badge :status="$typeStatus" />
        <span class="text-xs text-gray-400">{{ $club->acronym }}</span>
    </div>
    <p class="text-sm text-gray-500 mt-1.5">
        <span class="font-semibold text-gray-700">{{ $memberCount }}</span> {{ $memberCount === 1 ? 'member' : 'members' }}
        · <span class="font-semibold text-gray-700">{{ $upcomingEvents->count() }}</span> upcoming
    </p>

    {{-- Public page: shown to every viewer, including non-members and pending applicants. --}}
    <x-facebook-button :url="$club->facebook_url" :club="$club->name" class="mt-3" />
</div>

<div class="px-4 py-4 space-y-4">

    {{-- Membership action (members only; officers view enrolled clubs read-only) --}}
    @unless($readOnly ?? false)
        @if(! $membership)
            <form method="POST" action="{{ route('member.clubs.join', $club) }}"
                  data-loading="dialog" data-loading-message="Submitting membership request">
                @csrf
                <button type="submit"
                        class="w-full bg-[#07873e] text-[#F9A825] font-bold text-sm py-3.5 rounded-xl hover:opacity-90 transition-opacity shadow-sm">
                    Join This Club
                </button>
            </form>
        @elseif($membership->status === 'pending')
            <div class="bg-[#F9A825]/10 border border-[#F9A825]/30 rounded-xl px-4 py-3 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <svg class="w-5 h-5 text-[#F9A825]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                    </svg>
                    <span class="text-sm font-semibold text-gray-700">Membership request pending</span>
                </div>
                <form method="POST" action="{{ route('member.clubs.leave', $club) }}"
                      onsubmit="return confirm('Cancel your membership request for {{ addslashes($club->name) }}?')">
                    @csrf
                    <button type="submit" class="text-xs text-red-500 font-medium hover:text-red-700">Cancel</button>
                </form>
            </div>
        @elseif($membership->status === 'active')
            <div class="flex items-center gap-2.5 bg-green-50 border border-green-200 rounded-xl px-4 py-3">
                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                </svg>
                <span class="text-sm font-semibold text-green-700 flex-1">You are a member of this club</span>
                <form method="POST" action="{{ route('member.clubs.leave', $club) }}"
                      onsubmit="return confirm('Leave {{ addslashes($club->name) }}? You will need to re-apply to rejoin.')">
                    @csrf
                    <button type="submit" class="text-xs text-red-500 font-medium hover:text-red-700">Leave</button>
                </form>
            </div>
        @endif
    @endunless

    {{-- Club management — only for officers of THIS club. Officers navigate as
         members everywhere else; their admin tools live here, inside their club. --}}
    @if($canManage ?? false)
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="flex items-center gap-2 px-4 py-3 bg-[#07873e]/5 border-b border-[#07873e]/10">
                <svg class="w-4 h-4 text-[#07873e]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085"/>
                </svg>
                <p class="text-sm font-semibold text-[#07873e]">Club Management</p>
            </div>
            @php
                $tools = [
                    ['officer.activities.index',    'Activities',    'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5'],
                    ['officer.announcements.index', 'Announcements', 'M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 1 1 0-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 0 1-1.44-4.282m3.102.069a18.03 18.03 0 0 1-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 0 1 8.835 2.535M10.34 6.66a23.847 23.847 0 0 1 8.835-2.535'],
                    ['clubs.members.index',         'Members',       'M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z'],
                    ['clubs.officers.index',        'Officers',      'M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z'],
                    ['officer.fees.index',          'Fee Collection','M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
                    ['clubs.presence.index',        'Presence',      'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
                    ['clubs.violations.index',      'Compliance',    'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z'],
                    ['churn-risk',                  'Churn Risk',    'M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941'],
                ];
            @endphp
            <div class="grid grid-cols-2 sm:grid-cols-4 divide-x divide-y divide-gray-50">
                @foreach($tools as [$route, $label, $icon])
                    <a href="{{ route($route) }}"
                       class="flex flex-col items-center justify-center gap-1.5 py-4 px-2 hover:bg-[#07873e]/5 transition-colors">
                        <svg class="w-5 h-5 text-[#07873e]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $icon }}"/>
                        </svg>
                        <span class="text-[11px] font-medium text-gray-600 text-center leading-tight">{{ $label }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- About + Adviser — always shown, so a club without a description still names its adviser. --}}
    @php $adviser = $club->adviserUser; @endphp
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <section class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 sm:p-5">
            <div class="flex items-center gap-2 mb-3">
                <svg class="w-4 h-4 text-[#07873e]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z"/>
                </svg>
                <h2 class="text-xs font-bold uppercase tracking-wider text-gray-500">About the Club</h2>
            </div>
            @if($club->description)
                <p class="text-sm text-gray-700 leading-relaxed">{{ $club->description }}</p>
            @else
                <p class="text-sm text-gray-400">This club hasn't added a description yet.</p>
            @endif
        </section>

        <section class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 sm:p-5">
            <div class="flex items-center gap-2 mb-3">
                <svg class="w-4 h-4 text-[#07873e]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
                </svg>
                <h2 class="text-xs font-bold uppercase tracking-wider text-gray-500">Faculty Adviser</h2>
            </div>
            @if($adviser)
                <div class="flex items-center gap-3.5">
                    @if($adviser->profile_photo_url)
                        <img src="{{ $adviser->profile_photo_url }}" alt="{{ $adviser->name }}"
                             class="w-11 h-11 rounded-full object-cover border border-emerald-200 flex-shrink-0">
                    @else
                        <div class="w-11 h-11 rounded-full bg-emerald-50 text-emerald-800 font-bold text-sm border border-emerald-200 flex items-center justify-center flex-shrink-0">
                            {{ mb_strtoupper(mb_substr($adviser->first_name ?? '', 0, 1) . mb_substr($adviser->last_name ?? '', 0, 1)) ?: 'A' }}
                        </div>
                    @endif
                    <div class="min-w-0">
                        <p class="text-base font-bold text-gray-900 leading-snug truncate">{{ $adviser->name }}</p>
                        <p class="text-xs text-gray-500 mt-0.5">Club Adviser</p>
                    </div>
                </div>
            @else
                <p class="text-sm text-gray-400">No adviser assigned yet.</p>
            @endif
        </section>
    </div>

    {{-- Club content — visible only to approved members / club officers --}}
    @if($canViewContent ?? false)
        {{-- Upcoming activities --}}
        <section class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="flex items-center gap-2 px-4 sm:px-5 pt-4 pb-3">
                <svg class="w-4 h-4 text-[#07873e]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
                </svg>
                <h2 class="text-xs font-bold uppercase tracking-wider text-gray-500">Upcoming Activities</h2>
            </div>
            <div class="divide-y divide-gray-100 border-t border-gray-100">
                @forelse($upcomingEvents as $event)
                    <x-activity-row :event="$event" />
                @empty
                    <p class="text-sm text-gray-400 text-center py-6">No upcoming activities.</p>
                @endforelse
            </div>
        </section>

        {{-- Announcements --}}
        <section class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="flex items-center gap-2 px-4 sm:px-5 pt-4 pb-3">
                <svg class="w-4 h-4 text-[#07873e]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 1 1 0-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 0 1-1.44-4.282m3.102.069a18.03 18.03 0 0 1-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 0 1 8.835 2.535M10.34 6.66a23.847 23.847 0 0 1 8.835-2.535"/>
                </svg>
                <h2 class="text-xs font-bold uppercase tracking-wider text-gray-500">Announcements</h2>
            </div>
            <div class="divide-y divide-gray-100 border-t border-gray-100">
                @forelse($announcements as $ann)
                    <article class="px-4 sm:px-5 py-3.5">
                        <p class="text-sm font-semibold text-gray-800">{{ $ann->title ?? 'Announcement' }}</p>
                        <p class="text-xs text-gray-500 mt-1 line-clamp-3">{{ $ann->content }}</p>
                        <p class="text-xs text-gray-400 mt-2">{{ $ann->published_at?->diffForHumans() }}</p>
                    </article>
                @empty
                    <p class="text-sm text-gray-400 text-center py-6">No announcements yet.</p>
                @endforelse
            </div>
        </section>
    @else
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-8 text-center">
            <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-3">
                <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/>
                </svg>
            </div>
            <p class="text-sm font-semibold text-gray-600">Members only</p>
            <p class="text-xs text-gray-400 mt-1">Join this club and get approved to see its activities and announcements.</p>
        </div>
    @endif

</div>
@endsection

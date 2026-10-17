@extends('layouts.app-dsa')
@section('title', 'Members — ' . $club->name)

@section('content')

{{-- Page title bar --}}
<div class="bg-[#07873e] px-5 py-4">
    <h1 class="text-white font-bold text-xl">{{ $club->name }}</h1>
    <p class="text-white/60 text-xs mt-0.5">Member Registration (oversight — approved by the club adviser)</p>
</div>

<div class="px-4 py-5 space-y-5">


    @if($members->isEmpty())
        <p class="text-sm text-gray-400 text-center py-8">No members submitted for this club yet.</p>
    @else
        <div id="member-list" class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden divide-y divide-gray-50">
            @foreach($members as $member)
                <div class="px-4 py-3.5">
                    <div class="flex items-center gap-3">
                        {{-- Initials sit underneath; the photo covers them, and removes itself if it fails to load. --}}
                        <div class="relative w-10 h-10 rounded-full bg-[#07873e]/10 flex items-center justify-center flex-shrink-0">
                            <span class="text-[#07873e] text-xs font-bold">
                                {{ strtoupper(substr($member->user?->first_name ?? 'U', 0, 1) . substr($member->user?->last_name ?? '', 0, 1)) }}
                            </span>
                            @if($member->user?->profile_photo_url)
                                <button type="button"
                                        data-photo-src="{{ $member->user->profile_photo_url }}"
                                        data-photo-name="{{ $member->user->name }}"
                                        aria-label="View {{ $member->user->name }}'s profile photo"
                                        class="absolute inset-0 rounded-full overflow-hidden ring-2 ring-transparent hover:ring-[#07873e]/40 focus:outline-none focus-visible:ring-[#07873e] transition cursor-zoom-in">
                                    <img src="{{ $member->user->profile_photo_url }}" alt="" loading="lazy"
                                         class="w-full h-full object-cover"
                                         onerror="this.parentElement.remove()">
                                </button>
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-800 truncate">{{ $member->user?->name ?? 'Unknown user' }}</p>
                            <p class="text-xs text-gray-400 truncate">
                                {{ $member->user?->email ?? '' }}
                                @if($member->submittedBy)
                                    · submitted by {{ $member->submittedBy->name }}
                                @endif
                            </p>
                        </div>
                        <x-status-badge :status="$member->registration_status" />
                    </div>

                    @if($member->registration_status === 'pending' && $member->submitted_by)
                        <p class="text-xs text-gray-400 mt-2">Awaiting the club adviser's approval.</p>
                    @elseif($member->registration_status === 'rejected' && $member->dsa_remarks)
                        <p class="text-xs text-red-500 mt-2">Adviser remarks: {{ $member->dsa_remarks }}</p>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Enlarged profile photo --}}
        <dialog id="member-photo-dialog" aria-labelledby="member-photo-name"
                class="m-auto w-[calc(100%-2rem)] max-w-sm rounded-2xl overflow-hidden shadow-xl backdrop:bg-black/60">
            <img id="member-photo-img" src="" alt="" class="w-full aspect-square object-cover bg-gray-100">
            <div class="flex items-center justify-between gap-3 px-4 py-3">
                <p id="member-photo-name" class="text-sm font-semibold text-gray-800 truncate"></p>
                <form method="dialog">
                    <button class="text-sm font-semibold text-[#07873e] hover:underline">Close</button>
                </form>
            </div>
        </dialog>

        <script>
            (() => {
                // Bound to this page's own elements, so a Turbo revisit never stacks handlers.
                const list   = document.getElementById('member-list');
                const dialog = document.getElementById('member-photo-dialog');
                const img    = document.getElementById('member-photo-img');
                const name   = document.getElementById('member-photo-name');

                list.addEventListener('click', (e) => {
                    const btn = e.target.closest('[data-photo-src]');
                    if (! btn) return;
                    img.src = btn.dataset.photoSrc;
                    img.alt = btn.dataset.photoName;
                    name.textContent = btn.dataset.photoName;
                    dialog.showModal();
                });

                // A click on the backdrop closes it too.
                dialog.addEventListener('click', (e) => { if (e.target === dialog) dialog.close(); });

                // Never keep it open in Turbo's snapshot, or Back would reopen it.
                document.addEventListener('turbo:before-cache', () => dialog.close(), { once: true });
            })();
        </script>
    @endif

</div>
@endsection

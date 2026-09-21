@extends(auth_role() === 'adviser' ? 'layouts.app-adviser' : 'layouts.app-officer')
@section('title', 'Members — ' . ($club?->name ?? 'ClubSync'))
@section('club-name', $club?->name ?? 'ClubSync')
@section('page-title', 'Members')

@section('content')

{{-- Page title bar --}}
<div class="bg-[#07873e] px-5 py-4">
    <h1 class="text-white font-bold text-xl">Members</h1>
    <p class="text-white/60 text-xs mt-0.5">{{ $club?->name ?? 'No club assigned' }}</p>
</div>

<div class="px-4 py-5 space-y-5">

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 rounded-xl px-4 py-3 text-green-700 text-sm">
            {{ session('success') }}
        </div>
    @endif
    @if(session('info'))
        <div class="bg-[#F9A825]/10 border border-[#F9A825]/40 rounded-xl px-4 py-3 text-gray-700 text-sm">
            {{ session('info') }}
        </div>
    @endif
    @if($errors->any())
        <div class="bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-red-700 text-sm">
            {{ $errors->first() }}
        </div>
    @endif

    @if(! $club)
        <p class="text-sm text-gray-400 text-center py-8">You are not associated with any club.</p>
    @elseif($members->isEmpty())
        <p class="text-sm text-gray-400 text-center py-8">No members yet. Students who join this club will appear here.</p>
    @elseif(auth_role() === 'adviser')
        {{-- Adviser: approve / reject members submitted by officers --}}
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden divide-y divide-gray-50">
            @foreach($members as $member)
                <div class="px-4 py-3.5">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-full bg-[#07873e]/10 flex items-center justify-center flex-shrink-0">
                            <span class="text-[#07873e] text-xs font-bold">
                                {{ strtoupper(substr($member->user?->first_name ?? 'U', 0, 1) . substr($member->user?->last_name ?? '', 0, 1)) }}
                            </span>
                        </div>

                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-800 truncate">{{ $member->user?->name ?? 'Unknown user' }}</p>
                            <p class="text-xs text-gray-400 truncate">
                                {{ $member->user?->email ?? '' }}
                                @if($member->submittedBy)
                                    · submitted by {{ $member->submittedBy->name }}
                                @endif
                            </p>
                            @if($member->registration_status === 'rejected' && $member->dsa_remarks)
                                <p class="text-xs text-red-500 mt-1">Adviser remarks: {{ $member->dsa_remarks }}</p>
                            @endif
                        </div>

                        <x-status-badge :status="$member->registration_status" />
                    </div>

                    @if($member->registration_status === 'pending')
                        <div class="flex items-center gap-2 mt-3 pl-12">
                            <form method="POST" action="{{ route('clubs.members.approve', $member) }}"
                                  data-loading="dialog" data-loading-message="Approving member">
                                @csrf
                                <button type="submit"
                                        class="bg-[#07873e] text-[#F9A825] text-xs font-semibold rounded-lg px-4 py-2 hover:opacity-90 transition-opacity">
                                    Approve
                                </button>
                            </form>
                            <button type="button" onclick="document.getElementById('reject-{{ $member->id }}').classList.toggle('hidden')"
                                    class="border border-red-200 text-red-600 text-xs font-semibold rounded-lg px-4 py-2 hover:bg-red-50 transition-colors">
                                Reject
                            </button>
                        </div>
                        <form method="POST" action="{{ route('clubs.members.reject', $member) }}"
                              id="reject-{{ $member->id }}" class="hidden mt-2 pl-12"
                              data-loading="dialog" data-loading-message="Rejecting member">
                            @csrf
                            <textarea name="remarks" rows="2" required minlength="5"
                                      placeholder="Reason for rejection (shared with the club)…"
                                      class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:ring-[#07873e] focus:border-[#07873e]"></textarea>
                            <button type="submit"
                                    class="mt-2 bg-red-600 text-white text-xs font-semibold rounded-lg px-4 py-2 hover:bg-red-700 transition-colors">
                                Confirm rejection
                            </button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>
    @else
        {{-- Officer: select members and submit them to the club adviser for approval --}}
        <form method="POST" action="{{ route('clubs.members.store') }}"
              data-loading="dialog" data-loading-message="Submitting member registration">
            @csrf

            <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden divide-y divide-gray-50">
                @foreach($members as $member)
                    <div class="flex items-center gap-3 px-4 py-3.5">
                        @if($member->registration_status !== 'approved')
                            <input type="checkbox" name="member_ids[]" value="{{ $member->id }}"
                                   class="w-4 h-4 rounded border-gray-300 text-[#07873e] focus:ring-[#07873e]">
                        @else
                            <span class="w-4 h-4"></span>
                        @endif

                        <div class="w-9 h-9 rounded-full bg-[#07873e]/10 flex items-center justify-center flex-shrink-0">
                            <span class="text-[#07873e] text-xs font-bold">
                                {{ strtoupper(substr($member->user?->first_name ?? 'U', 0, 1) . substr($member->user?->last_name ?? '', 0, 1)) }}
                            </span>
                        </div>

                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-800 truncate">{{ $member->user?->name ?? 'Unknown user' }}</p>
                            <p class="text-xs text-gray-400 truncate">
                                {{ $member->user?->email ?? '' }}
                                @if($member->submittedBy)
                                    · submitted by {{ $member->submittedBy->name }}
                                @endif
                            </p>
                            @if($member->registration_status === 'rejected' && $member->dsa_remarks)
                                <p class="text-xs text-red-500 mt-1">Adviser remarks: {{ $member->dsa_remarks }}</p>
                            @endif
                        </div>

                        <x-status-badge :status="$member->registration_status" />
                    </div>
                @endforeach
            </div>

            <button type="submit"
                    class="mt-4 w-full bg-[#07873e] text-[#F9A825] font-semibold text-sm rounded-xl py-3.5 hover:opacity-90 transition-opacity">
                Submit Selected to Adviser
            </button>
        </form>
    @endif

</div>
@endsection

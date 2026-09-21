@extends('layouts.app-dsa')
@section('title', 'Members — ' . $club->name)

@section('content')

{{-- Page title bar --}}
<div class="bg-[#07873e] px-5 py-4">
    <h1 class="text-white font-bold text-xl">{{ $club->name }}</h1>
    <p class="text-white/60 text-xs mt-0.5">Member Registration (oversight — approved by the club adviser)</p>
</div>

<div class="px-4 py-5 space-y-5">

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 rounded-xl px-4 py-3 text-green-700 text-sm">
            {{ session('success') }}
        </div>
    @endif
    @if($errors->any())
        <div class="bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-red-700 text-sm">
            {{ $errors->first() }}
        </div>
    @endif

    @if($members->isEmpty())
        <p class="text-sm text-gray-400 text-center py-8">No members submitted for this club yet.</p>
    @else
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
    @endif

</div>
@endsection

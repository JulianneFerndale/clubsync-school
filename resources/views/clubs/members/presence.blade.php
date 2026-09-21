@extends(auth_role() === 'adviser' ? 'layouts.app-adviser' : 'layouts.app-officer')
@section('title', 'Presence — ' . ($club?->name ?? 'ClubSync'))
@section('club-name', $club?->name ?? 'ClubSync')
@section('page-title', 'Semestral Presence')

@section('content')

{{-- Page title bar --}}
<div class="bg-[#07873e] px-5 py-4">
    <h1 class="text-white font-bold text-xl">Semestral Presence</h1>
    <p class="text-white/60 text-xs mt-0.5">{{ $semester?->label ?? 'No active semester' }}</p>
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

    @if(! $club)
        <p class="text-sm text-gray-400 text-center py-8">You are not associated with any club.</p>
    @elseif(! $semester)
        <p class="text-sm text-gray-400 text-center py-8">There is no active semester configured yet.</p>
    @elseif($members->isEmpty())
        <p class="text-sm text-gray-400 text-center py-8">No approved members yet. Members must be DSA-approved before their presence can be tracked.</p>
    @else
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden divide-y divide-gray-50">
            @foreach($members as $member)
                @php $current = $member->current_status; @endphp
                <div class="flex items-center gap-3 px-4 py-3.5">
                    <div class="w-9 h-9 rounded-full bg-[#07873e]/10 flex items-center justify-center flex-shrink-0">
                        <span class="text-[#07873e] text-xs font-bold">
                            {{ strtoupper(substr($member->user?->first_name ?? 'U', 0, 1) . substr($member->user?->last_name ?? '', 0, 1)) }}
                        </span>
                    </div>

                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-gray-800 truncate">{{ $member->user?->name ?? 'Unknown user' }}</p>
                        <div class="mt-1">
                            <x-status-badge :status="$current?->semester_status ?? 'active'" />
                        </div>
                    </div>

                    <form method="POST" action="{{ route('clubs.presence.update', $member) }}" class="flex-shrink-0">
                        @csrf
                        <select name="semester_status" onchange="this.form.submit()"
                                class="border border-gray-300 rounded-lg text-xs px-2 py-1.5 bg-white focus:outline-none focus:ring-2 focus:ring-[#07873e]/30">
                            <option value="active"   {{ ($current?->semester_status ?? 'active') === 'active'   ? 'selected' : '' }}>Active</option>
                            <option value="inactive" {{ $current?->semester_status === 'inactive' ? 'selected' : '' }}>Inactive</option>
                            <option value="dropped"  {{ $current?->semester_status === 'dropped'  ? 'selected' : '' }}>Dropped</option>
                        </select>
                    </form>
                </div>
            @endforeach
        </div>

        <form method="POST" action="{{ route('clubs.presence.notify') }}">
            @csrf
            <button type="submit"
                    class="w-full bg-[#07873e] text-[#F9A825] font-semibold text-sm rounded-xl py-3.5 hover:opacity-90 transition-opacity">
                Mark Presence Update Complete &amp; Notify DSA
            </button>
        </form>
    @endif

</div>
@endsection

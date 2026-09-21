@extends(auth_role() === 'adviser' ? 'layouts.app-adviser' : 'layouts.app-officer')
@section('title', 'Officers — ' . ($club?->name ?? 'ClubSync'))
@section('club-name', $club?->name ?? 'ClubSync')
@section('page-title', 'Officers')

@section('content')

{{-- Page title bar --}}
<div class="bg-[#07873e] px-5 py-4">
    <h1 class="text-white font-bold text-xl">Officers</h1>
    <p class="text-white/60 text-xs mt-0.5">{{ $club?->name ?? 'No club assigned' }}</p>
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

    {{-- Officers turn over with the school year. Nothing is revoked on the date —
         the sitting officers keep working until the club replaces them. --}}
    @if($turnoverPending)
        <div class="bg-[#F9A825]/10 border border-[#F9A825]/40 rounded-xl px-4 py-3.5">
            <p class="text-sm font-semibold text-gray-800">
                New officers needed for A.Y. {{ $academicYear }}
            </p>
            <p class="text-xs text-gray-600 mt-1 leading-relaxed">
                No officer has been appointed for this club since the school year began.
                The current officers keep their access until they are replaced.
            </p>
            @if(auth_role() === 'adviser')
                <a href="{{ route('clubs.officers.create') }}"
                   class="inline-block mt-2.5 bg-[#07873e] text-[#F9A825] font-semibold text-xs rounded-lg px-3 py-2 hover:opacity-90 transition-opacity">
                    Record this year's officers
                </a>
            @endif
        </div>
    @endif

    @if(! $club)
        <p class="text-sm text-gray-400 text-center py-8">You are not associated with any club.</p>
    @elseif($records->isEmpty())
        <p class="text-sm text-gray-400 text-center py-8">No officer records yet.</p>
    @else
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden divide-y divide-gray-50">
            @foreach($records as $record)
                <div class="flex items-center gap-3 px-4 py-3.5 {{ $record->is_active ? '' : 'opacity-50' }}">
                    <div class="w-9 h-9 rounded-full bg-[#07873e]/10 flex items-center justify-center flex-shrink-0">
                        <span class="text-[#07873e] text-xs font-bold">
                            {{ strtoupper(substr($record->full_name, 0, 2)) }}
                        </span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-gray-800 truncate">{{ $record->full_name }}</p>
                        <p class="text-xs text-gray-400 truncate">
                            {{ $record->position }} · {{ $record->academic_year }} ({{ $record->semester }} Sem)
                        </p>
                        <p class="text-xs text-gray-400 truncate">{{ $record->student_id_number }} @if($record->contact_email) · {{ $record->contact_email }} @endif</p>
                    </div>
                    @if(! $record->is_active)
                        <x-status-badge status="inactive" />
                    @endif
                    @if(auth_role() === 'adviser')
                        <a href="{{ route('clubs.officers.edit', $record) }}" class="text-xs text-[#07873e] font-semibold px-2 py-1 hover:underline">
                            Edit
                        </a>
                        @if($record->is_active)
                            <form method="POST" action="{{ route('clubs.officers.archive', $record) }}"
                                  onsubmit="return confirm('Archive {{ addslashes($record->full_name) }}\'s officer record?')">
                                @csrf
                                <button type="submit" class="text-xs text-red-500 font-semibold px-2 py-1 hover:underline">
                                    Archive
                                </button>
                            </form>
                        @endif
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    @if($club && auth_role() === 'adviser')
        <a href="{{ route('clubs.officers.create') }}"
           class="flex items-center justify-between w-full border-2 border-[#07873e] rounded-xl px-4 py-3.5 text-[#07873e] font-semibold text-sm hover:bg-[#07873e]/5 transition-colors">
            Add officer record
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15"/>
            </svg>
        </a>
    @elseif($club)
        <p class="text-xs text-gray-400 text-center">Officer records are maintained by the club adviser.</p>
    @endif

</div>
@endsection

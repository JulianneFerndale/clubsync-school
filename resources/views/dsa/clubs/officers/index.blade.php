@extends('layouts.app-dsa')
@section('title', 'Officers — ' . $club->name)

@section('content')

<div class="bg-[#07873e] px-5 py-4">
    <div class="flex items-center gap-2 mb-1">
        <a href="{{ route('dsa.clubs.show', $club) }}" class="text-white/70 hover:text-white">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
            </svg>
        </a>
    </div>
    <h1 class="text-white font-bold text-xl">{{ $club->name }}</h1>
    <p class="text-white/60 text-xs mt-0.5">Officer Records</p>
</div>

<div class="px-4 py-5 space-y-5">

    @if($records->isEmpty())
        <p class="text-sm text-gray-400 text-center py-8">No officer records for this club yet.</p>
    @else
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden divide-y divide-gray-50">
            @foreach($records as $record)
                <div class="flex items-center gap-3 px-4 py-3.5 {{ $record->is_active ? '' : 'opacity-50' }}">
                    <div class="w-9 h-9 rounded-full bg-[#07873e]/10 flex items-center justify-center flex-shrink-0">
                        <span class="text-[#07873e] text-xs font-bold">{{ strtoupper(substr($record->full_name, 0, 2)) }}</span>
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
                </div>
            @endforeach
        </div>
    @endif

</div>
@endsection

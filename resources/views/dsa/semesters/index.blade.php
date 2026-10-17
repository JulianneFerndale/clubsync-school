@extends('layouts.app-dsa')
@section('title', 'Semesters — DSA')

@section('content')

<div class="bg-[#07873e] px-5 py-4">
    <h1 class="text-white font-bold text-xl">Semesters</h1>
    <p class="text-white/60 text-xs mt-0.5">Each club's narrative report is drafted the day after its semester ends, or when you conclude it</p>
</div>

<div class="px-4 py-5 space-y-5">



    {{-- Add a semester --}}
    <form method="POST" action="{{ route('dsa.semesters.store') }}" class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 space-y-3">
        @csrf
        <p class="text-sm font-semibold text-gray-800">Add a semester</p>
        <div>
            <label for="label" class="block text-xs text-gray-500 mb-1">Label</label>
            <input id="label" type="text" name="label" value="{{ old('label') }}" required maxlength="100"
                   placeholder="1st Semester, A.Y. 2026–2027"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#07873e]/30">
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label for="start_date" class="block text-xs text-gray-500 mb-1">Start date</label>
                <input id="start_date" type="date" name="start_date" value="{{ old('start_date') }}" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#07873e]/30">
            </div>
            <div>
                <label for="end_date" class="block text-xs text-gray-500 mb-1">End date</label>
                <input id="end_date" type="date" name="end_date" value="{{ old('end_date') }}" required
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#07873e]/30">
            </div>
        </div>
        <label class="flex items-center gap-2 text-sm text-gray-700">
            <input type="checkbox" name="is_current" value="1" @checked(old('is_current'))
                   class="w-4 h-4 rounded border-gray-300 text-[#07873e] focus:ring-[#07873e]">
            This is the current semester
        </label>
        <button type="submit" class="bg-[#07873e] text-[#F9A825] font-semibold text-sm rounded-lg px-4 py-2.5 hover:opacity-90">Add Semester</button>
    </form>

    {{-- Existing semesters --}}
    @if($semesters->isEmpty())
        <p class="text-sm text-gray-400 text-center py-6">No semesters defined yet. End-of-semester reports need a semester end date to run.</p>
    @else
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden divide-y divide-gray-100">
            @foreach($semesters as $semester)
                @php
                    $phase = $semester->end_date->lt(today()) ? 'Ended' : ($semester->start_date->gt(today()) ? 'Upcoming' : 'In progress');
                @endphp
                <details class="group">
                    <summary class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 cursor-pointer list-none">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-800">
                                {{ $semester->label }}
                                @if($semester->is_current)
                                    <span class="ml-1 text-[10px] font-semibold bg-green-100 text-green-700 px-1.5 py-0.5 rounded-full">Current</span>
                                @endif
                            </p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                {{ $semester->start_date->format('M j, Y') }} – {{ $semester->end_date->format('M j, Y') }} · {{ $phase }}
                            </p>
                        </div>
                        <p class="text-xs text-gray-500">
                            Reports: {{ $semester->submitted_reports_count }} submitted / {{ $semester->narrative_reports_count }} drafted
                        </p>
                    </summary>

                    <form method="POST" action="{{ route('dsa.semesters.update', $semester) }}" class="px-4 pb-4 space-y-3 bg-gray-50/60">
                        @csrf
                        @method('PATCH')
                        <input type="text" name="label" value="{{ $semester->label }}" required maxlength="100" aria-label="Label"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <input type="date" name="start_date" value="{{ $semester->start_date->toDateString() }}" required aria-label="Start date"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                            <input type="date" name="end_date" value="{{ $semester->end_date->toDateString() }}" required aria-label="End date"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                        </div>
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="is_current" value="1" @checked($semester->is_current)
                                   class="w-4 h-4 rounded border-gray-300 text-[#07873e] focus:ring-[#07873e]">
                            Current semester
                        </label>
                        <button type="submit" class="border-2 border-[#07873e] text-[#07873e] font-semibold text-sm rounded-lg px-4 py-2 hover:bg-[#07873e]/5">Save</button>
                    </form>

                    @unless($semester->start_date->gt(today()))
                        <form method="POST" action="{{ route('dsa.semesters.conclude', $semester) }}"
                              class="px-4 pb-4 pt-3 bg-gray-50/60 border-t border-gray-100 flex flex-wrap items-center justify-between gap-3"
                              onsubmit="return confirm('Draft this semester\'s narrative reports now? Each adviser reviews their club\'s report before it is submitted to you.')">
                            @csrf
                            <p class="text-xs text-gray-500 max-w-md">
                                Conclude the semester to have the AI draft each club's narrative report now, from its completed activities,
                                instead of the day after {{ $semester->end_date->format('M j') }}. The dates stay as they are.
                            </p>
                            <button type="submit" class="bg-[#07873e] text-[#F9A825] font-semibold text-sm rounded-lg px-4 py-2.5 hover:opacity-90">
                                Conclude &amp; Draft Reports
                            </button>
                        </form>
                    @endunless
                </details>
            @endforeach
        </div>
    @endif

</div>
@endsection

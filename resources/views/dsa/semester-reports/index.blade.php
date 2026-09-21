@extends('layouts.app-dsa')
@section('title', 'Semester Narrative Reports — DSA')

@section('content')

<div class="bg-[#07873e] px-5 py-4">
    <h1 class="text-white font-bold text-xl">Semester Narrative Reports</h1>
    <p class="text-white/60 text-xs mt-0.5">End-of-semester reports submitted by club advisers</p>
</div>

<div class="px-4 py-5 space-y-4">

    @if($semesters->isNotEmpty())
        <form method="GET" action="{{ route('dsa.semester-reports.index') }}" class="flex flex-wrap items-center gap-2">
            <label for="semester_id" class="text-xs text-gray-500">Semester</label>
            <select id="semester_id" name="semester_id" onchange="this.form.submit()"
                    class="border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#07873e]/30">
                <option value="">All semesters</option>
                @foreach($semesters as $semester)
                    <option value="{{ $semester->id }}" @selected((string) request('semester_id') === (string) $semester->id)>{{ $semester->label }}</option>
                @endforeach
            </select>
        </form>
    @endif

    @if($reports->isEmpty())
        <p class="text-sm text-gray-400 text-center py-8">No semester narrative reports have been submitted yet.</p>
    @else
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden divide-y divide-gray-50">
            @foreach($reports as $report)
                <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3.5">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-gray-800">{{ $report->club->name }}</p>
                        <p class="text-xs text-gray-500 mt-0.5">
                            {{ $report->semester->label }}
                            · {{ $report->activity_data['totals']['activities'] }} activities
                            · {{ $report->activity_data['totals']['attended'] }} total check-ins
                        </p>
                        <p class="text-xs text-gray-400 mt-0.5">
                            Submitted {{ $report->submitted_at?->format('M j, Y') }} by {{ $report->reviewer?->name ?? 'the club adviser' }}
                        </p>
                    </div>
                    <a href="{{ route('dsa.semester-reports.pdf', $report) }}" class="text-xs text-[#07873e] font-semibold underline flex-shrink-0">Download PDF</a>
                </div>
            @endforeach
        </div>

        <div>{{ $reports->links() }}</div>
    @endif

</div>
@endsection

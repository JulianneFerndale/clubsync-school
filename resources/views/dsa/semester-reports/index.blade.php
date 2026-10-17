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

    @error('revision')
        <div class="bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-red-600 text-sm">{{ $message }}</div>
    @enderror

    @if($returned->isNotEmpty())
        <div>
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-2 px-1">
                Returned for Revision
                <span class="ml-1 bg-orange-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full">{{ $returned->count() }}</span>
            </p>
            <div class="bg-white rounded-xl border border-orange-100 shadow-sm overflow-hidden divide-y divide-gray-50">
                @foreach($returned as $report)
                    <div class="px-4 py-3.5">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-800">{{ $report->club->name }}</p>
                                <p class="text-xs text-gray-500 mt-0.5">{{ $report->semester->label }} · {{ $report->activity_data['totals']['activities'] }} activities</p>
                                <p class="text-xs text-gray-400 mt-0.5">
                                    Returned {{ $report->returned_at->format('M j, Y g:i A') }}@if($report->returner) by {{ $report->returner->name }}@endif · waiting for the adviser to resubmit
                                </p>
                            </div>
                            <x-status-badge status="revision_required" />
                        </div>
                        <p class="mt-2 text-xs text-orange-800 bg-orange-50 border border-orange-200 rounded-lg px-3 py-2 whitespace-pre-line"><span class="font-semibold">Your remarks:</span> {{ $report->return_remarks }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if($reports->isEmpty())
        <p class="text-sm text-gray-400 text-center py-8">No semester narrative reports have been submitted yet.</p>
    @else
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden divide-y divide-gray-50">
            @foreach($reports as $report)
                <div class="px-4 py-3.5">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-800">{{ $report->club->name }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                {{ $report->semester->label }}
                                · {{ $report->activity_data['totals']['activities'] }} activities
                                · {{ $report->activity_data['totals']['attended'] }} total check-ins
                            </p>
                            <p class="text-xs text-gray-400 mt-0.5">
                                Submitted {{ $report->submitted_at?->format('M j, Y') }} by {{ $report->reviewer?->name ?? 'the club adviser' }}
                                @if($report->returned_at) · revised after you returned it on {{ $report->returned_at->format('M j, Y') }} @endif
                            </p>
                        </div>
                        @if($report->isLegacy())
                            <a href="{{ route('dsa.semester-reports.pdf', $report) }}" class="text-xs text-[#07873e] font-semibold underline flex-shrink-0">Download PDF</a>
                        @else
                            <div class="flex flex-wrap items-center gap-2 flex-shrink-0">
                                <a href="{{ route('dsa.semester-reports.pdf', $report) }}"
                                   class="text-xs font-semibold bg-[#07873e] text-[#F9A825] rounded-lg px-3 py-1.5 hover:opacity-90">Complete report (PDF)</a>
                                <a href="{{ route('dsa.semester-reports.bundle', $report) }}"
                                   class="text-xs font-semibold border border-[#07873e] text-[#07873e] rounded-lg px-3 py-1.5 hover:bg-[#07873e]/5">Word files (ZIP)</a>
                            </div>
                        @endif
                    </div>

                    @unless($report->isLegacy())
                        <details class="mt-2">
                            <summary class="text-xs text-gray-500 cursor-pointer">The five documents</summary>
                            <div class="mt-2 divide-y divide-gray-50 border border-gray-100 rounded-lg">
                                @foreach($documents as $key => $type)
                                    <div class="flex flex-wrap items-center justify-between gap-2 px-3 py-2">
                                        <span class="text-xs text-gray-700">{{ $type['label'] }}</span>
                                        <span class="flex gap-3">
                                            <a href="{{ route('dsa.semester-reports.document', [$report, $key, 'docx']) }}" class="text-xs text-[#07873e] font-semibold underline">Word</a>
                                            <a href="{{ route('dsa.semester-reports.document', [$report, $key, 'pdf']) }}" class="text-xs text-[#07873e] font-semibold underline">PDF</a>
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </details>
                    @endunless

                    <details class="mt-2">
                        <summary class="text-xs text-red-500 font-semibold cursor-pointer select-none">Return for revision…</summary>
                        <form method="POST" action="{{ route('dsa.semester-reports.return', $report) }}" class="mt-2 space-y-2"
                              onsubmit="return confirm('Return this report to the club adviser for revision? It goes back to them as a draft until they submit it again.')">
                            @csrf
                            <label for="remarks-{{ $report->id }}" class="text-xs text-gray-500 block">What the adviser needs to revise</label>
                            <textarea id="remarks-{{ $report->id }}" name="remarks" rows="3" required maxlength="2000"
                                      placeholder="e.g. Attach the documentation photos for each activity and complete the minutes of the planning meeting."
                                      class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#07873e]/30"></textarea>
                            <button type="submit" class="border border-red-200 text-red-500 text-xs font-semibold rounded-lg px-3 py-1.5 hover:bg-red-50 transition-colors">
                                Return to Adviser
                            </button>
                        </form>
                    </details>
                </div>
            @endforeach
        </div>

        <div>{{ $reports->links() }}</div>
    @endif

</div>
@endsection

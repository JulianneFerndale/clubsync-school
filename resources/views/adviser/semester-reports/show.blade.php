@extends('layouts.app-adviser')
@section('title', $report->semester->label . ' Narrative Report')
@section('page-title', 'Semester Report')

@section('content')

<div class="bg-[#07873e] px-5 pt-5 pb-6">
    <a href="{{ route('adviser.semester-reports.index') }}" class="text-white/70 hover:text-white mb-4 inline-block" aria-label="Back to semester reports">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
        </svg>
    </a>
    <div class="flex items-start justify-between gap-3">
        <h1 class="text-white font-bold text-xl leading-snug flex-1">{{ $report->semester->label }} Narrative Report</h1>
        <x-status-badge :status="$report->status" />
    </div>
    <p class="text-white/60 text-xs mt-2">{{ $report->club->name }} · {{ $data['start'] }} – {{ $data['end'] }}</p>
</div>

<div class="px-4 py-5 space-y-4">

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 rounded-xl px-4 py-3 text-green-700 text-sm">{{ session('success') }}</div>
    @endif

    @error('status')
        <div class="bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-red-600 text-sm">{{ $message }}</div>
    @enderror

    @if($report->ai_available)
        <div class="flex items-center gap-2 px-3 py-2 bg-purple-50 rounded-xl border border-purple-100">
            <span class="text-purple-500 text-sm">✦</span>
            <span class="text-xs text-purple-600 font-medium">AI-generated draft from the activity records — review and edit before submitting</span>
        </div>
    @else
        <div class="flex items-center gap-2 px-3 py-2 bg-amber-50 rounded-xl border border-amber-100">
            <span class="text-amber-600 text-sm">⚠</span>
            <span class="text-xs text-amber-700 font-medium">AI Unavailable — Manual Draft Required. Write the narrative below from the activity records.</span>
        </div>
    @endif

    {{-- What the records say. The submitted PDF prints this same table beside the narrative. --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
        <p class="text-xs text-gray-400 font-medium mb-3">Activity records this semester</p>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-4">
            <div class="bg-gray-50 rounded-lg px-3 py-2">
                <p class="text-lg font-bold text-gray-800">{{ $data['totals']['activities'] }}</p>
                <p class="text-[11px] text-gray-500">Activities completed</p>
            </div>
            <div class="bg-gray-50 rounded-lg px-3 py-2">
                <p class="text-lg font-bold text-gray-800">{{ $data['totals']['attended'] }}</p>
                <p class="text-[11px] text-gray-500">Total check-ins</p>
            </div>
            <div class="bg-gray-50 rounded-lg px-3 py-2">
                <p class="text-lg font-bold text-gray-800">{{ $data['totals']['unique_attendees'] }}</p>
                <p class="text-[11px] text-gray-500">Different members</p>
            </div>
            <div class="bg-gray-50 rounded-lg px-3 py-2">
                <p class="text-lg font-bold text-gray-800">{{ $data['totals']['turnout'] !== null ? $data['totals']['turnout'] . '%' : '—' }}</p>
                <p class="text-[11px] text-gray-500">Turnout vs expected</p>
            </div>
        </div>

        <div class="divide-y divide-gray-100">
            @foreach($data['activities'] as $activity)
                <div class="py-2.5">
                    <p class="text-sm font-semibold text-gray-800">{{ $activity['title'] }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">{{ $activity['type'] }} · {{ $activity['date'] }} · {{ $activity['time'] }} · {{ $activity['venue'] }}</p>
                    <p class="text-xs text-gray-600 mt-1">
                        @if($activity['attendance_recorded'])
                            <span class="font-semibold">{{ $activity['attended'] }}</span> attended of {{ $activity['expected'] }} expected
                            @if($activity['turnout'] !== null) ({{ $activity['turnout'] }}%) @endif
                        @else
                            Attendance not recorded · {{ $activity['expected'] }} expected
                        @endif
                    </p>
                </div>
            @endforeach
        </div>
    </div>

    @if(! $report->isSubmitted())

        @if($warnings)
            <div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3">
                <p class="text-sm font-semibold text-amber-800">Check before submitting</p>
                <ul class="mt-1.5 space-y-1 text-xs text-amber-900 list-disc pl-4">
                    @foreach($warnings as $warning)
                        <li>{{ $warning }}</li>
                    @endforeach
                </ul>
                <p class="text-[11px] text-amber-700 mt-2">These compare the narrative with the activity records above. Save your edits to re-check.</p>
            </div>
        @endif

        <form method="POST" action="{{ route('adviser.semester-reports.update', $report) }}" class="space-y-3">
            @csrf
            @method('PATCH')
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                <label for="content" class="text-xs text-gray-400 font-medium mb-2 block">Narrative</label>
                @error('content')
                    <p class="text-red-500 text-xs mb-2">{{ $message }}</p>
                @enderror
                <textarea id="content" name="content" rows="18" required
                          class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm text-gray-700 leading-relaxed focus:outline-none focus:ring-2 focus:ring-[#07873e]/30">{{ old('content', $report->finalContent()) }}</textarea>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <button type="submit" name="intent" value="save"
                        class="flex items-center justify-center border-2 border-[#07873e] text-[#07873e] font-semibold text-sm rounded-xl px-5 py-3.5 hover:bg-[#07873e]/5 transition-colors">
                    Save Changes
                </button>
                <button type="submit" name="intent" value="submit"
                        class="flex items-center justify-center bg-[#07873e] text-[#F9A825] font-semibold text-sm rounded-xl px-5 py-3.5 hover:opacity-90 transition-opacity"
                        onclick="return confirm('Submit this narrative report to the DSA? It can no longer be edited afterwards.')">
                    Approve &amp; Submit to DSA
                </button>
            </div>
        </form>

    @else

        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
            <p class="text-xs text-gray-400 font-medium mb-2">Narrative</p>
            <p class="text-sm text-gray-700 leading-relaxed whitespace-pre-line">{{ $report->finalContent() }}</p>
        </div>

        <div class="bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm text-gray-600 flex flex-wrap items-center justify-between gap-2">
            <span>Submitted to the DSA · {{ $report->submitted_at?->format('F j, Y g:i A') }}</span>
            <a href="{{ route('adviser.semester-reports.pdf', $report) }}" class="text-[#07873e] font-semibold underline">Download PDF</a>
        </div>

    @endif

</div>
@endsection

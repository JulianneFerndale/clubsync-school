@extends('layouts.app-officer')
@section('title', 'Reports to DSA')
@section('club-name', $club?->name ?? 'ClubSync')

@php
    // Full literal class strings (Tailwind's scanner can't see interpolated names).
    $statusBadge = [
        'submitted' => 'bg-blue-50 text-blue-600 border-blue-200',
        'accepted'  => 'bg-green-50 text-green-600 border-green-200',
        'returned'  => 'bg-red-50 text-red-600 border-red-200',
    ];
    $done = $submissions->where('status', 'accepted')->count();
@endphp

@section('content')

<div class="bg-[#07873e] px-5 pt-5 pb-6">
    <h1 class="text-white font-bold text-xl">Reports to DSA</h1>
    <p class="text-white/60 text-xs mt-1">
        {{ $club?->name ?? 'No club assigned' }}@if($semester) · {{ $semester->label }}@endif
    </p>
</div>

<div class="px-4 py-5 space-y-4 max-w-3xl">


    @if(! $club)
        <p class="text-sm text-gray-400 text-center py-8">No club assigned.</p>
    @elseif(! $semester)
        <div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 text-amber-700 text-sm">
            No semester is open right now, so there are no reports due. The DSA opens each semester.
        </div>
    @else

        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
            <div class="flex items-center justify-between">
                <p class="text-sm font-semibold text-gray-700">{{ $done }} of {{ count($types) }} reports accepted</p>
                <p class="text-xs text-gray-400">{{ $submissions->count() }} submitted</p>
            </div>
            <div class="h-2 bg-gray-100 rounded-full overflow-hidden mt-2">
                <div class="h-full bg-[#07873e] rounded-full" style="width: {{ round($done / max(1, count($types)) * 100) }}%"></div>
            </div>
            <p class="text-xs text-gray-500 mt-3">
                @if($isPresident)
                    Download each template, have the officer named on it fill it in, then upload the finished report here. You can replace a report until the DSA accepts it.
                @else
                    Download the template for your part and prepare it. Only the club president submits the finished reports to the DSA.
                @endif
            </p>
        </div>

        @foreach($types as $key => $type)
            @php($submission = $submissions->get($key))
            @php($template = $templates->get($key))
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 space-y-3">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-gray-800">{{ $type['label'] }}</p>
                        <p class="text-[11px] text-gray-400 mt-0.5">Prepared by the {{ $type['prepared_by'] }}</p>
                    </div>
                    @if($submission)
                        <span class="flex-shrink-0 inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold border {{ $statusBadge[$submission->status] ?? 'bg-gray-50 text-gray-600 border-gray-200' }}">
                            {{ $submission->status_label }}
                        </span>
                    @else
                        <span class="flex-shrink-0 inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold border bg-gray-50 text-gray-500 border-gray-200">
                            Not submitted
                        </span>
                    @endif
                </div>

                <p class="text-xs text-gray-500">{{ $type['description'] }}</p>

                @if($submission)
                    <div class="text-xs text-gray-500 bg-gray-50 rounded-lg px-3 py-2">
                        <a href="{{ route('officer.club-reports.download', $submission) }}" data-turbo="false" class="text-[#07873e] font-semibold underline">{{ $submission->file_name }}</a>
                        · Submitted {{ $submission->submitted_at->format('M j, Y g:i A') }}
                        @if($submission->submitter) by {{ $submission->submitter->name }}@endif
                        @if($submission->reviewed_at)
                            <br>Reviewed {{ $submission->reviewed_at->format('M j, Y') }}@if($submission->reviewer) by {{ $submission->reviewer->name }}@endif
                        @endif
                    </div>
                    @if($submission->status === 'returned' && $submission->review_remarks)
                        <div class="bg-red-50 border border-red-200 rounded-lg px-3 py-2 text-xs text-red-600">
                            <span class="font-semibold">DSA remarks:</span> {{ $submission->review_remarks }}
                        </div>
                    @endif
                @endif

                <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                    @if($template)
                        <a href="{{ route('resources.documents.download', $template) }}" data-turbo="false"
                           class="inline-flex items-center justify-center border border-[#07873e]/40 text-[#07873e] text-xs font-semibold rounded-lg px-3 py-2 hover:bg-[#07873e]/5 transition-colors">
                            Download Template
                        </a>
                    @endif

                    @if($isPresident && ! $submission?->isLocked())
                        <form method="POST" action="{{ route('officer.club-reports.submit', $key) }}" enctype="multipart/form-data"
                              class="flex flex-col sm:flex-row sm:items-center gap-2 flex-1">
                            @csrf
                            <input type="file" name="file" required accept=".pdf,.doc,.docx,.xls,.xlsx"
                                   class="block w-full min-w-0 text-xs text-gray-600 file:mr-2 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-gray-700">
                            <button type="submit"
                                    class="flex-shrink-0 bg-[#07873e] text-white text-xs font-semibold rounded-lg px-4 py-2 hover:opacity-90 transition-opacity">
                                {{ $submission ? ($submission->status === 'returned' ? 'Resubmit' : 'Replace') : 'Submit to DSA' }}
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @endforeach

    @endif
</div>
@endsection

@extends('layouts.app-dsa')
@section('title', 'Club Reports — DSA')

@php
    // Full literal class strings (Tailwind's scanner can't see interpolated names).
    $statusBadge = [
        'submitted' => 'bg-blue-50 text-blue-600 border-blue-200',
        'accepted'  => 'bg-green-50 text-green-600 border-green-200',
        'returned'  => 'bg-red-50 text-red-600 border-red-200',
    ];
    $filters = [
        ''          => ['All', $counts->sum()],
        'submitted' => ['Awaiting review', $counts->get('submitted', 0)],
        'returned'  => ['Returned', $counts->get('returned', 0)],
        'accepted'  => ['Accepted', $counts->get('accepted', 0)],
    ];
@endphp

@section('content')

<div class="bg-[#07873e] px-5 py-4">
    <h1 class="text-white font-bold text-xl">Club Reports</h1>
    <p class="text-white/60 text-xs mt-0.5">Semester-end reports submitted by club presidents</p>
</div>

<div class="px-4 py-5 space-y-4 max-w-4xl">


    @if(! $semester)
        <p class="text-sm text-gray-400 text-center py-8">No semesters have been set up yet.</p>
    @else

        <form method="GET" action="{{ route('dsa.club-reports.index') }}" class="flex flex-wrap items-center gap-2">
            <label for="semester" class="text-xs text-gray-500">Semester</label>
            <select id="semester" name="semester" onchange="this.form.submit()"
                    class="border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#07873e]/30">
                @foreach($semesters as $option)
                    <option value="{{ $option->id }}" @selected($option->id === $semester->id)>{{ $option->label }}{{ $option->is_current ? ' (current)' : '' }}</option>
                @endforeach
            </select>
            @if($status)<input type="hidden" name="status" value="{{ $status }}">@endif
        </form>

        <div class="flex gap-2 overflow-x-auto pb-1 -mx-1 px-1">
            @foreach($filters as $key => [$label, $count])
                <a href="{{ route('dsa.club-reports.index', array_filter(['semester' => $semester->id, 'status' => $key])) }}"
                   class="flex-shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold border transition-colors
                          {{ ($status ?? '') === $key ? 'bg-[#07873e] text-[#F9A825] border-[#07873e]' : 'bg-white text-gray-600 border-gray-200 hover:border-[#07873e]/40' }}">
                    {{ $label }}
                    <span class="{{ ($status ?? '') === $key ? 'bg-[#F9A825]/20 text-[#F9A825]' : 'bg-gray-100 text-gray-500' }} rounded-full px-1.5 py-0.5 text-[10px] leading-none">{{ $count }}</span>
                </a>
            @endforeach
        </div>

        {{-- Submissions --}}
        <div class="space-y-3">
            @forelse($submissions as $submission)
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 space-y-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-800">{{ $submission->club?->name }}</p>
                            <p class="text-xs text-gray-600 mt-0.5">{{ $submission->type_label }}</p>
                            <p class="text-[11px] text-gray-400 mt-0.5">
                                Submitted {{ $submission->submitted_at->format('M j, Y g:i A') }}
                                @if($submission->submitter) by {{ $submission->submitter->name }}@endif
                            </p>
                        </div>
                        <span class="flex-shrink-0 inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold border {{ $statusBadge[$submission->status] ?? 'bg-gray-50 text-gray-600 border-gray-200' }}">
                            {{ $submission->status_label }}
                        </span>
                    </div>

                    @if($submission->status === 'returned' && $submission->review_remarks)
                        <p class="text-xs text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
                            <span class="font-semibold">Your remarks:</span> {{ $submission->review_remarks }}
                        </p>
                    @endif

                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ route('dsa.club-reports.download', $submission) }}" data-turbo="false"
                           class="border border-[#07873e]/40 text-[#07873e] text-xs font-semibold rounded-lg px-3 py-1.5 hover:bg-[#07873e]/5 transition-colors">
                            Download
                        </a>

                        @if($submission->status === 'submitted')
                            <form method="POST" action="{{ route('dsa.club-reports.accept', $submission) }}"
                                  onsubmit="return confirm('Accept this report? It becomes final and the club can no longer replace it.')">
                                @csrf
                                <button type="submit" class="bg-[#07873e] text-white text-xs font-semibold rounded-lg px-3 py-1.5 hover:opacity-90 transition-opacity">
                                    Accept
                                </button>
                            </form>
                        @endif
                    </div>

                    @if($submission->status === 'submitted')
                        <details class="group">
                            <summary class="text-xs text-red-500 font-semibold cursor-pointer select-none">Return for revision…</summary>
                            <form method="POST" action="{{ route('dsa.club-reports.return', $submission) }}" class="mt-2 space-y-2">
                                @csrf
                                <textarea name="remarks" rows="3" required maxlength="2000"
                                          placeholder="What the club needs to revise…"
                                          class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#07873e]/30"></textarea>
                                <button type="submit" class="border border-red-200 text-red-500 text-xs font-semibold rounded-lg px-3 py-1.5 hover:bg-red-50 transition-colors">
                                    Return to Club
                                </button>
                            </form>
                        </details>
                    @endif
                </div>
            @empty
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm text-center py-10 px-4">
                    <p class="text-gray-400 text-sm">{{ $status ? 'No reports with this status.' : 'No club has submitted a report for ' . $semester->label . ' yet.' }}</p>
                </div>
            @endforelse
        </div>

        {{-- Clubs still missing reports --}}
        @if($incomplete->isNotEmpty())
            <details class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                <summary class="text-sm font-semibold text-gray-700 cursor-pointer">
                    Clubs that have not filed all {{ $typeCount }} reports ({{ $incomplete->count() }})
                </summary>
                <div class="mt-3 divide-y divide-gray-50">
                    @foreach($incomplete as $club)
                        <div class="flex items-center justify-between py-2">
                            <p class="text-xs text-gray-700">{{ $club->name }}</p>
                            <p class="text-[11px] text-gray-400">{{ $club->filed_count }} of {{ $typeCount }} filed</p>
                        </div>
                    @endforeach
                </div>
            </details>
        @endif

    @endif
</div>
@endsection

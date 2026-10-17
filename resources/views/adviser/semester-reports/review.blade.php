@extends('layouts.app-adviser')
@section('title', $report->semester->label . ' Narrative Report')
@section('page-title', 'Semester Report')

@section('content')

@php
    $sectionLabels = \App\Models\SemesterNarrativeReport::SECTIONS;
    $documentTypes = \App\Services\NarrativeDocuments\DocumentBuilder::DOCUMENTS;
    // Activities the AI did not draft: started from a template of their records, or blank.
    $undrafted     = collect($data['activities'])->filter(fn ($a) => $report->draftSource($a['id']) !== 'ai');
    $templated     = $undrafted->filter(fn ($a) => $report->draftSource($a['id']) === \App\Services\NarrativeTemplate::SOURCE);
@endphp

<div class="bg-[#07873e] px-5 pt-5 pb-6">
    <a href="{{ route('adviser.semester-reports.index') }}" class="text-white/70 hover:text-white mb-4 inline-block" aria-label="Back to semester reports">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
        </svg>
    </a>
    <div class="flex items-start justify-between gap-3">
        <h1 class="text-white font-bold text-xl leading-snug flex-1">{{ $report->semester->label }} Narrative Report</h1>
        <x-status-badge :status="$report->badgeStatus()" />
    </div>
    <p class="text-white/60 text-xs mt-2">{{ $report->club->name }} · {{ $data['start'] }} – {{ $data['end'] }}</p>
</div>

<div class="px-4 py-5 space-y-4">

    @error('status')
        <div class="bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-red-600 text-sm">{{ $message }}</div>
    @enderror

    @include('adviser.semester-reports.partials.returned')

    @if($undrafted->isEmpty())
        <div class="flex items-center gap-2 px-3 py-2 bg-purple-50 rounded-xl border border-purple-100">
            <span class="text-purple-500 text-sm">✦</span>
            <span class="text-xs text-purple-600 font-medium">AI-drafted from the activity records and the photos the officers attached — review and edit every section before submitting</span>
        </div>
    @else
        <div class="px-3 py-2 bg-amber-50 rounded-xl border border-amber-100">
            <p class="text-xs text-amber-700 font-medium">⚠ AI Unavailable — Manual Draft Required for {{ $undrafted->count() === count($data['activities']) ? 'every activity' : $undrafted->count() . ' ' . str('activity')->plural($undrafted->count()) }}:</p>
            <p class="text-xs text-amber-700 mt-0.5">{{ $undrafted->pluck('title')->implode(', ') }}</p>
            @if($templated->isNotEmpty())
                <p class="text-xs text-amber-700 mt-1.5">
                    {{ $templated->count() === $undrafted->count() ? 'They start' : $templated->count() . ' of them start' }}
                    from a template written from the activity records, so no figure in them is guessed. Check and complete
                    them, or draft them with AI when it is available. ClubSync also asks the AI again by itself for any you
                    leave unchanged, and tells you when it does.
                </p>
            @endif
        </div>
    @endif

    {{-- The five documents --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
        <div class="flex flex-wrap items-baseline justify-between gap-2 mb-1">
            <p class="text-sm font-semibold text-gray-800">The five DSA documents</p>
            @unless($report->isSubmitted())
                <p class="text-[11px] text-gray-400">Previews show your last saved edits</p>
            @endunless
        </div>
        <p class="text-xs text-gray-500 mb-3">Word files can be edited before printing; each also downloads as a PDF. They carry the Saint Columban College banner, the GSC and SCC logos and the club's logo.</p>

        <div class="divide-y divide-gray-100">
            @foreach($documentTypes as $key => $type)
                <div class="flex flex-wrap items-center justify-between gap-2 py-2.5">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-gray-800">{{ $type['label'] }}</p>
                        <p class="text-[11px] text-gray-500">{{ $documents[$key] ?? '' }} · {{ $type['prepared_by'] }}</p>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <a href="{{ route('adviser.semester-reports.document', [$report, $key, 'docx']) }}"
                           class="text-xs font-semibold text-[#07873e] border border-[#07873e]/30 rounded-lg px-2.5 py-1 hover:bg-[#07873e]/5">Word</a>
                        <a href="{{ route('adviser.semester-reports.document', [$report, $key, 'pdf']) }}"
                           class="text-xs font-semibold text-[#07873e] border border-[#07873e]/30 rounded-lg px-2.5 py-1 hover:bg-[#07873e]/5">PDF</a>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="flex flex-wrap gap-2 mt-3">
            <a href="{{ route('adviser.semester-reports.pdf', $report) }}"
               class="text-xs font-semibold bg-[#07873e] text-[#F9A825] rounded-lg px-3 py-2 hover:opacity-90">Complete report (PDF)</a>
            <a href="{{ route('adviser.semester-reports.bundle', $report) }}"
               class="text-xs font-semibold border border-[#07873e] text-[#07873e] rounded-lg px-3 py-2 hover:bg-[#07873e]/5">All Word files (ZIP)</a>
        </div>
    </div>

    {{-- What the records say --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
        <p class="text-xs text-gray-400 font-medium mb-3">Activity records this semester</p>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
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
                <p class="text-[11px] text-amber-700 mt-2">These compare the sections with the activity records and attachments. Save your edits to re-check.</p>
            </div>
        @endif

        <form method="POST" action="{{ route('adviser.semester-reports.update', $report) }}" class="space-y-3">
            @csrf
            @method('PATCH')

            @foreach($data['activities'] as $i => $activity)
                @php
                    $isUndrafted = $undrafted->contains('id', $activity['id']);
                    $isTemplated = $templated->contains('id', $activity['id']);
                @endphp
                <details id="activity-{{ $activity['id'] }}" class="bg-white rounded-xl border border-gray-100 shadow-sm p-4"
                         @if($i === 0 || $errors->any() || (int) session('open_activity') === (int) $activity['id']) open @endif>
                    <summary class="cursor-pointer">
                        <span class="text-sm font-semibold text-gray-800">{{ $i + 1 }}. {{ $activity['title'] }}</span>
                        @if($isTemplated)
                            <span class="ml-1 text-[11px] font-semibold text-amber-700 bg-amber-50 border border-amber-100 rounded-lg px-2 py-0.5">Template draft</span>
                        @elseif($isUndrafted)
                            <span class="ml-1 text-[11px] font-semibold text-amber-700 bg-amber-50 border border-amber-100 rounded-lg px-2 py-0.5">Manual draft required</span>
                        @endif
                        <span class="block text-xs text-gray-500 mt-0.5">
                            {{ $activity['type'] }} · {{ $activity['date'] }} · {{ $activity['venue'] }} ·
                            @if($activity['attendance_recorded'])
                                {{ $activity['attended'] }} attended of {{ $activity['expected'] }} expected
                            @else
                                attendance not recorded
                            @endif
                        </span>
                    </summary>

                    <div class="mt-3 space-y-3">
                        @foreach($sectionLabels as $key => $label)
                            @continue($key === 'minutes' && empty($activity['is_meeting']))
                            @php $field = "sections.{$activity['id']}.{$key}"; @endphp
                            <div>
                                <label for="s-{{ $activity['id'] }}-{{ $key }}" class="text-xs text-gray-500 font-medium block mb-1">
                                    {{ $label }}
                                    <span class="font-normal text-gray-400">
                                        · {{ match ($key) {
                                            'introduction'    => 'Accomplishment Report and Financial Report',
                                            'rationale'       => 'Accomplishment Report',
                                            'minutes'         => 'Minutes of Meetings',
                                            'recommendations' => 'Accomplishment Report, Annex F',
                                        } }}
                                    </span>
                                </label>
                                <textarea id="s-{{ $activity['id'] }}-{{ $key }}" name="sections[{{ $activity['id'] }}][{{ $key }}]"
                                          rows="{{ in_array($key, ['introduction', 'minutes'], true) ? 7 : 4 }}"
                                          class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm text-gray-700 leading-relaxed focus:outline-none focus:ring-2 focus:ring-[#07873e]/30">{{ old($field, $report->section($activity['id'], $key)) }}</textarea>
                            </div>
                        @endforeach

                        <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
                            <p class="text-[11px] text-gray-400 flex-1 min-w-[12rem]">
                                @if($isTemplated)
                                    The AI could not draft this activity, so it starts from a template of its records.
                                    {{ $aiAvailable ? 'Ask the AI again, or edit it yourself.' : 'Edit it yourself; the AI is unavailable right now.' }}
                                @elseif($isUndrafted)
                                    The AI could not draft this activity. Fill it in from its records, then edit it{{ $aiAvailable ? ', or ask the AI again' : '' }}.
                                @elseif($aiAvailable)
                                    Replaces these sections with a new AI draft from the records.
                                @endif
                            </p>
                            <div class="flex flex-wrap gap-2">
                                @if($isUndrafted)
                                    <button type="submit" formaction="{{ route('adviser.semester-reports.template', [$report, $activity['id']]) }}" formnovalidate
                                            onclick="return confirm('Fill this activity in from its records? Its sections are replaced by the template. Your edits elsewhere on this page are saved first.')"
                                            class="text-xs font-semibold text-[#07873e] border border-[#07873e]/30 rounded-lg px-3 py-1.5 hover:bg-[#07873e]/5 transition-colors">
                                        Fill from records
                                    </button>
                                @endif
                                @if($aiAvailable)
                                    <button type="submit" formaction="{{ route('adviser.semester-reports.redraft', [$report, $activity['id']]) }}" formnovalidate
                                            onclick="return confirm('{{ $isUndrafted ? 'Draft this activity with AI?' : 'Redraft this activity with AI? Its sections are replaced by the new draft.' }} Your edits elsewhere on this page are saved first.')"
                                            class="text-xs font-semibold text-purple-700 border border-purple-200 bg-purple-50 rounded-lg px-3 py-1.5 hover:bg-purple-100 transition-colors">
                                        ✦ {{ $isUndrafted ? 'Draft with AI' : 'Redraft with AI' }}
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                </details>
            @endforeach

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <button type="submit" name="intent" value="save"
                        class="flex items-center justify-center border-2 border-[#07873e] text-[#07873e] font-semibold text-sm rounded-xl px-5 py-3.5 hover:bg-[#07873e]/5 transition-colors">
                    Save Changes
                </button>
                <button type="submit" name="intent" value="submit"
                        class="flex items-center justify-center bg-[#07873e] text-[#F9A825] font-semibold text-sm rounded-xl px-5 py-3.5 hover:opacity-90 transition-opacity"
                        onclick="return confirm('Submit the five documents to the DSA? They can no longer be edited afterwards.')">
                    {{ $report->isReturned() ? 'Resubmit to DSA' : 'Approve & Submit to DSA' }}
                </button>
            </div>
        </form>

    @else

        <div class="bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm text-gray-600">
            Submitted to the DSA · {{ $report->submitted_at?->format('F j, Y g:i A') }}
            @if($report->reviewer) by {{ $report->reviewer->name }} @endif
            @if($report->returned_at)
                <span class="block text-xs text-gray-500 mt-1">Revised after the DSA returned it on {{ $report->returned_at->format('F j, Y') }}.</span>
            @endif
        </div>

    @endif

</div>
@endsection

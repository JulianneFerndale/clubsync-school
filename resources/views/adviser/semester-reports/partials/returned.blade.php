{{-- The DSA's revision request, shown while the report is back with the adviser. --}}
@if($report->isReturned())
    <div class="bg-orange-50 border border-orange-200 rounded-xl px-4 py-3">
        <p class="text-sm font-semibold text-orange-800">Returned by the DSA for revision</p>
        <p class="text-sm text-orange-900 mt-1 whitespace-pre-line">{{ $report->return_remarks }}</p>
        <p class="text-[11px] text-orange-700 mt-2">
            {{ $report->returner?->name ?? 'The DSA' }} · {{ $report->returned_at->format('F j, Y g:i A') }}.
            @if($report->isLegacy())
                Revise the narrative below, then submit the report again.
            @else
                Revise the sections below, or have the AI redraft an activity, then submit the report again.
                Until you do, the officers can still attach missing photos and records to its activities.
            @endif
        </p>
    </div>
@endif

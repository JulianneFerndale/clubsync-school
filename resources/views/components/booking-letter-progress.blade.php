{{--
    Where an activity's room booking letter stands (POLICY.md §Event Creation): signed and
    sent by an officer, approved by the club adviser, then approved by the Vice President
    for Administration, which confirms it. Read from the activity, so every role sees the
    same three steps.
    event: a ClubActivity with a booking letter
--}}
@props(['event'])

@php
    $officer = $event->letterSignature('officer');
    $vpaSigned = $event->letterSignature('vpa');
    $adviserSigned = $event->letterSignature('adviser');
    $vpaReturned = $event->wasRejectedByVpa();
    $adviserReturned = $event->approval_status === 'rejected' && ! $vpaReturned;

    $steps = [
        [
            'title' => 'Signed and sent by the officer',
            'state' => $officer ? 'done' : 'waiting',
            'note'  => $officer
                ? ($officer['name'] . ' · ' . \Carbon\Carbon::parse($officer['signed_at'])->format('M j, Y g:i A'))
                : null,
        ],
        [
            'title' => 'Club adviser',
            'state' => match (true) {
                $adviserReturned                               => 'returned',
                $event->approval_status === 'pending_approval' => 'current',
                $adviserSigned !== null, $event->approval_status === 'approved' => 'done',
                default                                        => 'waiting',
            },
            'note'  => match (true) {
                $adviserReturned => 'Not approved ' . ($event->updated_at?->format('M j, Y g:i A') ?? ''),
                $event->approval_status === 'pending_approval' => 'Waiting for the adviser to approve',
                $adviserSigned !== null => 'Approved and signed ' . \Carbon\Carbon::parse($adviserSigned['signed_at'])->format('M j, Y g:i A'),
                $event->approval_status === 'approved' => 'Approved',
                default => null,
            },
        ],
        [
            'title' => 'Vice President for Administration',
            'state' => match (true) {
                $vpaReturned                        => 'returned',
                $event->vpa_decision === 'approved' => 'done',
                $event->isAwaitingVpa()             => 'current',
                default                             => 'waiting',
            },
            'note'  => match (true) {
                $vpaReturned => 'Returned ' . $event->vpa_reviewed_at?->format('M j, Y g:i A'),
                $event->vpa_decision === 'approved' => 'Approved and signed ' . \Carbon\Carbon::parse($vpaSigned['signed_at'] ?? $event->vpa_reviewed_at)->format('M j, Y g:i A'),
                $event->isAwaitingVpa() => 'Reviewing the letter',
                default => 'After the adviser approves',
            },
        ],
    ];

    $tones = [
        'done'     => ['bg-[#07873e] text-white', 'text-gray-800'],
        'current'  => ['bg-[#F9A825] text-white', 'text-gray-800'],
        'returned' => ['bg-red-500 text-white', 'text-red-700'],
        'waiting'  => ['bg-gray-200 text-gray-500', 'text-gray-400'],
    ];
@endphp

<ol {{ $attributes->merge(['class' => 'space-y-3']) }}>
    @foreach($steps as $i => $step)
        <li class="flex items-start gap-3">
            <span class="w-6 h-6 rounded-full flex-shrink-0 flex items-center justify-center text-[11px] font-bold {{ $tones[$step['state']][0] }}">
                @if($step['state'] === 'done')
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                @elseif($step['state'] === 'returned')
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                @else
                    {{ $i + 1 }}
                @endif
            </span>
            <div class="min-w-0">
                <p class="text-sm font-semibold {{ $tones[$step['state']][1] }}">{{ $step['title'] }}</p>
                @if($step['note'])
                    <p class="text-xs text-gray-500 mt-0.5">{{ $step['note'] }}</p>
                @endif
            </div>
        </li>
    @endforeach
</ol>

@if($event->approval_status === 'rejected' && $event->review_remarks)
    <div class="mt-3 bg-red-50 border border-red-200 rounded-lg px-3 py-2 text-xs text-red-700">
        <span class="font-semibold">{{ $vpaReturned ? 'VPA' : 'Adviser' }} remarks:</span> {{ $event->review_remarks }}
    </div>
@endif

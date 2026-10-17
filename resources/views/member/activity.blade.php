@extends('layouts.app-member')
@section('title', 'Activity — ClubSync')
@section('page-title', 'My Activity')

@section('live-refresh', 'any')
@section('content')

{{-- Header --}}
<div class="bg-[#07873e] px-5 pt-12 pb-5">
    <h1 class="text-white font-bold text-xl">My Activity</h1>
    <p class="text-white/60 text-xs mt-1">Upcoming activities, attendance history and fee status</p>
</div>

{{-- Tabs.
     Each tab is a link back to this page with ?tab=…, so the open panel is part of the
     URL: Turbo swaps the panel in without a reload, back/forward return to the tab you
     were on, and a live refresh re-fetches the tab you are actually reading. --}}
@php
    $tabs = [
        'upcoming'   => ['label' => 'Upcoming',   'count' => $upcoming->count(),    'alert' => false],
        'attendance' => ['label' => 'Attendance', 'count' => $attendanceCount,      'alert' => false],
        'fees'       => ['label' => 'Fees',       'count' => $pendingFees->count(), 'alert' => true],
    ];
@endphp

<div class="flex bg-white border-b border-gray-100">
    @foreach($tabs as $key => $meta)
        @php $active = $tab === $key; @endphp
        <a href="{{ route('member.activity', $key === 'upcoming' ? [] : ['tab' => $key]) }}"
           data-turbo-action="replace"
           @if($active) aria-current="page" @endif
           class="flex-1 py-3 text-sm font-semibold text-center border-b-2 transition-colors
                  {{ $active ? 'text-[#07873e] border-[#F9A825]' : 'text-gray-400 border-transparent hover:text-gray-600' }}">
            {{ $meta['label'] }}
            @if($meta['count'] > 0)
                <span class="ml-1 inline-block px-1.5 py-0.5 rounded-full text-[10px] font-bold leading-none align-middle
                             {{ $meta['alert']
                                 ? 'bg-red-50 text-red-500'
                                 : ($active ? 'bg-[#07873e]/10 text-[#07873e]' : 'bg-gray-100 text-gray-400') }}">
                    {{ $meta['count'] }}
                </span>
            @endif
        </a>
    @endforeach
</div>

@if($tab === 'upcoming')

    {{-- Upcoming: confirmed activities of the member's clubs, soonest first --}}
    <div class="px-4 py-5">
        @if($upcoming->isEmpty())
            <div class="text-center py-14">
                <div class="w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-3">
                    <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
                    </svg>
                </div>
                <p class="text-sm text-gray-400 font-medium">No upcoming activities</p>
                <p class="text-xs text-gray-300 mt-1">New activities from your clubs appear here as soon as they are confirmed</p>
            </div>
        @else
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden divide-y divide-gray-50">
                @foreach($upcoming as $event)
                    <div class="flex items-start gap-3 px-4 py-3.5">
                        <div class="bg-[#F9A825] rounded-xl px-2.5 py-2 flex flex-col items-center min-w-[44px] flex-shrink-0">
                            <span class="text-white font-bold text-lg leading-none">{{ $event->date->format('d') }}</span>
                            <span class="text-white text-[9px] font-semibold uppercase">{{ $event->date->format('M') }}</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-800 truncate">{{ $event->title }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                @if($event->date->isToday())
                                    <span class="font-semibold text-[#07873e]">Today</span> ·
                                @else
                                    {{ $event->date->format('D') }} ·
                                @endif
                                {{ \Carbon\Carbon::parse($event->time_start)->format('g:i A') }}–{{ \Carbon\Carbon::parse($event->time_end)->format('g:i A') }}
                            </p>
                            <p class="text-xs text-gray-400 mt-0.5 truncate">{{ $event->venue }}</p>
                            @if($event->club)
                                <a href="{{ route('member.clubs.show', $event->club) }}"
                                   class="inline-block mt-1 px-2 py-0.5 bg-[#07873e]/10 text-[#07873e] text-[10px] font-semibold rounded-full hover:bg-[#07873e]/20 transition-colors">
                                    {{ $event->club->acronym ?? $event->club->name }}
                                </a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

@elseif($tab === 'attendance')

    {{-- Attendance --}}
    <div class="px-4 py-5 space-y-5">
        @if($attendanceByMonth->isEmpty())
            <div class="text-center py-14">
                <div class="w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-3">
                    <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                    </svg>
                </div>
                <p class="text-sm text-gray-400 font-medium">No attendance records yet</p>
                <p class="text-xs text-gray-300 mt-1">Your check-ins will appear here</p>
                <a href="{{ route('member.clubs.index') }}"
                   class="inline-block mt-4 px-4 py-2 bg-[#07873e] text-[#F9A825] text-xs font-semibold rounded-xl">
                    Browse clubs
                </a>
            </div>
        @else
            @foreach($attendanceByMonth as $monthLabel => $records)
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-2 px-1">
                        {{ $monthLabel }}
                    </p>
                    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden divide-y divide-gray-50">
                        @foreach($records as $record)
                            @php $event = $record->activity; @endphp
                            <div class="flex items-start gap-3 px-4 py-3.5 {{ $event ? '' : 'opacity-60' }}">

                                {{-- Date badge --}}
                                <div class="bg-[#07873e] rounded-xl px-2.5 py-2 flex flex-col items-center min-w-[44px] flex-shrink-0">
                                    @if($event?->date)
                                        <span class="text-white font-bold text-lg leading-none">{{ $event->date->format('d') }}</span>
                                        <span class="text-white text-[9px] font-semibold uppercase">{{ $event->date->format('D') }}</span>
                                    @else
                                        <span class="text-white/60 font-bold text-sm leading-none mt-1">?</span>
                                    @endif
                                </div>

                                {{-- Info --}}
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-gray-900 truncate">
                                        {{ $event?->title ?? 'Deleted Activity' }}
                                    </p>

                                    @if($event?->club)
                                        <a href="{{ route('member.clubs.show', $event->club) }}"
                                           class="inline-block mt-1 px-2 py-0.5 bg-[#07873e]/10 text-[#07873e] text-[10px] font-semibold rounded-full hover:bg-[#07873e]/20 transition-colors">
                                            {{ $event->club->acronym ?? $event->club->name }}
                                        </a>
                                    @elseif(! $event)
                                        <p class="text-xs text-gray-500 mt-0.5">This activity no longer exists</p>
                                    @endif

                                    {{-- Check in/out times --}}
                                    <div class="flex items-center gap-3 mt-1.5">
                                        @if($record->time_in)
                                            <span class="flex items-center gap-1 text-[10px] font-semibold text-green-600">
                                                <span class="w-1.5 h-1.5 rounded-full bg-green-500 inline-block"></span>
                                                IN {{ $record->time_in->format('g:i A') }}
                                            </span>
                                        @endif
                                        @if($record->time_out)
                                            <span class="flex items-center gap-1 text-[10px] font-semibold text-[#F9A825]">
                                                <span class="w-1.5 h-1.5 rounded-full bg-[#F9A825] inline-block"></span>
                                                OUT {{ $record->time_out->format('g:i A') }}
                                            </span>
                                        @elseif($record->time_in)
                                            <span class="text-[10px] font-semibold text-gray-300">NO TIME OUT</span>
                                        @endif
                                    </div>
                                </div>

                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        @endif
    </div>

@else

    {{-- Fees --}}
    <div class="px-4 py-5 space-y-5">

        {{-- What is still owed, above the list of what it is made of --}}
        @if($pendingFees->isNotEmpty())
            <div class="bg-white rounded-xl border border-red-100 shadow-sm px-4 py-3.5 flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest">Total due</p>
                    <p class="text-[11px] text-gray-400 mt-0.5">
                        {{ $pendingFees->count() }} unpaid {{ \Illuminate\Support\Str::plural('fee', $pendingFees->count()) }}
                    </p>
                </div>
                <p class="text-xl font-bold text-red-500">₱{{ number_format($amountDue, 2) }}</p>
            </div>
        @endif

        {{-- Pending --}}
        <div>
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-2 px-1">Pending</p>
            @if($pendingFees->isEmpty())
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-5 text-center">
                    <p class="text-sm text-gray-400">No pending fees.</p>
                </div>
            @else
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden divide-y divide-gray-50">
                    @foreach($pendingFees as $payment)
                        @php $overdue = $payment->fee?->due_date?->isPast() ?? false; @endphp
                        <div class="flex items-center justify-between gap-3 px-4 py-3.5">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-gray-800 truncate">{{ $payment->fee?->title ?? 'Fee' }}</p>
                                <p class="text-xs mt-0.5 {{ $overdue ? 'text-red-500 font-semibold' : 'text-gray-400' }}">
                                    @if($payment->fee?->due_date)
                                        {{ $overdue ? 'Overdue —' : 'Due' }} {{ $payment->fee->due_date->format('M j, Y') }}
                                    @else
                                        No due date
                                    @endif
                                </p>
                                @if($payment->fee?->isPostponed())
                                    <p class="text-[11px] text-amber-700 mt-0.5">
                                        <span class="inline-block px-1.5 py-px bg-amber-50 border border-amber-100 rounded-full font-semibold">Postponed</span>
                                        @if($payment->fee->previous_due_date)
                                            from {{ $payment->fee->previous_due_date->format('M j') }}
                                        @endif
                                        @if(filled($payment->fee->status_reason))
                                            — {{ $payment->fee->status_reason }}
                                        @endif
                                    </p>
                                @endif
                                @if($payment->fee?->club)
                                    <a href="{{ route('member.clubs.show', $payment->fee->club) }}"
                                       class="inline-block mt-1 px-2 py-0.5 bg-[#07873e]/10 text-[#07873e] text-[10px] font-semibold rounded-full hover:bg-[#07873e]/20 transition-colors">
                                        {{ $payment->fee->club->acronym ?? $payment->fee->club->name }}
                                    </a>
                                @endif
                            </div>
                            <div class="flex-shrink-0 text-right">
                                <p class="text-sm font-bold text-red-500">₱{{ number_format((float) ($payment->fee?->amount ?? 0), 2) }}</p>
                                <span class="inline-block mt-0.5 px-2 py-0.5 bg-red-50 text-red-500 text-[10px] font-semibold rounded-full">Unpaid</span>
                            </div>
                        </div>
                    @endforeach
                </div>
                <p class="text-[11px] text-gray-400 mt-2 px-1">
                    Pay your club treasurer in person. Once they mark it paid, your official receipt appears below.
                </p>
            @endif
        </div>

        {{-- Paid --}}
        <div>
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-2 px-1">Paid</p>
            @if($paidFees->isEmpty())
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-5 text-center">
                    <p class="text-sm text-gray-400">No paid fees yet.</p>
                </div>
            @else
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden divide-y divide-gray-50">
                    @foreach($paidFees as $payment)
                        <div class="flex items-center justify-between gap-3 px-4 py-3.5">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-gray-800 truncate">{{ $payment->fee?->title ?? 'Fee' }}</p>
                                <p class="text-xs text-gray-400 mt-0.5">
                                    @if($payment->confirmed_at)
                                        Paid {{ $payment->confirmed_at->format('M j, Y') }}
                                    @endif
                                    @if($payment->receipt_number)
                                        · {{ $payment->receipt_number }}
                                    @endif
                                </p>
                                @if($payment->fee?->club)
                                    <a href="{{ route('member.clubs.show', $payment->fee->club) }}"
                                       class="inline-block mt-1 px-2 py-0.5 bg-[#07873e]/10 text-[#07873e] text-[10px] font-semibold rounded-full hover:bg-[#07873e]/20 transition-colors">
                                        {{ $payment->fee->club->acronym ?? $payment->fee->club->name }}
                                    </a>
                                @endif
                            </div>
                            <div class="flex-shrink-0 text-right">
                                <p class="text-sm font-bold text-gray-600">₱{{ number_format((float) ($payment->fee?->amount ?? 0), 2) }}</p>
                                <span class="inline-block mt-0.5 px-2 py-0.5 bg-green-50 text-green-600 text-[10px] font-semibold rounded-full">Paid</span>
                                @if($payment->hasReceipt())
                                    <a href="{{ route('member.activity.receipt', $payment) }}"
                                       class="block mt-1.5 border border-[#07873e]/40 text-[#07873e] text-[10px] font-semibold rounded-lg px-3 py-1.5 hover:bg-[#07873e]/5 transition-colors">
                                        Receipt
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Cancelled: no longer owed; a payment already made keeps its receipt --}}
        @if($cancelledFees->isNotEmpty())
            <div>
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-2 px-1">Cancelled</p>
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden divide-y divide-gray-50">
                    @foreach($cancelledFees as $payment)
                        <div class="flex items-center justify-between gap-3 px-4 py-3.5">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-gray-500 truncate">{{ $payment->fee->title }}</p>
                                <p class="text-xs text-gray-400 mt-0.5">
                                    Cancelled{{ $payment->fee->status_changed_at ? ' ' . $payment->fee->status_changed_at->format('M j, Y') : '' }}@if(filled($payment->fee->status_reason)) — {{ $payment->fee->status_reason }}@endif
                                </p>
                                @if($payment->isPaid())
                                    <p class="text-[11px] text-amber-700 mt-0.5">You had already paid this. Ask your club treasurer about a refund.</p>
                                @endif
                                @if($payment->fee->club)
                                    <a href="{{ route('member.clubs.show', $payment->fee->club) }}"
                                       class="inline-block mt-1 px-2 py-0.5 bg-[#07873e]/10 text-[#07873e] text-[10px] font-semibold rounded-full hover:bg-[#07873e]/20 transition-colors">
                                        {{ $payment->fee->club->acronym ?? $payment->fee->club->name }}
                                    </a>
                                @endif
                            </div>
                            <div class="flex-shrink-0 text-right">
                                <p class="text-sm font-bold text-gray-400 {{ $payment->isPaid() ? '' : 'line-through' }}">₱{{ number_format((float) $payment->fee->amount, 2) }}</p>
                                <span class="inline-block mt-0.5 px-2 py-0.5 bg-gray-100 text-gray-500 text-[10px] font-semibold rounded-full">Cancelled</span>
                                @if($payment->hasReceipt())
                                    <a href="{{ route('member.activity.receipt', $payment) }}"
                                       class="block mt-1.5 border border-[#07873e]/40 text-[#07873e] text-[10px] font-semibold rounded-lg px-3 py-1.5 hover:bg-[#07873e]/5 transition-colors">
                                        Receipt
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

    </div>

@endif

@endsection

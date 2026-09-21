@extends('layouts.app-member')
@section('title', 'Activity — ClubSync')

@section('live-refresh', 'any')
@section('content')

{{-- Header --}}
<div class="bg-[#07873e] px-5 pt-12 pb-5">
    <h1 class="text-white font-bold text-xl">My Activity</h1>
    <p class="text-white/60 text-xs mt-1">Attendance history and fee status</p>
</div>

{{-- Tabs --}}
<div x-data="{ tab: 'attendance' }" class="flex flex-col min-h-0">

    <div class="flex bg-white border-b border-gray-100 sticky top-0 z-10">
        <button @click="tab = 'attendance'"
                :class="tab === 'attendance' ? 'text-[#07873e] border-b-2 border-[#F9A825]' : 'text-gray-400'"
                class="flex-1 py-3 text-sm font-semibold transition-colors">
            Attendance
        </button>
        <button @click="tab = 'fees'"
                :class="tab === 'fees' ? 'text-[#07873e] border-b-2 border-[#F9A825]' : 'text-gray-400'"
                class="flex-1 py-3 text-sm font-semibold transition-colors">
            Fees
        </button>
    </div>

    {{-- Attendance tab --}}
    <div x-show="tab === 'attendance'" class="px-4 py-5 space-y-5">
        @if($attendanceRecords->isEmpty())
            <div class="text-center py-14">
                <div class="w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-3">
                    <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                    </svg>
                </div>
                <p class="text-sm text-gray-400 font-medium">No attendance records yet</p>
                <p class="text-xs text-gray-300 mt-1">Your check-ins will appear here</p>
            </div>
        @else
            @foreach($attendanceRecords as $monthLabel => $records)
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
                                    <p class="text-xs text-gray-500 mt-0.5">
                                        {{ $event?->club?->acronym ?? $event?->club?->name ?? ($event ? '' : 'This activity no longer exists') }}
                                    </p>

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

    {{-- Fees tab --}}
    <div x-show="tab === 'fees'" class="px-4 py-5 space-y-5">

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
                        <div class="flex items-center justify-between px-4 py-3.5">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-gray-800 truncate">{{ $payment->fee?->title }}</p>
                                <p class="text-xs text-gray-400 mt-0.5">
                                    {{ $payment->fee?->club?->acronym ?? $payment->fee?->club?->name ?? '' }}
                                    @if($payment->fee?->due_date)
                                        · Due {{ $payment->fee->due_date->format('M j, Y') }}
                                    @endif
                                </p>
                            </div>
                            <div class="flex-shrink-0 ml-3 text-right">
                                <p class="text-sm font-bold text-red-500">₱{{ number_format($payment->fee?->amount, 2) }}</p>
                                <span class="inline-block mt-0.5 px-2 py-0.5 bg-red-50 text-red-500 text-[10px] font-semibold rounded-full">Unpaid</span>
                            </div>
                        </div>
                    @endforeach
                </div>
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
                        <div class="flex items-center justify-between px-4 py-3.5">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-gray-800 truncate">{{ $payment->fee?->title }}</p>
                                <p class="text-xs text-gray-400 mt-0.5">
                                    {{ $payment->fee?->club?->acronym ?? $payment->fee?->club?->name ?? '' }}
                                    @if($payment->confirmed_at)
                                        · Paid {{ $payment->confirmed_at->format('M j, Y') }}
                                    @endif
                                </p>
                            </div>
                            <div class="flex-shrink-0 ml-3 text-right">
                                <p class="text-sm font-bold text-gray-600">₱{{ number_format($payment->fee?->amount, 2) }}</p>
                                <span class="inline-block mt-0.5 px-2 py-0.5 bg-green-50 text-green-600 text-[10px] font-semibold rounded-full">Paid</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

    </div>

</div>

@endsection

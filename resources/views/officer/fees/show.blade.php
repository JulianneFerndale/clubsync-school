@extends('layouts.app-officer')
@section('title', $fee->title)
@section('club-name', $club?->name ?? 'ClubSync')

@section('content')

{{-- Header --}}
<div class="bg-[#07873e] px-5 pt-5 pb-6">
    <a href="{{ route('officer.fees.index') }}" class="text-white/70 hover:text-white mb-4 inline-block">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
        </svg>
    </a>
    <h1 class="text-white font-bold text-xl leading-snug">{{ $fee->title }}</h1>
    <p class="text-white/60 text-xs mt-1">
        {{ $fee->academic_period }} · Due {{ $fee->due_date->format('F j, Y') }}
    </p>
</div>

<div class="px-4 py-5 space-y-4">

    {{-- Where the fee stands, when it is not simply being collected --}}
    @if($fee->isCancelled())
        <div class="bg-gray-50 border border-gray-200 rounded-xl px-4 py-3">
            <p class="text-sm font-semibold text-gray-700">
                Cancelled{{ $fee->status_changed_at ? ' on ' . $fee->status_changed_at->format('F j, Y') : '' }}
            </p>
            @if(filled($fee->status_reason))
                <p class="text-xs text-gray-500 mt-0.5">{{ $fee->status_reason }}</p>
            @endif
            <p class="text-[11px] text-gray-400 mt-1.5">
                Nobody owes this fee any more. Payments already recorded keep their receipts; those members were told to ask you about a refund.
            </p>
        </div>
    @elseif($fee->isPostponed())
        <div class="bg-amber-50 border border-amber-100 rounded-xl px-4 py-3">
            <p class="text-sm font-semibold text-amber-800">
                Postponed{{ $fee->previous_due_date ? ' from ' . $fee->previous_due_date->format('F j, Y') : '' }} to {{ $fee->due_date->format('F j, Y') }}
            </p>
            @if(filled($fee->status_reason))
                <p class="text-xs text-amber-700 mt-0.5">{{ $fee->status_reason }}</p>
            @endif
        </div>
    @endif

    {{-- Postpone or cancel. Members are notified either way. --}}
    @unless($fee->isCancelled())
        <div class="grid grid-cols-2 gap-2">
            <details class="group col-span-2 sm:col-span-1 bg-white rounded-xl border border-gray-100 shadow-sm" @if($errors->has('due_date')) open @endif>
                <summary class="cursor-pointer list-none px-4 py-3 text-sm font-semibold text-[#07873e] flex items-center justify-between">
                    Postpone fee
                    <svg class="w-4 h-4 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                </summary>
                <form method="POST" action="{{ route('officer.fees.postpone', $fee) }}" class="px-4 pb-4 space-y-3">
                    @csrf
                    @php $earliest = max($fee->due_date->copy()->addDay(), today()); @endphp
                    <div>
                        <label for="postpone-due" class="block text-xs font-semibold text-gray-600 mb-1">New due date</label>
                        <input id="postpone-due" type="date" name="due_date" required min="{{ $earliest->toDateString() }}"
                               value="{{ old('due_date') }}"
                               class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-[#07873e] focus:ring-[#07873e]">
                        @error('due_date') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="postpone-reason" class="block text-xs font-semibold text-gray-600 mb-1">Reason <span class="font-normal text-gray-400">(optional, shown to members)</span></label>
                        <input id="postpone-reason" type="text" name="reason" maxlength="500" value="{{ old('reason') }}"
                               class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-[#07873e] focus:ring-[#07873e]">
                    </div>
                    <button type="submit" class="w-full bg-[#07873e] text-white text-sm font-semibold rounded-lg py-2 hover:opacity-90">
                        Postpone and notify members
                    </button>
                </form>
            </details>

            <details class="group col-span-2 sm:col-span-1 bg-white rounded-xl border border-gray-100 shadow-sm">
                <summary class="cursor-pointer list-none px-4 py-3 text-sm font-semibold text-red-500 flex items-center justify-between">
                    Cancel fee
                    <svg class="w-4 h-4 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                </summary>
                <form method="POST" action="{{ route('officer.fees.cancel', $fee) }}" class="px-4 pb-4 space-y-3">
                    @csrf
                    <p class="text-xs text-gray-500">
                        Nobody will owe this fee any more, and it cannot be undone. Members who already paid keep their receipts and are told to ask you about a refund.
                    </p>
                    <div>
                        <label for="cancel-reason" class="block text-xs font-semibold text-gray-600 mb-1">Reason <span class="font-normal text-gray-400">(optional, shown to members)</span></label>
                        <input id="cancel-reason" type="text" name="reason" maxlength="500"
                               class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-red-400 focus:ring-red-400">
                    </div>
                    <button type="submit" class="w-full bg-red-500 text-white text-sm font-semibold rounded-lg py-2 hover:opacity-90">
                        Cancel fee and notify members
                    </button>
                </form>
            </details>
        </div>
    @endunless

    {{-- Summary --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm grid grid-cols-3 divide-x divide-gray-100">
        <div class="flex flex-col items-center py-3">
            <span class="text-lg font-bold text-gray-800">₱{{ number_format($fee->amount, 2) }}</span>
            <span class="text-[10px] text-gray-400 mt-0.5">Per Member</span>
        </div>
        <div class="flex flex-col items-center py-3">
            <span class="text-lg font-bold text-[#07873e]">{{ $paidCount }}</span>
            <span class="text-[10px] text-gray-400 mt-0.5">Paid</span>
        </div>
        <div class="flex flex-col items-center py-3">
            <span class="text-lg font-bold text-red-500">{{ $pendingCount }}</span>
            <span class="text-[10px] text-gray-400 mt-0.5">Unpaid</span>
        </div>
    </div>

    {{-- Progress bar --}}
    @php $total = $paidCount + $pendingCount; $pct = $total > 0 ? ($paidCount / $total) * 100 : 0; @endphp
    <div>
        <div class="flex items-center justify-between mb-1.5">
            <span class="text-xs text-gray-500 font-medium">Collection progress</span>
            <span class="text-xs text-gray-500">{{ number_format($pct, 0) }}%</span>
        </div>
        <div class="h-2 rounded-full bg-gray-100 overflow-hidden">
            <div class="h-full bg-[#07873e] rounded-full transition-all" style="width: {{ $pct }}%"></div>
        </div>
    </div>

    {{-- Member payment list --}}
    @if($payments->isEmpty())
        <div class="text-center py-8">
            <p class="text-sm text-gray-400">No members assigned to this fee.</p>
        </div>
    @else
        {{-- Unpaid first --}}
        @php
            $unpaid = $payments->where('status', 'pending');
            $paid   = $payments->where('status', 'paid');
        @endphp

        @if($unpaid->isNotEmpty())
            <div>
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-2 px-1">Unpaid ({{ $unpaid->count() }})</p>
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden divide-y divide-gray-50">
                    @foreach($unpaid as $payment)
                        <div class="flex items-center gap-3 px-4 py-3">
                            <div class="w-9 h-9 rounded-full bg-red-50 flex items-center justify-center flex-shrink-0">
                                <span class="text-red-400 text-xs font-bold">
                                    {{ strtoupper(substr($payment->user?->first_name, 0, 1) . substr($payment->user?->last_name, 0, 1)) }}
                                </span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-gray-800 truncate">
                                    {{ $payment->user?->first_name }} {{ $payment->user?->last_name }}
                                </p>
                                <p class="text-[10px] text-red-400 font-medium mt-0.5">Unpaid</p>
                            </div>
                            @unless($fee->isCancelled())
                            <form method="POST" action="{{ route('officer.fees.paid', [$fee, $payment->user_id]) }}" class="flex-shrink-0">
                                @csrf
                                <button type="submit"
                                        class="bg-[#07873e] text-white text-[10px] font-semibold rounded-lg px-3 py-1.5 hover:opacity-90 transition-opacity">
                                    Mark Paid
                                </button>
                            </form>
                            @endunless
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if($paid->isNotEmpty())
            <div>
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-2 px-1">Paid ({{ $paid->count() }})</p>
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden divide-y divide-gray-50">
                    @foreach($paid as $payment)
                        <div class="flex items-center gap-3 px-4 py-3">
                            <div class="w-9 h-9 rounded-full bg-green-50 flex items-center justify-center flex-shrink-0">
                                <span class="text-green-600 text-xs font-bold">
                                    {{ strtoupper(substr($payment->user?->first_name, 0, 1) . substr($payment->user?->last_name, 0, 1)) }}
                                </span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-gray-800 truncate">
                                    {{ $payment->user?->first_name }} {{ $payment->user?->last_name }}
                                </p>
                                @if($payment->confirmed_at)
                                    <p class="text-[10px] text-green-500 font-medium mt-0.5">
                                        Paid {{ $payment->confirmed_at->format('M j, Y') }}
                                        @if($payment->receipt_number)
                                            · <span class="text-gray-400">{{ $payment->receipt_number }}</span>
                                        @endif
                                    </p>
                                @endif
                            </div>
                            <div class="flex items-center gap-1.5 flex-shrink-0">
                                @if($payment->hasReceipt())
                                    <a href="{{ route('officer.fees.receipt', [$fee, $payment->user_id]) }}"
                                       class="border border-[#07873e]/40 text-[#07873e] text-[10px] font-semibold rounded-lg px-3 py-1.5 hover:bg-[#07873e]/5 transition-colors">
                                        Receipt
                                    </a>
                                @endif
                                @unless($fee->isCancelled())
                                <form method="POST" action="{{ route('officer.fees.unpaid', [$fee, $payment->user_id]) }}">
                                    @csrf
                                    <button type="submit"
                                            class="border border-gray-200 text-gray-400 text-[10px] font-semibold rounded-lg px-3 py-1.5 hover:border-red-300 hover:text-red-400 transition-colors">
                                        Undo
                                    </button>
                                </form>
                                @endunless
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @endif

</div>
@endsection

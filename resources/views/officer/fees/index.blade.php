@extends('layouts.app-officer')
@section('title', 'Fees')
@section('club-name', $club?->name ?? 'ClubSync')

@section('content')

{{-- Header --}}
<div class="bg-[#07873e] px-5 pt-5 pb-6">
    <h1 class="text-white font-bold text-xl">Fee Collection</h1>
    <p class="text-white/60 text-xs mt-1">{{ $club?->name ?? 'No club assigned' }}</p>
</div>

<div class="px-4 py-5 space-y-4">

    @if(! $club)
        <p class="text-sm text-gray-400 text-center py-8">No club assigned.</p>
    @elseif($fees->isEmpty())
        <div class="text-center py-14">
            <div class="w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-3">
                <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75"/>
                </svg>
            </div>
            <p class="text-sm text-gray-400 font-medium">No fees created yet</p>
            <p class="text-xs text-gray-300 mt-1">Tap + to add a fee</p>
        </div>
    @else
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden divide-y divide-gray-50">
            @foreach($fees as $fee)
                <div class="flex items-center px-4 py-3.5 hover:bg-gray-50 transition-colors">
                    <a href="{{ route('officer.fees.show', $fee) }}" class="flex items-center justify-between flex-1 min-w-0">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold truncate {{ $fee->isCancelled() ? 'text-gray-400' : 'text-gray-800' }}">
                                {{ $fee->title }}
                                @if($fee->isCancelled())
                                    <span class="ml-1 px-1.5 py-px bg-gray-100 text-gray-500 text-[10px] font-semibold rounded-full align-middle">Cancelled</span>
                                @elseif($fee->isPostponed())
                                    <span class="ml-1 px-1.5 py-px bg-amber-50 text-amber-700 text-[10px] font-semibold rounded-full align-middle">Postponed</span>
                                @endif
                            </p>
                            <p class="text-xs text-gray-400 mt-0.5">
                                {{ $fee->academic_period }} · Due {{ $fee->due_date->format('M j, Y') }}
                            </p>
                            <div class="flex items-center gap-2 mt-1.5">
                                <div class="h-1.5 rounded-full bg-gray-100 flex-1 max-w-[100px] overflow-hidden">
                                    @php $pct = $fee->total_count > 0 ? ($fee->paid_count / $fee->total_count) * 100 : 0; @endphp
                                    <div class="h-full bg-[#07873e] rounded-full" style="width: {{ $pct }}%"></div>
                                </div>
                                <span class="text-[10px] text-gray-400">{{ $fee->paid_count }}/{{ $fee->total_count }} paid</span>
                            </div>
                        </div>
                        <p class="text-sm font-bold text-gray-800 flex-shrink-0 ml-3">₱{{ number_format($fee->amount, 2) }}</p>
                    </a>
                    @unless($fee->isCancelled())
                    <a href="{{ route('officer.fees.edit', $fee) }}"
                       class="ml-2 p-2 rounded-lg text-gray-400 hover:text-[#07873e] hover:bg-[#07873e]/5 flex-shrink-0" title="Edit fee">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"/>
                        </svg>
                    </a>
                    @endunless
                </div>
            @endforeach
        </div>
    @endif

</div>

{{-- FAB --}}
@if($club)
<a href="{{ route('officer.fees.create') }}"
   class="fixed bottom-20 right-4 w-14 h-14 bg-[#07873e] rounded-full shadow-lg flex items-center justify-center hover:opacity-90 transition-opacity z-30">
    <svg class="w-7 h-7 text-[#F9A825]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4.5v15m7.5-7.5h-15"/>
    </svg>
</a>
@endif

@endsection

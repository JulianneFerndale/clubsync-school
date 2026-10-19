@extends('layouts.app-vpa')
@section('title', 'Booking Letters — VP for Administration')
@section('page-title', 'Booking Letters')

@section('live-refresh', 'any')
@section('content')

<div class="bg-[#07873e] px-5 pt-5 pb-6">
    <h1 class="text-white font-bold text-xl">Room Booking Letters</h1>
    <p class="text-white/60 text-xs mt-1">Letters reach you once the club adviser has approved them. Approve one to sign it and confirm the activity, or return it with remarks.</p>
</div>

<div class="px-4 py-5 space-y-4 max-w-4xl">

    {{-- Filters --}}
    <div class="flex flex-wrap gap-2">
        @foreach([
            'pending'  => 'To approve' . ($counts['pending'] ? ' (' . $counts['pending'] . ')' : ''),
            'approved' => 'Approved (' . $counts['approved'] . ')',
            'returned' => 'Returned (' . $counts['returned'] . ')',
            'all'      => 'All',
        ] as $value => $label)
            <a href="{{ route('vpa.letters.index', $value === 'pending' ? [] : ['status' => $value]) }}"
               class="text-xs font-semibold rounded-full px-3 py-1.5 border transition-colors
                      {{ $filter === $value ? 'bg-[#07873e] text-white border-[#07873e]' : 'bg-white text-gray-600 border-gray-200 hover:border-[#07873e]/40' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    @if($letters->isEmpty())
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-8 text-center">
            <p class="text-sm text-gray-400">
                {{ [
                    'pending'  => 'No letters are waiting for your approval.',
                    'approved' => 'You have not approved any letters yet.',
                    'returned' => 'You have not returned any letters.',
                ][$filter] ?? 'No room booking letters yet.' }}
            </p>
        </div>
    @else
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden divide-y divide-gray-50">
            @foreach($letters as $event)
                @include('vpa.letters._row', ['event' => $event])
            @endforeach
        </div>

        {{ $letters->links() }}
    @endif
</div>
@endsection

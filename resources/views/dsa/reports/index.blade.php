@extends('layouts.app-dsa')
@section('title', 'CHED Reports — DSA')

@section('content')

<div class="bg-[#07873e] px-5 py-4">
    <h1 class="text-white font-bold text-xl">CHED Reports</h1>
    <p class="text-white/60 text-xs mt-0.5">Finalized ACLE and community service reports</p>
</div>

<div class="px-4 py-5 space-y-5">

    @if($reports->isEmpty())
        <p class="text-sm text-gray-400 text-center py-8">No finalized reports yet.</p>
    @else
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden divide-y divide-gray-50">
            @foreach($reports as $report)
                <div class="flex items-center justify-between gap-3 px-4 py-3.5">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-gray-800 truncate">{{ $report->activity?->title ?? 'Deleted activity' }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">
                            {{ $report->club->name }} · {{ ucfirst(str_replace('_', ' ', $report->report_type)) }} · {{ $report->created_at->format('M j, Y') }}
                        </p>
                    </div>
                    <div class="flex items-center gap-3 text-xs flex-shrink-0">
                        <a href="{{ route('dsa.reports.pdf', $report) }}" class="text-[#07873e] font-semibold underline">PDF</a>
                        <a href="{{ route('dsa.reports.xlsx', $report) }}" class="text-[#07873e] font-semibold underline">XLSX</a>
                    </div>
                </div>
            @endforeach
        </div>

        <div>{{ $reports->links() }}</div>
    @endif

</div>
@endsection

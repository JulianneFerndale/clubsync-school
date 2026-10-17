@extends($layout)
@section('title', 'Resources — ClubSync')
@section('page-title', 'Resources')
@section('club-name', 'ClubSync')

@php
    $fmt = fn ($bytes) => $bytes >= 1048576
        ? number_format($bytes / 1048576, 1) . ' MB'
        : number_format($bytes / 1024, 0) . ' KB';
    $tabs = [
        'handbook'  => ['Club Handbook', $handbooks->count()],
        'templates' => ['Report Templates', $templates->count()],
        'memos'     => ['CHED Memorandums', $memos->count()],
    ];
    // Full literal class strings (Tailwind's scanner can't see interpolated names).
    $statusBadge = [
        'in_effect'  => 'bg-green-50 text-green-600 border-green-200',
        'superseded' => 'bg-amber-50 text-amber-600 border-amber-200',
        'revoked'    => 'bg-red-50 text-red-600 border-red-200',
    ];
    $manage = auth_is_admin()
        ? ($tab === 'memos' ? route('admin.ched-memos.index') : route('admin.resources.index'))
        : null;
@endphp

@section('content')

<div class="bg-[#07873e] px-5 pt-5 pb-6">
    <h1 class="text-white font-bold text-xl">Resources</h1>
    <p class="text-white/60 text-xs mt-1">Saint Columban College club handbook, report templates and CHED memorandums.</p>
</div>

<div class="px-4 py-5 space-y-4 max-w-3xl">

    {{-- Plain links, not Alpine tabs: Alpine only loads in the officer layout. --}}
    <div class="flex gap-2 overflow-x-auto pb-1 -mx-1 px-1">
        @foreach($tabs as $key => [$label, $count])
            <a href="{{ route('resources.index', ['tab' => $key]) }}"
               class="flex-shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold border transition-colors
                      {{ $tab === $key ? 'bg-[#07873e] text-[#F9A825] border-[#07873e]' : 'bg-white text-gray-600 border-gray-200 hover:border-[#07873e]/40' }}">
                {{ $label }}
                <span class="{{ $tab === $key ? 'bg-[#F9A825]/20 text-[#F9A825]' : 'bg-gray-100 text-gray-500' }} rounded-full px-1.5 py-0.5 text-[10px] leading-none">{{ $count }}</span>
            </a>
        @endforeach
    </div>

    @if($manage)
        <p class="text-xs text-gray-500">
            You manage these files. <a href="{{ $manage }}" class="text-[#07873e] font-semibold underline">Open the admin page</a> to add or replace one.
        </p>
    @endif

    @if($tab === 'memos')

        <form method="GET" action="{{ route('resources.index') }}" class="bg-white rounded-xl border border-gray-100 shadow-sm p-3">
            <input type="hidden" name="tab" value="memos">
            <div class="flex items-center gap-2">
                <input type="text" name="search" value="{{ $search }}" placeholder="Search by number or title…"
                       class="flex-1 min-w-0 border border-gray-300 rounded-lg text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#07873e]/30">
                <button type="submit" class="bg-[#07873e] text-white text-xs font-semibold rounded-lg px-4 py-2 hover:opacity-90 transition-opacity">
                    Search
                </button>
                @if($search)
                    <a href="{{ route('resources.index', ['tab' => 'memos']) }}" class="text-xs text-gray-400 font-medium hover:text-gray-600">Clear</a>
                @endif
            </div>
        </form>

        <div class="space-y-3">
            @forelse($memos as $memo)
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                    <div class="flex items-start gap-3">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <p class="text-sm font-bold text-[#07873e]">{{ $memo->citation }}</p>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold border {{ $statusBadge[$memo->status] ?? 'bg-gray-50 text-gray-600 border-gray-200' }}">
                                    {{ $memo->status_label }}
                                </span>
                            </div>
                            <p class="text-sm font-semibold text-gray-800 mt-0.5">{{ $memo->title }}</p>
                            <p class="text-[11px] text-gray-400 mt-0.5">
                                {{ $memo->type_label }} · Issued {{ $memo->date_issued->format('M j, Y') }}
                                @if($memo->effective_date) · Effective {{ $memo->effective_date->format('M j, Y') }}@endif
                            </p>
                        </div>
                        <a href="{{ route('resources.memos.download', $memo) }}" data-turbo="false"
                           class="flex-shrink-0 bg-[#07873e] text-white text-xs font-semibold rounded-lg px-3 py-1.5 hover:opacity-90 transition-opacity">
                            Download
                        </a>
                    </div>
                    @if($memo->summary)
                        <p class="text-xs text-gray-600 leading-relaxed mt-2 whitespace-pre-line">{{ $memo->summary }}</p>
                    @endif
                    @if($memo->source_url)
                        <a href="{{ $memo->source_url }}" target="_blank" rel="noopener noreferrer"
                           class="inline-block mt-2 text-[11px] text-[#07873e] font-semibold underline">View on the CHED website</a>
                    @endif
                </div>
            @empty
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm text-center py-10 px-4">
                    <p class="text-gray-400 text-sm">{{ $search ? 'No memorandums match your search.' : 'No CHED memorandums have been posted yet.' }}</p>
                </div>
            @endforelse
        </div>

    @else

        @php($files = $tab === 'handbook' ? $handbooks : $templates)
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm divide-y divide-gray-50">
            @forelse($files as $document)
                <div class="flex items-start gap-3 px-4 py-3">
                    <div class="w-10 h-10 rounded-lg bg-[#07873e]/10 text-[#07873e] flex items-center justify-center flex-shrink-0 text-[10px] font-bold">
                        {{ $document->extension }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-gray-800">{{ $document->title }}</p>
                        @if($document->description)
                            <p class="text-xs text-gray-500 mt-0.5">{{ $document->description }}</p>
                        @endif
                        <p class="text-[11px] text-gray-400 mt-0.5">{{ $fmt($document->file_size) }} · Updated {{ $document->updated_at->format('M j, Y') }}</p>
                    </div>
                    <a href="{{ route('resources.documents.download', $document) }}" data-turbo="false"
                       class="flex-shrink-0 bg-[#07873e] text-white text-xs font-semibold rounded-lg px-3 py-1.5 hover:opacity-90 transition-opacity">
                        Download
                    </a>
                </div>
            @empty
                <p class="text-gray-400 text-sm text-center py-10 px-4">
                    {{ $tab === 'handbook' ? 'The club handbook has not been uploaded yet.' : 'No report templates have been uploaded yet.' }}
                </p>
            @endforelse
        </div>

    @endif

</div>
@endsection

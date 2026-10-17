@extends('layouts.app-admin')
@section('title', 'CHED Memorandums — Admin')
@section('page-title', 'CHED Memorandums')

@php
    // Full literal class strings (Tailwind's scanner can't see interpolated names).
    $statusBadge = [
        'in_effect'  => 'bg-green-50 text-green-600 border-green-200',
        'superseded' => 'bg-amber-50 text-amber-600 border-amber-200',
        'revoked'    => 'bg-red-50 text-red-600 border-red-200',
    ];
@endphp

@section('content')

<div class="bg-[#07873e] px-5 pt-5 pb-6">
    <h1 class="text-white font-bold text-xl">CHED Memorandums</h1>
    <p class="text-white/60 text-xs mt-1">The CHED issuances student affairs follows. Every user can read and download them from Resources; every change here is recorded in the audit log.</p>
</div>

<div class="px-4 py-5 space-y-4 max-w-4xl">


    <div class="flex flex-col sm:flex-row gap-3">
        <form method="GET" action="{{ route('admin.ched-memos.index') }}" class="flex-1 bg-white rounded-xl border border-gray-100 shadow-sm p-3">
            <div class="flex items-center gap-2">
                <input type="text" name="search" value="{{ $search }}" placeholder="Search by number or title…"
                       class="flex-1 min-w-0 border border-gray-300 rounded-lg text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#07873e]/30">
                <select name="status" class="border border-gray-300 rounded-lg text-xs px-2.5 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-[#07873e]/30">
                    <option value="">All statuses</option>
                    @foreach($statuses as $key => $label)
                        <option value="{{ $key }}" {{ $status === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <button type="submit" class="bg-[#07873e] text-white text-xs font-semibold rounded-lg px-4 py-2 hover:opacity-90 transition-opacity">
                    Search
                </button>
                @if($search || $status)
                    <a href="{{ route('admin.ched-memos.index') }}" class="text-xs text-gray-400 font-medium hover:text-gray-600">Clear</a>
                @endif
            </div>
        </form>
        <a href="{{ route('admin.ched-memos.create') }}"
           class="inline-flex items-center justify-center gap-1.5 bg-[#F9A825] text-white text-sm font-semibold rounded-xl px-4 py-3 hover:opacity-90 transition-opacity flex-shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            Add Memorandum
        </a>
    </div>

    <div class="bg-white rounded-xl border border-gray-100 shadow-sm divide-y divide-gray-50">
        @forelse($memos as $memo)
            <div class="flex flex-col sm:flex-row sm:items-start gap-3 px-4 py-3">
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
                <div class="flex items-center gap-2 flex-shrink-0">
                    <a href="{{ route('resources.memos.download', $memo) }}" data-turbo="false"
                       class="border border-[#07873e]/40 text-[#07873e] text-xs font-semibold rounded-lg px-3 py-1.5 hover:bg-[#07873e]/5 transition-colors">
                        PDF
                    </a>
                    <a href="{{ route('admin.ched-memos.edit', $memo) }}"
                       class="border border-gray-300 text-gray-600 text-xs font-semibold rounded-lg px-3 py-1.5 hover:bg-gray-50 transition-colors">
                        Edit
                    </a>
                    <form method="POST" action="{{ route('admin.ched-memos.destroy', $memo) }}"
                          onsubmit="return confirm('Remove {{ $memo->citation }} from the library? Its PDF is deleted too.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="border border-red-200 text-red-500 text-xs font-semibold rounded-lg px-3 py-1.5 hover:bg-red-50 transition-colors">
                            Remove
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <div class="text-center py-10 px-4">
                <p class="text-gray-400 text-sm">{{ $search || $status ? 'No memorandums match your search.' : 'No CHED memorandums have been added yet.' }}</p>
                @unless($search || $status)
                    <a href="{{ route('admin.ched-memos.create') }}" class="inline-block mt-2 text-sm text-[#07873e] font-semibold underline">Add the first one</a>
                @endunless
            </div>
        @endforelse
    </div>

    @if($memos->hasPages())
        <div class="pt-2">{{ $memos->links() }}</div>
    @endif

</div>
@endsection

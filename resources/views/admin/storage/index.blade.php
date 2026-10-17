@extends('layouts.app-admin')
@section('title', 'Storage & Retention')
@section('page-title', 'Storage & Retention')

@php
    $fmt = fn ($bytes) => $bytes >= 1048576
        ? number_format($bytes / 1048576, 1) . ' MB'
        : number_format($bytes / 1024, 0) . ' KB';
    // Full literal class strings (Tailwind's scanner can't see interpolated names).
    $levelBadge = [
        'ok'       => 'bg-green-50 text-green-600 border-green-200',
        'warn'     => 'bg-amber-50 text-amber-600 border-amber-200',
        'critical' => 'bg-red-50 text-red-600 border-red-200',
    ][$status['level']] ?? 'bg-gray-50 text-gray-600 border-gray-200';
    $barColor = ['ok' => 'bg-green-500', 'warn' => 'bg-[#F9A825]', 'critical' => 'bg-red-500'][$status['level']] ?? 'bg-gray-400';
@endphp

@section('content')

<div class="bg-[#07873e] px-5 pt-5 pb-6">
    <h1 class="text-white font-bold text-xl">Storage & Retention</h1>
    <p class="text-white/60 text-xs mt-1">Database usage and data archival</p>
</div>

<div class="px-4 py-5 space-y-5 max-w-3xl">

    @error('semester')
        <div class="bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-red-600 text-sm">{{ $message }}</div>
    @enderror

    {{-- Usage gauge --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
        <div class="flex items-center justify-between mb-2">
            <p class="text-sm font-semibold text-gray-700">Database usage</p>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ $levelBadge }}">
                {{ strtoupper($status['level']) }}
            </span>
        </div>
        <div class="h-3 bg-gray-100 rounded-full overflow-hidden">
            <div class="h-full {{ $barColor }} rounded-full" style="width: {{ min(100, $status['percent']) }}%"></div>
        </div>
        <p class="text-xs text-gray-500 mt-2">
            {{ $fmt($status['bytes']) }} of {{ $fmt($status['cap']) }} used ({{ $status['percent'] }}%)
        </p>
        <p class="text-[11px] text-gray-400 mt-2">
            Expired sessions and read notifications older than {{ config('retention.read_notification_days') }} days are pruned automatically each day. Student, financial, activity and audit data are never deleted automatically.
        </p>
    </div>

    {{-- Largest tables --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest px-4 pt-4 pb-1">Largest tables</p>
        <div class="divide-y divide-gray-50">
            @foreach($status['tables'] as $t)
                <div class="flex items-center justify-between px-4 py-2.5">
                    <span class="text-sm text-gray-700 font-mono">{{ $t['name'] }}</span>
                    <span class="text-xs text-gray-400">{{ number_format($t['rows']) }} rows · {{ $fmt($t['bytes']) }}</span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Archive old semesters --}}
    <div>
        <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-2 px-1">Archive old semesters</p>
        @if($archivable->isEmpty())
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-5 text-center text-sm text-gray-400">
                No semesters are old enough to archive yet (the current and previous semester are always kept).
            </div>
        @else
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm divide-y divide-gray-50">
                @foreach($archivable as $sem)
                    <form method="POST" action="{{ route('admin.storage.archive', $sem) }}"
                          class="flex items-center justify-between px-4 py-3"
                          onsubmit="return confirm('Export {{ $sem->label }} attendance & fee records to Excel and remove them from the database? This cannot be undone (a downloadable archive is kept).')">
                        @csrf
                        <div>
                            <p class="text-sm font-semibold text-gray-800">{{ $sem->label }}</p>
                            <p class="text-[11px] text-gray-400">{{ optional($sem->start_date)->format('M Y') }} – {{ optional($sem->end_date)->format('M Y') }}</p>
                        </div>
                        <button type="submit" class="bg-[#07873e] text-[#F9A825] font-semibold text-xs rounded-lg px-4 py-2 hover:opacity-90 transition-opacity">
                            Archive &amp; purge
                        </button>
                    </form>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Existing archives --}}
    @if($archives->isNotEmpty())
        <div>
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-2 px-1">Archived files</p>
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm divide-y divide-gray-50">
                @foreach($archives as $a)
                    <a href="{{ route('admin.storage.download', ['file' => $a['name']]) }}"
                       class="flex items-center justify-between px-4 py-3 hover:bg-gray-50 transition-colors">
                        <span class="text-sm text-gray-700 truncate">{{ $a['name'] }}</span>
                        <span class="text-xs text-[#07873e] font-semibold flex-shrink-0 ml-3">{{ $fmt($a['size']) }} · Download</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Audit log pruning --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
        <p class="text-sm font-semibold text-gray-700">Audit log</p>
        <p class="text-[11px] text-gray-400 mt-1 mb-3">
            Audit entries are retained for at least one academic year (policy). Older entries may be pruned.
        </p>
        <form method="POST" action="{{ route('admin.storage.prune-audit') }}"
              onsubmit="return confirm('Permanently delete audit-log entries older than {{ config('retention.audit_retention_days') }} days?')">
            @csrf
            <button type="submit" class="border-2 border-red-300 text-red-500 font-semibold text-xs rounded-lg px-4 py-2 hover:bg-red-50 transition-colors">
                Prune audit entries &gt; 1 year
            </button>
        </form>
    </div>

</div>
@endsection

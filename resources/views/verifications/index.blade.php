@extends($layout)
@section('title', 'Enrollment Verification — ClubSync')
@section('page-title', 'Enrollment Verification')

@section('content')

<div class="bg-[#07873e] px-5 py-4">
    <h1 class="text-white font-bold text-xl">Enrollment Verification</h1>
    <p class="text-white/60 text-xs mt-0.5">Proof of enrollment the automatic check could not decide. Students wait until one is approved.</p>
</div>

<div class="px-4 py-5 space-y-6 max-w-4xl">

    <section class="space-y-2">
        <h2 class="text-sm font-semibold text-gray-700">Waiting for a decision ({{ $pending->count() }})</h2>

        @if($awaitingRecheck > 0)
            <div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 flex flex-wrap items-center justify-between gap-3">
                <p class="text-xs text-amber-800 leading-relaxed flex-1 min-w-[14rem]">
                    <span class="font-semibold">{{ $awaitingRecheck }} {{ $awaitingRecheck === 1 ? 'is' : 'are' }} Pending Verification only because the automatic check was unavailable when uploaded.</span>
                    ClubSync checks {{ $awaitingRecheck === 1 ? 'it' : 'them' }} again by itself every 15 minutes once the AI is back, so
                    you can leave {{ $awaitingRecheck === 1 ? 'it' : 'them' }} or decide now.
                    @unless($aiReady) The automatic check is still unavailable. @endunless
                </p>
                @if($aiReady)
                    <form method="POST" action="{{ route($prefix . 'recheck') }}">
                        @csrf
                        <button type="submit"
                                class="text-xs font-semibold bg-[#07873e] text-[#F9A825] rounded-lg px-3 py-2 hover:opacity-90 transition-opacity">
                            Check them now
                        </button>
                    </form>
                @endif
            </div>
        @endif

        @if($pending->isEmpty())
            <p class="text-sm text-gray-400 text-center py-8 bg-white rounded-xl border border-gray-100">Nothing waiting. Every new student so far was verified automatically or has been decided.</p>
        @else
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden divide-y divide-gray-50">
                @foreach($pending as $case)
                    <a href="{{ route($prefix . 'show', $case) }}"
                       class="flex items-center justify-between gap-3 px-4 py-3.5 hover:bg-gray-50 transition-colors">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-800 truncate">{{ $case->user->name }}</p>
                            <p class="text-xs text-gray-500 truncate">EDP {{ $case->user->edp_number }} · {{ $case->user->course?->name ?? 'No course' }}</p>
                            <p class="text-xs text-[#b7791f] mt-1">{{ $case->review_reason_label }}</p>
                        </div>
                        <span class="text-xs text-gray-400 flex-shrink-0">{{ $case->created_at->diffForHumans() }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </section>

    @if($decided->isNotEmpty())
        <section class="space-y-2">
            <h2 class="text-sm font-semibold text-gray-700">Recently decided</h2>
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden divide-y divide-gray-50">
                @foreach($decided as $case)
                    <div class="flex items-center justify-between gap-3 px-4 py-3">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-800 truncate">{{ $case->user?->name ?? 'Deleted account' }}</p>
                            <p class="text-xs text-gray-400 truncate">
                                @if($case->method === 'automatic')
                                    Automatic re-check, once the AI was back
                                @else
                                    {{ $case->reviewer?->name ?? 'Unknown' }} ({{ $case->method === 'admin' ? 'Admin' : 'DSA' }})
                                @endif
                                · {{ $case->reviewed_at?->format('M j, Y g:i A') }}
                            </p>
                        </div>
                        @if($case->status === \App\Models\EnrollmentVerification::STATUS_VERIFIED)
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold border bg-green-50 text-green-700 border-green-200">{{ $case->method === 'automatic' ? 'Verified' : 'Approved' }}</span>
                        @else
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold border bg-red-50 text-red-600 border-red-200">{{ $case->method === 'automatic' ? 'Sent back' : 'Rejected' }}</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @endif

</div>
@endsection

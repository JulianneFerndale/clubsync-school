@extends($layout)
@section('title', 'Check Proof of Enrollment — ClubSync')
@section('page-title', 'Enrollment Verification')

@php
    $student  = $verification->user;
    $findings = $verification->findings;
@endphp

@section('content')

<div class="bg-[#07873e] px-5 py-4">
    <a href="{{ route($prefix . 'index') }}" class="text-white/70 text-xs hover:text-white">&larr; Enrollment Verification</a>
    <h1 class="text-white font-bold text-xl mt-1">{{ $student->name }}</h1>
    <p class="text-white/60 text-xs mt-0.5">{{ $student->email }}</p>
</div>

<div class="px-4 py-5 space-y-5 max-w-5xl">

    @unless($verification->isPending())
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-3.5 text-sm text-gray-700">
            This case was {{ $verification->status === \App\Models\EnrollmentVerification::STATUS_VERIFIED ? 'approved' : 'rejected' }}
            by {{ $verification->reviewer?->name ?? 'someone' }} on {{ $verification->reviewed_at?->format('F j, Y g:i A') }}.
            The document has been deleted.
            @if($verification->remarks)
                <p class="text-xs text-gray-500 mt-1">Reason given: {{ $verification->remarks }}</p>
            @endif
        </div>
    @endunless

    <div class="grid md:grid-cols-2 gap-5 items-start">

        {{-- Registered vs. read --}}
        <div class="space-y-5">
            <section class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 space-y-3">
                <h2 class="text-sm font-semibold text-gray-800">What the student registered with</h2>
                <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 text-sm">
                    <dt class="text-gray-500">Full name</dt>    <dd class="font-semibold text-gray-900">{{ $student->name }}</dd>
                    <dt class="text-gray-500">EDP number</dt>   <dd class="font-semibold text-gray-900">{{ $student->edp_number }}</dd>
                    <dt class="text-gray-500">Course</dt>       <dd class="font-semibold text-gray-900">{{ $student->course?->name ?? '—' }}</dd>
                    <dt class="text-gray-500">College</dt>      <dd class="text-gray-800">{{ $student->department?->short_name ?? '—' }}</dd>
                    <dt class="text-gray-500">Year level</dt>   <dd class="text-gray-800">{{ $student->year_level ? year_level_label($student->year_level) : '—' }}</dd>
                    <dt class="text-gray-500">Submitted</dt>    <dd class="text-gray-800">{{ $verification->created_at->format('F j, Y g:i A') }}</dd>
                </dl>
                <p class="text-xs text-[#b7791f]">{{ $verification->review_reason_label }}.</p>
            </section>

            @if($verification->isPending())
                <section class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 space-y-3">
                    <h2 class="text-sm font-semibold text-gray-800">What the automatic check read</h2>

                    @if($findings)
                        <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 text-sm">
                            <dt class="text-gray-500">Document</dt>    <dd class="text-gray-900">{{ \App\Models\EnrollmentVerification::documentLabel($findings['document_type'] ?? null) }}</dd>
                            <dt class="text-gray-500">School</dt>      <dd class="text-gray-900">{{ ($findings['school_name'] ?? '') ?: '—' }}</dd>
                            <dt class="text-gray-500">Full name</dt>   <dd class="text-gray-900">{{ ($findings['full_name'] ?? '') ?: '—' }}</dd>
                            <dt class="text-gray-500">EDP number</dt>  <dd class="text-gray-900">{{ ($findings['edp_number'] ?? '') ?: '—' }}</dd>
                            <dt class="text-gray-500">Course</dt>      <dd class="text-gray-900">{{ ($findings['course'] ?? '') ?: '—' }}</dd>
                        </dl>

                        @if(! empty($findings['looks_altered']))
                            <p class="text-xs font-semibold text-red-600">Flagged: the document may have been edited. Look closely at the name and number fields.</p>
                        @endif

                        @if(! empty($findings['problems']))
                            <div class="text-xs text-red-700 bg-red-50 border border-red-100 rounded-lg px-3 py-2">
                                <p class="font-semibold mb-0.5">Did not match</p>
                                <ul class="list-disc pl-4 space-y-0.5">
                                    @foreach($findings['problems'] as $problem)
                                        <li>{{ $problem }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if(! empty($findings['notes']))
                            <p class="text-xs text-gray-500">Note: {{ $findings['notes'] }}</p>
                        @endif
                    @else
                        <p class="text-sm text-gray-500">The automatic check could not run, so nothing was read. Compare the document with the details above yourself.</p>
                    @endif
                </section>

                {{-- Decision --}}
                <section class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 space-y-4">
                    <h2 class="text-sm font-semibold text-gray-800">Your decision</h2>
                    <p class="text-xs text-gray-500 leading-relaxed">
                        Approve only if this is a Saint Columban College Certificate of Enrollment or the front of an SCC student ID,
                        and it shows this student's full name, EDP number and course. Either way the document is deleted once you decide.
                    </p>

                    <form method="POST" action="{{ route($prefix . 'approve', $verification) }}" data-turbo="false"
                          onsubmit="return confirm('Approve {{ addslashes($student->name) }} as an enrolled SCC student? Their account becomes active.')">
                        @csrf
                        <button type="submit" class="w-full bg-[#07873e] text-white text-sm font-semibold rounded-lg px-4 py-2.5 hover:opacity-90 transition-opacity">
                            Approve — the document matches
                        </button>
                    </form>

                    <form method="POST" action="{{ route($prefix . 'reject', $verification) }}" data-turbo="false" class="space-y-2 border-t border-gray-100 pt-4">
                        @csrf
                        <label for="remarks" class="block text-xs font-medium text-gray-600">Reason for rejecting (the student sees this)</label>
                        <textarea id="remarks" name="remarks" rows="3" required minlength="5" maxlength="500"
                                  placeholder="e.g. The EDP number on the COE does not match the one you registered with."
                                  class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-300">{{ old('remarks') }}</textarea>
                        @error('remarks')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                        <button type="submit" class="w-full border border-red-300 text-red-600 text-sm font-semibold rounded-lg px-4 py-2.5 hover:bg-red-50 transition-colors">
                            Reject — ask for another document
                        </button>
                    </form>
                </section>
            @endif
        </div>

        {{-- The document --}}
        @if($verification->isPending())
            <section class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 space-y-3">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-sm font-semibold text-gray-800">Uploaded document</h2>
                    <a href="{{ route($prefix . 'document', $verification) }}" target="_blank" rel="noopener" data-no-loading
                       class="text-xs text-[#07873e] font-semibold hover:underline">Open in a new tab</a>
                </div>

                @if($verification->document_mime === 'application/pdf')
                    <iframe src="{{ route($prefix . 'document', $verification) }}" title="Uploaded proof of enrollment"
                            class="w-full h-[70vh] rounded-lg border border-gray-200"></iframe>
                @else
                    <img src="{{ route($prefix . 'document', $verification) }}" alt="Uploaded proof of enrollment"
                         class="w-full rounded-lg border border-gray-200">
                @endif
            </section>
        @endif
    </div>
</div>
@endsection

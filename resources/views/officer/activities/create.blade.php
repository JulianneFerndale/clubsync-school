@extends('layouts.app-officer')
@section('title', 'Add Activity')
@section('club-name', $club?->name ?? 'ClubSync')

@section('content')

{{-- Header --}}
<div class="flex items-center gap-3 bg-[#07873e] px-5 py-4">
    <a href="{{ route('officer.activities.index') }}" class="text-white/70 hover:text-white transition-colors">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
        </svg>
    </a>
    <h1 class="text-white font-bold text-xl">Add Activity</h1>
</div>

<div class="px-4 py-5">

    {{-- Validation errors --}}

    {{-- Duplicate warning (non-blocking) --}}
    @if(session('duplicate_warning'))
        <div class="mb-4 bg-[#F9A825]/10 border border-[#F9A825]/40 rounded-xl px-4 py-3">
            <p class="text-sm text-gray-700 font-medium">
                A club activity with this title already exists on the selected date. You may still proceed.
            </p>
        </div>
    @endif

    <form method="POST" action="{{ route('officer.activities.store') }}" enctype="multipart/form-data" class="space-y-4" id="event-form"
          data-loading="dialog" data-loading-message="Sending to your adviser"
          x-data="{ activityType: '{{ old('activity_type', 'internal_meeting') }}' }">
        @csrf

        {{-- Pass confirmed = true if the user is re-submitting after duplicate warning --}}
        @if(session('duplicate_warning'))
            <input type="hidden" name="confirmed" value="1">
        @endif

        {{-- Activity title --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                Activity Title <span class="text-red-500">*</span>
            </label>
            <input type="text" name="title" value="{{ old('title') }}" required
                   placeholder="Upcoming Activity..."
                   class="w-full border rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#07873e]/30
                          {{ $errors->has('title') ? 'border-red-400' : 'border-gray-300' }}">
            @error('title')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        {{-- Description --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                Description <span class="text-red-500">*</span>
            </label>
            <textarea name="description" rows="3" required
                      placeholder="Activity description..."
                      class="w-full border rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#07873e]/30 resize-none
                             {{ $errors->has('description') ? 'border-red-400' : 'border-gray-300' }}">{{ old('description') }}</textarea>
            @error('description')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        {{-- Date --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                Date <span class="text-red-500">*</span>
            </label>
            <input type="date" name="date" value="{{ old('date') }}" required
                   min="{{ today()->toDateString() }}"
                   class="w-full border rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#07873e]/30
                          {{ $errors->has('date') ? 'border-red-400' : 'border-gray-300' }}">
            @error('date')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        {{-- Time (start + end) --}}
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                    Start Time <span class="text-red-500">*</span>
                </label>
                <input type="time" name="time_start" value="{{ old('time_start') }}" required
                       class="w-full border rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#07873e]/30
                              {{ $errors->has('time_start') ? 'border-red-400' : 'border-gray-300' }}">
                @error('time_start')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                    End Time <span class="text-red-500">*</span>
                </label>
                <input type="time" name="time_end" value="{{ old('time_end') }}" required
                       class="w-full border rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#07873e]/30
                              {{ $errors->has('time_end') ? 'border-red-400' : 'border-gray-300' }}">
                @error('time_end')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>
        </div>

        {{-- Location --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                Location <span class="text-red-500">*</span>
            </label>
            <select name="venue" required
                    class="w-full border rounded-xl px-4 py-3 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#07873e]/30
                           {{ $errors->has('venue') ? 'border-red-400' : 'border-gray-300' }}">
                <option value="">Select a room...</option>
                @foreach($venues as $venue)
                    <option value="{{ $venue }}" {{ old('venue') === $venue ? 'selected' : '' }}>
                        {{ $venue }}
                    </option>
                @endforeach
            </select>
            <p class="text-xs text-gray-400 mt-1">Main campus rooms only.</p>
            @error('venue')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        {{-- AI-assisted proposal: checks the venue is free, then drafts the prose.
             The availability check runs even when AI is switched off. --}}
        <div class="border border-[#07873e]/25 bg-[#07873e]/5 rounded-xl p-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold text-[#07873e]">
                        {{ $aiAvailable ? 'AI-Assisted Proposal' : 'Venue Availability Check' }}
                    </p>
                    <p class="text-xs text-gray-600 mt-0.5">
                        @if($aiAvailable)
                            Confirms the venue is free for your slot, then drafts the description, purpose, expected participants and target year levels for you to review.
                        @else
                            AI Unavailable — Manual Draft Required. The venue availability check still works.
                        @endif
                    </p>
                </div>
            </div>

            @if($aiAvailable)
                <textarea id="proposal-context" rows="2" maxlength="500"
                          placeholder="Optional: anything else the draft should mention..."
                          class="mt-3 w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm resize-none
                                 focus:outline-none focus:ring-2 focus:ring-[#07873e]/30"></textarea>
            @endif

            <button type="button" id="proposal-btn"
                    class="mt-3 w-full flex items-center justify-center gap-2 border-2 border-[#07873e] text-[#07873e]
                           font-semibold text-sm rounded-xl py-2.5 hover:bg-[#07873e]/10 transition-colors
                           disabled:opacity-50 disabled:cursor-not-allowed">
                <svg id="proposal-spinner" class="hidden animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z"></path>
                </svg>
                <span id="proposal-btn-text">{{ $aiAvailable ? 'Check venue & draft proposal' : 'Check venue availability' }}</span>
            </button>

            <div id="proposal-result" class="hidden mt-3 text-sm rounded-xl px-3 py-2.5"></div>
        </div>

        {{-- Purpose / Objectives --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                Purpose / Objectives <span class="text-red-500">*</span>
            </label>
            <textarea name="purpose" rows="3" required
                      placeholder="State the purpose and objectives of this activity..."
                      class="w-full border rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#07873e]/30 resize-none
                             {{ $errors->has('purpose') ? 'border-red-400' : 'border-gray-300' }}">{{ old('purpose') }}</textarea>
            @error('purpose')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        {{-- Expected Participants --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                Expected Participants <span class="text-red-500">*</span>
            </label>
            <input type="number" name="expected_participants" value="{{ old('expected_participants') }}"
                   min="1" required placeholder="0"
                   class="w-full border rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#07873e]/30
                          {{ $errors->has('expected_participants') ? 'border-red-400' : 'border-gray-300' }}">
            @error('expected_participants')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        {{-- Target Year Level(s) --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                Target Year Level(s)
                <span class="text-xs text-gray-400 font-normal ml-1">(Optional)</span>
            </label>
            <div class="grid grid-cols-2 gap-2">
                @foreach($yearLevels as $value => $label)
                    <label class="flex items-center gap-2.5 border rounded-xl px-3 py-2.5 cursor-pointer transition-colors
                                  {{ in_array($value, old('target_year_levels', [])) ? 'border-[#07873e] bg-[#07873e]/5' : 'border-gray-300' }}">
                        <input type="checkbox" name="target_year_levels[]" value="{{ $value }}"
                               class="w-4 h-4 rounded border-gray-300 text-[#07873e] focus:ring-[#07873e]"
                               {{ in_array($value, old('target_year_levels', [])) ? 'checked' : '' }}>
                        <span class="text-sm text-gray-700">{{ $label }}</span>
                    </label>
                @endforeach
            </div>
            <p class="text-xs text-gray-400 mt-1.5">
                For year-level-specific workshops (e.g. a session for 1st years only). Leave all unchecked to open the activity to every year level.
            </p>
            @error('target_year_levels')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            @error('target_year_levels.*')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
        </div>

        {{-- Activity Type --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                Activity Type <span class="text-red-500">*</span>
            </label>
            <select name="activity_type" required x-model="activityType"
                    class="w-full border rounded-xl px-4 py-3 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#07873e]/30
                           {{ $errors->has('activity_type') ? 'border-red-400' : 'border-gray-300' }}">
                <option value="internal_meeting">Internal Meeting</option>
                <option value="acle">ACLE</option>
                <option value="community_involvement">Community Involvement</option>
                <option value="campus_resource_use">Campus Resource Use</option>
                <option value="other_external">Other External Activity</option>
            </select>
            @error('activity_type')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
            <p class="text-xs text-[#F9A825] mt-1.5 font-medium">
                Every activity, internal meetings included, needs a room booking letter. It goes to your club adviser first, then to the Vice President for Administration, and is confirmed once both approve.
            </p>
        </div>

        {{-- Room booking letter to the VPA, through the adviser (every activity) --}}
        @include('officer.activities._booking-letter-field', ['letterBody' => old('letter_body', ''), 'aiDrafted' => old('letter_ai_drafted', '0')])

        {{-- Supporting document --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                Other Supporting Document
                <span class="text-xs text-gray-400 font-normal ml-1">(Optional, PDF/JPG/PNG, max 5MB)</span>
            </label>
            <input type="file" name="approval_letter" accept=".pdf,.jpg,.jpeg,.png"
                   class="w-full border rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#07873e]/30
                          {{ $errors->has('approval_letter') ? 'border-red-400' : 'border-gray-300' }}">
            @error('approval_letter')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        {{-- Reminder (optional, UI only — dispatch implemented in notification step) --}}
        <div class="bg-gray-50 rounded-xl p-4">
            <p class="text-sm font-medium text-gray-700 mb-3">
                Set Reminder Notification
                <span class="text-xs text-gray-400 font-normal ml-1">(Optional)</span>
            </p>
            <div class="space-y-2">
                @foreach([
                    'none'    => 'None',
                    '1440'    => '1 day before',
                    '60'      => '1 hour before',
                    '30'      => '30 minutes before',
                    'custom'  => 'Custom',
                ] as $value => $label)
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" name="reminders[]" value="{{ $value }}"
                               class="w-4 h-4 rounded border-gray-300 text-[#07873e] focus:ring-[#07873e]"
                               {{ in_array($value, old('reminders', ['none'])) ? 'checked' : '' }}>
                        <span class="text-sm text-gray-600">{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        {{-- Buttons --}}
        <div class="grid grid-cols-2 gap-3 pt-2">
            <a href="{{ route('officer.activities.index') }}"
               class="flex items-center justify-center border-2 border-[#07873e] text-[#07873e] font-semibold text-sm rounded-xl py-3.5 hover:bg-[#07873e]/5 transition-colors">
                Cancel
            </a>
            <button type="submit"
                    class="bg-[#07873e] text-[#F9A825] font-semibold text-sm rounded-xl py-3.5 hover:opacity-90 transition-opacity">
                Sign &amp; Send to Adviser
            </button>
        </div>

    </form>
</div>

<script>
(() => {
    const form   = document.getElementById('event-form');
    const btn    = document.getElementById('proposal-btn');
    const result = document.getElementById('proposal-result');
    if (! form || ! btn) return;

    const field = (name) => form.querySelector(`[name="${name}"]`);
    const spin  = document.getElementById('proposal-spinner');
    const label = document.getElementById('proposal-btn-text');
    const idle  = label.textContent;

    const show = (html, tone) => {
        const tones = {
            ok:   'bg-green-50 text-green-800 border border-green-200',
            bad:  'bg-red-50 text-red-800 border border-red-200',
            warn: 'bg-amber-50 text-amber-900 border border-amber-200',
        };
        result.className = 'mt-3 text-sm rounded-xl px-3 py-2.5 ' + tones[tone];
        result.innerHTML = html;
        result.classList.remove('hidden');
    };

    const esc = (s) => { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; };

    // Tick the drafted year levels — but only while the officer has ticked none
    // themselves, so their own targeting is never overwritten. The label carries the
    // selected styling, so it is kept in step with its checkbox by hand.
    const applyYearLevels = (levels) => {
        const boxes = [...form.querySelectorAll('[name="target_year_levels[]"]')];

        if (! Array.isArray(levels) || ! levels.length || ! boxes.length) return false;
        if (boxes.some((box) => box.checked)) return false;

        const wanted = levels.map(String);
        let applied = false;

        for (const box of boxes) {
            if (! wanted.includes(box.value)) continue;

            box.checked = true;
            applied = true;

            const label = box.closest('label');
            label?.classList.add('border-[#07873e]', 'bg-[#07873e]/5');
            label?.classList.remove('border-gray-300');
        }

        return applied;
    };

    // Fill the still-empty fields from the draft and report back what was filled, so
    // the officer knows which values to read before submitting. The participant count
    // is an estimate off the club roster and is labelled as one — it is not a figure
    // the system knows.
    const applyDraft = (draft, rosterTotal) => {
        const filled = [];

        const prose = (name, value, label) => {
            const el = field(name);
            if (! el || ! value || el.value.trim()) return;

            el.value = value;
            filled.push(label);
        };

        prose('description', draft.description, 'description');
        prose('purpose', draft.purpose, 'purpose');

        const participants = field('expected_participants');

        if (participants && draft.expected_participants && ! participants.value.trim()) {
            participants.value = draft.expected_participants;
            filled.push(rosterTotal
                ? `expected participants (estimated against your ${rosterTotal} active member${rosterTotal === 1 ? '' : 's'})`
                : 'expected participants (an estimate)');
        }

        if (applyYearLevels(draft.target_year_levels)) filled.push('target year levels');

        return filled;
    };

    btn.addEventListener('click', async () => {
        const payload = {
            title:      field('title')?.value.trim(),
            date:       field('date')?.value,
            time_start: field('time_start')?.value,
            time_end:   field('time_end')?.value,
            venue:      field('venue')?.value,
            context:    document.getElementById('proposal-context')?.value.trim() ?? '',
        };

        if (! payload.title || ! payload.date || ! payload.time_start || ! payload.time_end || ! payload.venue) {
            show('Fill in the title, date, start and end time, and venue first.', 'warn');
            return;
        }

        btn.disabled = true;
        spin.classList.remove('hidden');
        label.textContent = 'Checking…';

        try {
            const res = await fetch('{{ route('officer.activities.ai-proposal') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify(payload),
            });

            const data = await res.json();

            if (! res.ok) {
                const first = data.errors ? Object.values(data.errors)[0][0] : (data.error ?? 'Could not check the venue.');
                show(esc(first), 'bad');
                return;
            }

            // The draft is only ever placed in the form for the officer to read and
            // edit — it is never submitted on their behalf, and a field they have
            // already filled in is left exactly as they wrote it.
            const drafted = data.draft ? applyDraft(data.draft, data.roster_total) : [];

            const draftNote = drafted.length
                ? `<p class="text-xs mt-1">Filled in for you to check: ${esc(drafted.join(', '))}.</p>`
                : '';

            if (data.venue_available) {
                let html = `<p class="font-semibold">✓ ${esc(payload.venue)} is free for that slot.</p>`;
                html += `<p class="text-xs mt-1">${esc(data.notice)}</p>`;
                html += draftNote;
                show(html, 'ok');
            } else {
                let html = `<p class="font-semibold">✗ ${esc(data.conflict?.message ?? 'That venue is already booked.')}</p>`;
                if (data.alternative_venues?.length) {
                    html += '<p class="text-xs mt-2 mb-1">Free at that time:</p><div class="flex flex-wrap gap-1.5">';
                    for (const v of data.alternative_venues) {
                        html += `<button type="button" data-venue="${esc(v)}"
                                    class="js-pick-venue text-xs bg-white border border-green-300 text-green-800
                                           rounded-lg px-2 py-1 hover:bg-green-50">${esc(v)}</button>`;
                    }
                    html += '</div>';
                }
                html += draftNote;
                show(html, 'bad');

                result.querySelectorAll('.js-pick-venue').forEach((el) => {
                    el.addEventListener('click', () => {
                        field('venue').value = el.dataset.venue;
                        btn.click(); // re-check with the newly chosen venue
                    });
                });
            }
        } catch (e) {
            show('Network error. Please try again.', 'bad');
        } finally {
            btn.disabled = false;
            spin.classList.add('hidden');
            label.textContent = idle;
        }
    });
})();
</script>

@endsection

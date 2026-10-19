{{--
    The room booking letter on the activity form (POLICY.md §Event Creation), for every
    activity, internal meetings included. The officer drafts the body (AI, or the template
    when the AI is unavailable), reads and edits it, and sends it signed with their name.
    Pressing Send with the letter still empty drafts it first and stops, so the officer
    always reads what goes to the adviser and the VPA (POLICY.md §AI & Automation).

    Expects $club, and: letterBody (string), aiDrafted ('0'|'1').
--}}
@php
    $me          = auth()->user();
    $hasSignature = (bool) $me?->signature_path;
    $adviserName = $club?->adviserUser?->name;
    $acronym     = $club?->acronym ?: ($club?->name ?? 'Club');
@endphp

<div id="booking-letter" class="border border-[#07873e]/25 rounded-xl overflow-hidden scroll-mt-4">
    <div class="bg-[#07873e]/5 px-4 py-3 border-b border-[#07873e]/15">
        <p class="text-sm font-semibold text-[#07873e]">Room Booking Letter to the VPA <span class="text-red-500">*</span></p>
        <p class="text-xs text-gray-600 mt-0.5">
            Sent with this activity to your club adviser and then to the Vice President for Administration, signed with your name. Draft it from the details above, then read it and change anything that is not right.
        </p>
    </div>

    <div class="p-4 space-y-3">
        <button type="button" id="letter-draft-btn"
                class="w-full flex items-center justify-center gap-2 border-2 border-[#07873e] text-[#07873e]
                       font-semibold text-sm rounded-xl py-2.5 hover:bg-[#07873e]/10 transition-colors
                       disabled:opacity-50 disabled:cursor-not-allowed">
            <svg id="letter-draft-spinner" class="hidden animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z"></path>
            </svg>
            <span id="letter-draft-label">{{ trim($letterBody) === '' ? 'Draft the letter' : 'Draft the letter again' }}</span>
        </button>

        <div id="letter-draft-result" class="hidden text-xs rounded-lg px-3 py-2"></div>

        <p id="letter-stale" class="hidden text-xs rounded-lg px-3 py-2 bg-amber-50 text-amber-900 border border-amber-200">
            You changed the title, date, time or room — the letter below still describes the activity as it was. Draft it again, or edit it to match.
        </p>

        {{-- The letter around the body, as the VPA will read it --}}
        <div class="bg-white border border-gray-200 rounded-lg px-4 py-3 text-[13px] leading-relaxed text-gray-800"
             style="font-family: 'Times New Roman', Times, serif">
            <p class="font-bold">{{ config('vpa.name') }}</p>
            <p>{{ config('vpa.title') }}</p>
            <p class="mt-2">Dear {{ config('vpa.salutation') }},</p>
            <p class="mt-2">{{ \App\Services\BookingLetter::GREETING }}</p>

            <label for="letter_body" class="sr-only">Body of the letter</label>
            <textarea id="letter_body" name="letter_body" rows="8" maxlength="5000"
                      placeholder="Press “Draft the letter” above, or write it yourself: what the activity is, when and where, what it is for, and that the room will be kept clean and orderly."
                      class="mt-2 w-full border rounded-lg px-3 py-2 text-[13px] leading-relaxed focus:outline-none focus:ring-2 focus:ring-[#07873e]/30
                             {{ $errors->has('letter_body') ? 'border-red-400' : 'border-gray-300' }}"
                      style="font-family: 'Times New Roman', Times, serif">{{ $letterBody }}</textarea>
            <input type="hidden" name="letter_ai_drafted" id="letter_ai_drafted" value="{{ $aiDrafted ? '1' : '0' }}">

            <p class="mt-2">{{ \App\Services\BookingLetter::CLOSING }}</p>
            <p class="mt-2">In Saint Columban,</p>
            <p class="mt-1 font-bold underline uppercase">{{ $me?->name }}</p>
            <p>{{ $acronym }} Club Officer</p>
            <p class="mt-2 text-gray-500">Noted by: <span class="uppercase">{{ $adviserName ?? 'your club adviser' }}</span> · Approved by: <span class="uppercase">{{ config('vpa.name') }}</span></p>
        </div>

        @error('letter_body')
            <p class="text-red-500 text-xs">{{ $message }}</p>
        @enderror

        <p class="text-[11px] text-gray-500">
            @if($hasSignature)
                ✓ Signed with the signature on your profile when you send it.
            @else
                You have no signature on file, so the letter will say it was signed electronically.
                <a href="{{ route('profile') }}#signature" class="text-[#07873e] font-semibold underline">Add one on your profile</a> (optional).
            @endif
        </p>
    </div>
</div>

<script>
(() => {
    const form     = document.getElementById('event-form');
    const box      = document.getElementById('booking-letter');
    const textarea = document.getElementById('letter_body');
    const btn      = document.getElementById('letter-draft-btn');
    if (! form || ! box || ! textarea || ! btn) return;

    const flag   = document.getElementById('letter_ai_drafted');
    const result = document.getElementById('letter-draft-result');
    const stale  = document.getElementById('letter-stale');
    const spin   = document.getElementById('letter-draft-spinner');
    const label  = document.getElementById('letter-draft-label');

    const field = (name) => form.querySelector(`[name="${name}"]`);

    const show = (text, tone) => {
        const tones = {
            ok:   'bg-green-50 text-green-800 border border-green-200',
            warn: 'bg-amber-50 text-amber-900 border border-amber-200',
            bad:  'bg-red-50 text-red-800 border border-red-200',
        };
        result.className = 'text-xs rounded-lg px-3 py-2 ' + tones[tone];
        result.textContent = text;
        result.classList.remove('hidden');
    };

    // On the edit form: a letter describing the activity before the officer moved it.
    const material = ['title', 'date', 'time_start', 'time_end', 'venue'];
    const initial  = Object.fromEntries(material.map((name) => [name, field(name)?.value ?? '']));
    const initialLetter = textarea.value;
    const checkStale = () => {
        const moved = material.some((name) => (field(name)?.value ?? '') !== initial[name]);
        stale.classList.toggle('hidden', ! (initialLetter.trim() && moved && textarea.value === initialLetter));
    };
    material.forEach((name) => field(name)?.addEventListener('change', checkStale));
    textarea.addEventListener('input', checkStale);

    const draft = async () => {
        const payload = {
            title:                 field('title')?.value.trim(),
            description:           field('description')?.value.trim(),
            date:                  field('date')?.value,
            time_start:            field('time_start')?.value,
            time_end:              field('time_end')?.value,
            venue:                 field('venue')?.value,
            purpose:               field('purpose')?.value.trim(),
            expected_participants: field('expected_participants')?.value || null,
            activity_type:         field('activity_type')?.value,
            target_year_levels:    [...form.querySelectorAll('[name="target_year_levels[]"]:checked')].map((b) => b.value),
        };

        if (! payload.title || ! payload.date || ! payload.time_start || ! payload.time_end || ! payload.venue) {
            show('Fill in the title, date, start and end time, and room first — the letter is written from them.', 'warn');
            return false;
        }

        btn.disabled = true;
        spin.classList.remove('hidden');
        label.textContent = 'Drafting…';

        try {
            const res = await fetch('{{ route('officer.activities.letter-draft') }}', {
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
                show(data.errors ? Object.values(data.errors)[0][0] : (data.error ?? 'The letter could not be drafted.'), 'bad');
                return false;
            }

            // Placed in the form for the officer to read; never sent on their behalf.
            textarea.value = data.body;
            flag.value = data.ai ? '1' : '0';
            show(data.notice, data.ai ? 'ok' : 'warn');
            checkStale();
            return true;
        } catch (e) {
            show('Network error. Please try again, or write the letter yourself.', 'bad');
            return false;
        } finally {
            btn.disabled = false;
            spin.classList.add('hidden');
            label.textContent = 'Draft the letter again';
        }
    };

    btn.addEventListener('click', () => {
        if (textarea.value.trim() && ! confirm('Replace the letter you have with a new draft?')) return;
        draft();
    });

    // Sending with no letter written: draft it, show it, and stop so it is read before it
    // goes. Registered on window in the capture phase so it runs before the page-wide
    // loading overlay, which would otherwise lock the form for a submission that waits.
    window.addEventListener('submit', async (e) => {
        if (e.target !== form || textarea.value.trim()) return;
        if (! form.checkValidity()) return;

        e.preventDefault();
        e.stopImmediatePropagation();

        if (await draft()) {
            show('Your letter is ready below. Read it, change anything that is not right, then press “Sign & Send to Adviser” again.', 'ok');
        }
        box.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, true);
})();
</script>

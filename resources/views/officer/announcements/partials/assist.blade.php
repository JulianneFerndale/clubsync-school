{{--
    Help writing an announcement: an AI draft when the AI is up, and templates that
    always work — offline, while Gemini is down, or with no AI configured at all
    (POLICY.md § AI & Automation). Whatever lands in the content box is the officer's
    to read and edit; nothing is posted until they press the button themselves.

    Expects: $aiAvailable (configured), $aiReady (configured and not in an outage),
    $club, $aiHeading, $aiPlaceholder. Uses #title-field, #content-field,
    input[name=type] and #ai_assisted_flag from the surrounding form.
--}}
@php
    $announcementTemplates = \App\Services\AnnouncementTemplates::all((string) ($club?->name ?? ''));
@endphp

@if($aiAvailable)
<div class="bg-purple-50 border border-purple-100 rounded-xl p-4">
    <div class="flex items-start gap-3">
        <div class="w-8 h-8 rounded-full bg-purple-100 flex items-center justify-center flex-shrink-0">
            <span class="text-purple-600 text-sm">✦</span>
        </div>
        <div class="flex-1">
            <p class="text-sm font-semibold text-purple-800">{{ $aiHeading }}</p>
            @if($aiReady)
                <p class="text-xs text-purple-500 mt-0.5">Describe what this is about and let AI draft the content.</p>
                <textarea id="ai-context" rows="2" placeholder="{{ $aiPlaceholder }}"
                          class="w-full mt-2 border border-purple-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-purple-300 bg-white resize-none"></textarea>
                <button type="button" id="ai-btn"
                        class="mt-2 flex items-center gap-2 bg-purple-600 text-white text-xs font-semibold rounded-lg px-4 py-2 hover:bg-purple-700 transition-colors">
                    <span id="ai-btn-text">Generate Draft</span>
                    <svg id="ai-spinner" class="hidden w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                    </svg>
                </button>
                <p id="ai-error" class="hidden text-xs text-red-500 mt-1"></p>
            @else
                <p class="text-xs text-purple-600 mt-0.5">The AI is unavailable right now. Start from a template below, or write it yourself.</p>
            @endif
        </div>
    </div>
</div>
@endif

<div class="bg-gray-50 border border-gray-200 rounded-xl p-4">
    <label for="template-picker" class="block text-sm font-semibold text-gray-800">Start from a template</label>
    <p class="text-xs text-gray-500 mt-0.5">Fills in the content with the usual structure. Replace every [bracketed] part before you post.</p>
    <select id="template-picker"
            class="w-full mt-2 border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#07873e]/30">
        <option value="">Choose a template…</option>
        @foreach(['announcement' => 'Announcements', 'letter' => 'Letters'] as $templateType => $groupLabel)
            <optgroup label="{{ $groupLabel }}">
                @foreach($announcementTemplates as $key => $template)
                    @continue($template['type'] !== $templateType)
                    <option value="{{ $key }}">{{ $template['label'] }}</option>
                @endforeach
            </optgroup>
        @endforeach
    </select>
</div>

<script>
(() => {
    const templates = @json($announcementTemplates);
    const content   = () => document.getElementById('content-field');
    const aiFlag    = () => document.getElementById('ai_assisted_flag');
    const PLACEHOLDER = /\[[^\]\n]{2,80}\]/;

    document.getElementById('template-picker')?.addEventListener('change', (event) => {
        const template = templates[event.target.value];
        if (! template) return;

        const field = content();
        if (field.value.trim() !== '' && ! confirm('Replace what is in the content box with this template?')) {
            event.target.value = '';
            return;
        }

        field.value = template.content;
        document.querySelector(`input[name="type"][value="${template.type}"]`)?.click();
        // A template is not AI output, so a template replacing an AI draft clears the flag.
        if (aiFlag()) aiFlag().value = '0';
        event.target.value = '';
        field.focus();
    });

    // Called by the form's submit button: catches a template posted with its brackets still in.
    window.announcementConfirm = (action, question) => {
        if (PLACEHOLDER.test(content().value)) {
            return confirm(`The content still has [bracketed] parts from the template to fill in. ${action} it anyway?`);
        }
        return question ? confirm(question) : true;
    };

    document.getElementById('ai-btn')?.addEventListener('click', async () => {
        const title   = document.getElementById('title-field').value.trim();
        const context = document.getElementById('ai-context').value.trim();
        const type    = document.querySelector('input[name="type"]:checked')?.value ?? 'announcement';
        const btn     = document.getElementById('ai-btn');
        const spinner = document.getElementById('ai-spinner');
        const btnText = document.getElementById('ai-btn-text');
        const errEl   = document.getElementById('ai-error');
        const fail    = (message) => { errEl.textContent = message; errEl.classList.remove('hidden'); };

        errEl.classList.add('hidden');

        if (! title && ! context) {
            fail('Please enter a title or describe the topic above.');
            return;
        }

        btn.disabled = true;
        spinner.classList.remove('hidden');
        btnText.textContent = 'Generating…';

        try {
            const res = await fetch(@json(route('officer.announcements.ai-draft')), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ title, type, context }),
            });

            // An error page is not JSON; say what happened instead of "Network error".
            const data = await res.json().catch(() => ({}));

            if (res.ok && data.content) {
                content().value = data.content;
                if (aiFlag()) aiFlag().value = '1';
            } else if (res.status === 429) {
                fail('You have asked the AI several times in a minute. Wait a moment, or start from a template below.');
            } else {
                fail(data.error ?? 'The AI could not draft this. Start from a template below, or write it yourself.');
            }
        } catch (e) {
            fail('ClubSync could not be reached. Check your connection, or start from a template below — templates work offline.');
        } finally {
            btn.disabled = false;
            spinner.classList.add('hidden');
            btnText.textContent = 'Generate Draft';
        }
    });
})();
</script>

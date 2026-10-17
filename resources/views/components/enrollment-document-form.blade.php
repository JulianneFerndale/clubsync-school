{{--
    The proof-of-enrollment upload: a Certificate of Enrollment (photo or PDF) or the
    front of the SCC school ID. Shared by the last registration step and the page a
    signed-in student re-uploads from, so both explain and behave the same.

    Photos are shrunk in the browser before they are sent (a phone photo is several MB;
    this keeps it to a few hundred KB, under any upload limit, and drops the camera's
    metadata). The server re-encodes them again regardless.
--}}
@props([
    'action',
    'submitLabel'      => 'Verify',
    'canRequestReview' => false,
    'name'             => '',
    'edpNumber'        => '',
    'courseName'       => '',
])

<form method="POST" action="{{ $action }}" enctype="multipart/form-data" data-turbo="false"
      id="enrollmentDocForm" class="space-y-4"
      data-loading="splash" data-loading-message="Checking your document…">
    @csrf
    <input type="hidden" name="action" value="check">

    {{-- What must be on it --}}
    <div class="bg-green-50 border border-green-200 rounded-2xl px-4 py-3.5 text-sm text-gray-700 space-y-2">
        <p class="font-semibold text-green-900">Upload one of these:</p>
        <ul class="list-disc pl-5 space-y-0.5 text-[13px]">
            <li>Your <strong>Certificate of Enrollment</strong> (PDF or a photo of it)</li>
            <li>The <strong>front</strong> of your SCC school ID</li>
        </ul>
        <p class="text-[13px]">It must clearly show the same details you registered with:</p>
        <dl class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-[13px] bg-white rounded-xl border border-green-100 px-3 py-2.5">
            <dt class="text-gray-500">Full name</dt><dd class="font-semibold text-gray-900 break-words">{{ $name }}</dd>
            <dt class="text-gray-500">EDP number</dt><dd class="font-semibold text-gray-900">{{ $edpNumber }}</dd>
            <dt class="text-gray-500">Course</dt><dd class="font-semibold text-gray-900">{{ $courseName }}</dd>
        </dl>
    </div>

    {{-- What did not match last time --}}
    @if($errors->has('document'))
        <div class="bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-red-700 text-sm">
            <p class="font-semibold mb-1">We could not verify that document</p>
            <ul class="list-disc pl-5 space-y-1">
                @foreach($errors->get('document') as $problem)
                    <li>{{ $problem }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- The file --}}
    <label for="enrollmentDocInput"
           class="flex flex-col items-center justify-center gap-2 w-full border-2 border-dashed border-green-300 rounded-2xl px-4 py-6 bg-white cursor-pointer hover:border-green-500 transition-colors text-center">
        <span id="enrollmentDocPreview" class="flex flex-col items-center gap-2">
            <svg class="w-9 h-9 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Zm6-10.125a1.875 1.875 0 1 1-3.75 0 1.875 1.875 0 0 1 3.75 0Zm1.294 6.336a6.721 6.721 0 0 1-3.17.789 6.721 6.721 0 0 1-3.168-.789 3.376 3.376 0 0 1 6.338 0Z"/>
            </svg>
            <span class="text-sm font-semibold text-green-800">Take a photo or choose a file</span>
            <span class="text-xs text-gray-400">JPG, PNG, WEBP or PDF · up to 10 MB</span>
        </span>
        <input type="file" name="document" id="enrollmentDocInput" class="sr-only" required
               accept="image/jpeg,image/png,image/webp,application/pdf,.jpg,.jpeg,.png,.webp,.pdf">
    </label>

    <p class="text-xs text-gray-500 leading-relaxed">
        Lay it flat in good light, with the whole document in the frame and nothing covering the name, EDP number or course.
    </p>

    <button type="submit" id="enrollmentDocSubmit" disabled
            class="flex items-center justify-between w-full bg-gray-300 text-white font-semibold text-[15px] py-4 px-6 rounded-full transition-colors">
        {{ $submitLabel }}
        <span class="w-8 h-8 rounded-full bg-yellow-400 flex items-center justify-center flex-shrink-0">
            <svg class="w-4 h-4 text-green-900" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/>
            </svg>
        </span>
    </button>

    @if($canRequestReview)
        <div class="border border-amber-200 bg-amber-50 rounded-2xl px-4 py-3.5 space-y-2">
            <p class="text-sm font-semibold text-gray-800">Still not going through?</p>
            <p class="text-xs text-gray-600 leading-relaxed">
                If your document is correct and the details really match, the Student Affairs Office can check it by hand.
                Choose the file above, then send it to them. You can use ClubSync as soon as they approve it.
            </p>
            <button type="submit" id="enrollmentDocReview" disabled
                    class="w-full rounded-full border-2 border-green-800 text-green-800 font-semibold text-sm py-3 px-4 transition-colors disabled:opacity-40">
                Send to the Student Affairs Office instead
            </button>
        </div>
    @endif

    <p class="text-[11px] text-gray-400 leading-relaxed">
        Your document is read automatically to check these three details, and is not kept once your enrollment is verified.
        If the Student Affairs Office has to check it, it is stored encrypted until they decide, then deleted.
    </p>
</form>

<script>
(function () {
    const form    = document.getElementById('enrollmentDocForm');
    const input   = document.getElementById('enrollmentDocInput');
    const preview = document.getElementById('enrollmentDocPreview');
    const submit  = document.getElementById('enrollmentDocSubmit');
    const review  = document.getElementById('enrollmentDocReview');
    if (!form || !input) return;

    const MAX_SIDE = 2000;

    const ready = (on) => {
        submit.disabled = !on;
        submit.classList.toggle('bg-gray-300', !on);
        submit.classList.toggle('bg-green-800', on);
        submit.classList.toggle('hover:bg-green-900', on);
        if (review) review.disabled = !on;
    };

    // Which button sent the form travels in a hidden field: the loading overlay
    // disables the buttons as the form submits, and a disabled button's own value
    // would not be sent.
    submit.addEventListener('click', () => { form.elements.action.value = 'check'; });
    if (review) review.addEventListener('click', () => { form.elements.action.value = 'review'; });

    async function shrink(file) {
        const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
        const scale  = Math.min(1, MAX_SIDE / Math.max(bitmap.width, bitmap.height));
        const canvas = document.createElement('canvas');
        canvas.width  = Math.max(1, Math.round(bitmap.width * scale));
        canvas.height = Math.max(1, Math.round(bitmap.height * scale));

        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#fff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(bitmap, 0, 0, canvas.width, canvas.height);

        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.88));
        if (!blob || blob.size >= file.size) return null;   // already small enough

        return new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' });
    }

    function show(file) {
        const size = file.size >= 1048576
            ? (file.size / 1048576).toFixed(1) + ' MB'
            : Math.max(1, Math.round(file.size / 1024)) + ' KB';

        const name = document.createElement('span');
        name.className = 'text-sm font-semibold text-gray-800 break-all';
        name.textContent = file.name;

        const meta = document.createElement('span');
        meta.className = 'text-xs text-gray-400';
        meta.textContent = size + ' · tap to choose a different file';

        preview.replaceChildren();

        if (file.type.startsWith('image/')) {
            const img = document.createElement('img');
            img.src = URL.createObjectURL(file);
            img.alt = 'Selected document';
            img.className = 'max-h-48 rounded-lg border border-gray-200 object-contain';
            preview.append(img);
        } else {
            const badge = document.createElement('span');
            badge.className = 'w-12 h-14 rounded-lg bg-red-50 border border-red-200 text-red-600 text-xs font-bold flex items-center justify-center';
            badge.textContent = 'PDF';
            preview.append(badge);
        }

        preview.append(name, meta);
    }

    input.addEventListener('change', async () => {
        const file = input.files && input.files[0];
        if (!file) { ready(false); return; }

        ready(false);

        if (file.type.startsWith('image/') && 'createImageBitmap' in window && 'DataTransfer' in window) {
            try {
                const smaller = await shrink(file);
                if (smaller) {
                    const transfer = new DataTransfer();
                    transfer.items.add(smaller);
                    input.files = transfer.files;
                }
            } catch (e) {
                // Keep the original: the server re-encodes it anyway.
            }
        }

        show(input.files[0]);
        ready(true);
    });
})();
</script>

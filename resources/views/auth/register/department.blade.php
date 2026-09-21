@extends('layouts.auth')
@section('title', 'Pick Department — ClubSync')

@section('content')
<div class="flex flex-col min-h-screen">

    <div class="flex-1 px-8 pt-12 pb-6">
        <h1 class="text-2xl font-bold text-green-900 leading-snug mb-8">
            Pick your department<br>to continue
        </h1>

        @error('department_id')
            <div class="mb-5 bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-red-700 text-sm">
                {{ $message }}
            </div>
        @enderror

        <form method="POST" action="{{ route('register.department') }}" id="deptForm">
            @csrf

            <div class="grid grid-cols-2 gap-4" id="deptGrid">
                @foreach ($departments as $dept)
                    <label class="dept-card cursor-pointer" for="dept_{{ $dept->id }}">
                        <input type="radio" name="department_id" id="dept_{{ $dept->id }}"
                               value="{{ $dept->id }}"
                               class="sr-only"
                               onchange="selectDept(this)"
                               {{ old('department_id') == $dept->id ? 'checked' : '' }}>
                        <div class="dept-box flex flex-col items-center justify-center gap-2 p-4 rounded-2xl border-2 border-gray-200 bg-white hover:border-green-400 transition-all aspect-square
                                    {{ old('department_id') == $dept->id ? 'border-green-700 bg-green-50' : '' }}"
                             id="deptBox_{{ $dept->id }}">

                            {{-- Department seal placeholder — replace with <img src="/images/dept/{{ $dept->slug }}.png"> --}}
                            <div class="w-16 h-16 rounded-full bg-green-100 border-2 border-green-300 flex items-center justify-center">
                                <span class="text-green-800 font-bold text-xs text-center leading-tight">{{ $dept->short_name }}</span>
                            </div>
                            <span class="text-[10px] font-semibold text-gray-600 text-center leading-tight">{{ $dept->name }}</span>
                        </div>
                    </label>
                @endforeach
            </div>

            {{-- Continue button --}}
            <div class="mt-8">
                <button type="submit" id="continueBtn"
                        class="flex items-center justify-between w-full bg-gray-300 text-white font-semibold text-[15px] py-4 px-6 rounded-full transition-colors"
                        disabled>
                    Continue
                    <span class="w-8 h-8 rounded-full bg-yellow-400 flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4 text-green-900" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/>
                        </svg>
                    </span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function selectDept(radio) {
    // Reset all boxes
    document.querySelectorAll('.dept-box').forEach(function (box) {
        box.classList.remove('border-green-700', 'bg-green-50');
        box.classList.add('border-gray-200');
    });
    // Highlight selected
    const box = document.getElementById('deptBox_' + radio.value);
    box.classList.remove('border-gray-200');
    box.classList.add('border-green-700', 'bg-green-50');
    // Enable button
    const btn = document.getElementById('continueBtn');
    btn.disabled = false;
    btn.classList.remove('bg-gray-300');
    btn.classList.add('bg-green-800', 'hover:bg-green-900');
}

document.addEventListener('DOMContentLoaded', function () {
    const checked = document.querySelector('input[name="department_id"]:checked');
    if (checked) selectDept(checked);
});
</script>
@endsection

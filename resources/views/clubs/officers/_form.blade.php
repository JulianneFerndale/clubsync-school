@php($record = $record ?? null)

{{-- Full Name --}}
<div>
    <label class="block text-sm font-medium text-gray-700 mb-1.5">
        Full Name <span class="text-red-500">*</span>
    </label>
    <input type="text" name="full_name" value="{{ old('full_name', $record?->full_name) }}" required
           class="w-full border rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#07873e]/30
                  {{ $errors->has('full_name') ? 'border-red-400' : 'border-gray-300' }}">
    @error('full_name')
        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
    @enderror
</div>

{{-- Position --}}
<div>
    <label class="block text-sm font-medium text-gray-700 mb-1.5">
        Position <span class="text-red-500">*</span>
    </label>
    <input type="text" name="position" value="{{ old('position', $record?->position) }}" required
           placeholder="e.g. President, Secretary"
           class="w-full border rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#07873e]/30
                  {{ $errors->has('position') ? 'border-red-400' : 'border-gray-300' }}">
    @error('position')
        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
    @enderror
</div>

{{-- Student ID Number --}}
<div>
    <label class="block text-sm font-medium text-gray-700 mb-1.5">
        Student ID Number <span class="text-red-500">*</span>
    </label>
    <input type="text" name="student_id_number" value="{{ old('student_id_number', $record?->student_id_number) }}" required
           class="w-full border rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#07873e]/30
                  {{ $errors->has('student_id_number') ? 'border-red-400' : 'border-gray-300' }}">
    @error('student_id_number')
        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
    @enderror
</div>

{{-- Contact Email --}}
<div>
    <label class="block text-sm font-medium text-gray-700 mb-1.5">
        Contact Email
        <span class="text-xs text-gray-400 font-normal ml-1">(Optional)</span>
    </label>
    <input type="email" name="contact_email" value="{{ old('contact_email', $record?->contact_email) }}"
           class="w-full border rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#07873e]/30
                  {{ $errors->has('contact_email') ? 'border-red-400' : 'border-gray-300' }}">
    @error('contact_email')
        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
    @enderror
</div>

{{-- Academic Year --}}
<div>
    <label class="block text-sm font-medium text-gray-700 mb-1.5">
        Academic Year <span class="text-red-500">*</span>
    </label>
    <input type="text" name="academic_year" value="{{ old('academic_year', $record?->academic_year) }}" required
           placeholder="e.g. 2025–2026"
           class="w-full border rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#07873e]/30
                  {{ $errors->has('academic_year') ? 'border-red-400' : 'border-gray-300' }}">
    @error('academic_year')
        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
    @enderror
</div>

{{-- Semester (radio card) --}}
<div>
    <label class="block text-sm font-medium text-gray-700 mb-2">
        Semester <span class="text-red-500">*</span>
    </label>
    <div class="grid grid-cols-2 gap-3">
        @foreach(['1st' => '1st Semester', '2nd' => '2nd Semester'] as $value => $label)
            <label class="cursor-pointer">
                <input type="radio" name="semester" value="{{ $value }}" class="sr-only peer"
                       {{ old('semester', $record?->semester) === $value ? 'checked' : '' }}>
                <div class="border-2 border-gray-200 rounded-2xl px-4 py-3.5 text-sm font-semibold text-center text-gray-600
                            peer-checked:border-green-700 peer-checked:bg-green-50 peer-checked:text-[#07873e] transition-all">
                    {{ $label }}
                </div>
            </label>
        @endforeach
    </div>
    @error('semester')
        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
    @enderror
</div>

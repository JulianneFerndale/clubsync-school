@extends(auth_role() === 'adviser' ? 'layouts.app-adviser' : 'layouts.app-officer')
@section('title', 'Add Officer Record')
@section('club-name', $club?->name ?? 'ClubSync')
@section('page-title', 'Add Officer Record')

@section('content')

<div class="flex items-center gap-3 bg-[#07873e] px-5 py-4">
    <a href="{{ route('clubs.officers.index') }}" class="text-white/70 hover:text-white transition-colors">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
        </svg>
    </a>
    <h1 class="text-white font-bold text-xl">Add Officer Record</h1>
</div>

<div class="px-4 py-5">

    @if($errors->any())
        <div class="mb-4 bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-red-700 text-sm">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('clubs.officers.store') }}" class="space-y-4">
        @csrf
        @include('clubs.officers._form')

        <div class="grid grid-cols-2 gap-3 pt-2">
            <a href="{{ route('clubs.officers.index') }}"
               class="flex items-center justify-center border-2 border-[#07873e] text-[#07873e] font-semibold text-sm rounded-xl py-3.5 hover:bg-[#07873e]/5 transition-colors">
                Cancel
            </a>
            <button type="submit"
                    class="bg-[#07873e] text-[#F9A825] font-semibold text-sm rounded-xl py-3.5 hover:opacity-90 transition-opacity">
                Save
            </button>
        </div>
    </form>
</div>
@endsection

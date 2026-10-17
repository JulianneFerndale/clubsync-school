@extends(auth_role() === 'adviser' ? 'layouts.app-adviser' : 'layouts.app-officer')
@section('title', 'Compliance Notices — ' . ($club?->name ?? 'ClubSync'))
@section('club-name', $club?->name ?? 'ClubSync')
@section('page-title', 'Compliance Notices')

@section('content')

<div class="bg-[#07873e] px-5 py-4">
    <h1 class="text-white font-bold text-xl">Compliance Notices</h1>
    <p class="text-white/60 text-xs mt-0.5">{{ $club?->name ?? 'No club assigned' }}</p>
</div>

<div class="px-4 py-5 space-y-5">


    @if(! $club)
        <p class="text-sm text-gray-400 text-center py-8">You are not associated with any club.</p>
    @elseif($violations->isEmpty())
        <p class="text-sm text-gray-400 text-center py-8">No compliance notices for your club.</p>
    @else
        <div class="space-y-3">
            @foreach($violations as $violation)
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 {{ $violation->is_resolved ? 'opacity-60' : '' }}">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-sm font-semibold text-gray-800">{{ $violation->gap_label }}</p>
                        <x-status-badge :status="$violation->is_resolved ? 'resolved' : 'pending'" />
                    </div>
                    <p class="text-sm text-gray-700 leading-relaxed">{{ $violation->finalContent() }}</p>

                    @if($violation->is_resolved)
                        <p class="text-xs text-gray-400 mt-2">Resolved on {{ $violation->resolved_at?->format('M j, Y') }}: {{ $violation->resolution_note }}</p>
                    @else
                        <form method="POST" action="{{ route('clubs.violations.resolve', $violation) }}" class="mt-3 space-y-2">
                            @csrf
                            <textarea name="resolution_note" rows="2" required placeholder="Describe how this was resolved..."
                                      class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#07873e]/30"></textarea>
                            <button type="submit" class="bg-[#07873e] text-white text-xs font-semibold rounded-lg px-3 py-1.5 hover:opacity-90 transition-opacity">
                                Mark Resolved
                            </button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

</div>
@endsection

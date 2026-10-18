{{--
    The room booking letter as a sheet of paper on the page: the same letter
    BookingLetter::compose() gives the PDF, laid out as docs/BOOKING OF ROOMS.docx.
    letter: the array from BookingLetter::compose()
--}}
@props(['letter'])

<div {{ $attributes->merge(['class' => 'bg-white border border-gray-200 shadow-sm rounded-lg px-5 py-6 sm:px-10 sm:py-10 text-[13px] sm:text-sm leading-relaxed text-gray-900']) }}
     style="font-family: 'Times New Roman', Times, serif">

    {{-- Letterhead --}}
    <div class="flex items-start justify-between gap-3">
        <div class="w-14 sm:w-20 flex-shrink-0">
            @if($letter['crest'])
                <img src="{{ $letter['crest'] }}" alt="Saint Columban College" class="w-full h-auto">
            @endif
        </div>
        <div class="text-center flex-1 pt-1 sm:pt-3">
            <p>Saint Columban College</p>
            <p>{{ $letter['club'] }}</p>
            <p>Pagadian City</p>
        </div>
        <div class="w-14 sm:w-20 flex-shrink-0">
            @if($letter['club_logo'])
                <img src="{{ $letter['club_logo'] }}" alt="{{ $letter['club'] }}" class="w-full h-auto">
            @endif
        </div>
    </div>

    <p class="mt-8">{{ $letter['date']->format('F j, Y') }}</p>

    <div class="mt-4">
        <p class="font-bold">{{ $letter['addressee'] }}</p>
        <p>{{ $letter['title'] }}</p>
        <p>This institution</p>
    </div>

    <p class="mt-5">{{ $letter['salutation'] }}</p>

    <p class="mt-4 text-justify">{{ $letter['greeting'] }}</p>

    @foreach($letter['paragraphs'] as $paragraph)
        <p class="mt-4 text-justify">{{ $paragraph }}</p>
    @endforeach

    <p class="mt-4">{{ $letter['closing'] }}</p>

    <p class="mt-6">In Saint Columban,</p>

    @foreach(['officer', 'adviser', 'vpa'] as $role)
        @php($signer = $letter['signers'][$role])
        <div class="{{ $loop->first ? 'mt-2' : 'mt-6' }}">
            @if($signer['caption'])
                <p>{{ $signer['caption'] }}</p>
            @endif

            <div class="h-14 flex items-end">
                @if($signer['signed'] && $signer['image'])
                    <img src="{{ $signer['image'] }}" alt="Signature of {{ $signer['name'] }}" class="max-h-14 w-auto">
                @elseif($signer['signed'])
                    <span class="text-[11px] italic text-[#07873e]" style="font-family: Inter, sans-serif">
                        Signed electronically via ClubSync · {{ $signer['signed_at']->format('M j, Y g:i A') }}
                    </span>
                @else
                    <span class="text-[11px] italic text-gray-400" style="font-family: Inter, sans-serif">Not signed yet</span>
                @endif
            </div>

            <p class="font-bold underline uppercase">{{ $signer['name'] }}</p>
            <p>{{ $signer['position'] }}</p>
        </div>
    @endforeach

    @if($letter['returned'])
        <div class="mt-8 border border-red-200 bg-red-50 text-red-700 rounded px-3 py-2 text-xs" style="font-family: Inter, sans-serif">
            Returned by {{ $letter['returned']['by'] }}.@if($letter['returned']['remarks']) Remarks: {{ $letter['returned']['remarks'] }}@endif
        </div>
    @endif
</div>

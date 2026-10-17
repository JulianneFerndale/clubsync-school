{{--
    Activities held per month across the semester: one cell per month, darker for
    more, with the count printed in the cell so the shade is never the only cue.
    Months still ahead are outlined and empty.
--}}
<div class="flex gap-1" role="list" aria-label="Activities held per month">
    @foreach($monthly as $key => $month)
        @php
            $shade = match (true) {
                $month['future']     => 'border border-dashed border-gray-200 text-gray-300',
                $month['held'] === 0 => 'bg-gray-100 text-gray-400',
                $month['held'] === 1 => 'bg-[#07873e]/25 text-[#05602c]',
                $month['held'] === 2 => 'bg-[#07873e]/60 text-white',
                default              => 'bg-[#07873e] text-white',
            };
            $tip = \Carbon\Carbon::createFromFormat('Y-m', $key)->format('F Y') . ': '
                . ($month['future'] ? 'still ahead'
                    : $month['held'] . ' ' . \Illuminate\Support\Str::plural('activity', $month['held']) . ' held'
                      . ($month['turnout'] !== null ? ', ' . round($month['turnout'] * 100) . '% turnout' : ''));
        @endphp
        <div role="listitem" title="{{ $tip }}" aria-label="{{ $tip }}" class="flex flex-col items-center gap-0.5">
            <span class="w-7 h-7 rounded-md flex items-center justify-center text-[11px] font-bold tabular-nums {{ $shade }}">
                {{ $month['future'] ? '' : $month['held'] }}
            </span>
            <span class="text-[9px] text-gray-400 leading-none">{{ $month['label'] }}</span>
        </div>
    @endforeach
</div>

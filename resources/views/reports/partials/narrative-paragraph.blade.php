@php
    $style = sprintf(
        'font-size: %spt; text-align: %s; margin: %spt 0 %spt; line-height: %s;%s%s%s',
        $block['size'] ?? 12,
        $block['align'] ?? 'left',
        $block['space_before'] ?? 0,
        $block['space_after'] ?? 6,
        1.2 * ($block['line'] ?? 1.0),
        ! empty($block['indent']) ? ' text-indent: 36pt;' : '',
        ! empty($block['bold']) ? ' font-weight: bold;' : '',
        ! empty($block['italic']) ? ' font-style: italic;' : '',
    );
@endphp
<p class="{{ ! empty($block['bullet']) ? 'bullet' : '' }}" style="{{ $style }}">@if(! empty($block['bullet']))&bull;&nbsp;&nbsp;&nbsp;@endif @foreach($block['runs'] ?? [] as $run)<span style="{{ ! empty($run['bold']) ? 'font-weight: bold;' : '' }}{{ ! empty($run['italic']) ? 'font-style: italic;' : '' }}{{ ! empty($run['underline']) ? 'text-decoration: underline;' : '' }}">@if(! empty($run['sup']))<sup>{{ $run['text'] }}</sup>@else{{ $run['text'] }}@endif</span>@endforeach @if(($block['runs'] ?? []) === [] || implode('', array_column($block['runs'], 'text')) === '')&nbsp;@endif</p>

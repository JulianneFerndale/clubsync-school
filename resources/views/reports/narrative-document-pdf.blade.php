@php
    use App\Services\NarrativeDocuments\PdfWriter;

    // Points everywhere, to match the Word file's measurements.
    $contentWidth = $page['width'] - $page['left'] - $page['right'];
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $documents[0]->title }}</title>
    <style>
        @page { margin: {{ $page['top'] }}pt {{ $page['right'] }}pt {{ $page['bottom'] }}pt {{ $page['left'] }}pt; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 12pt; color: #000; line-height: 1.2; }
        .banner { position: fixed; top: -{{ $page['top'] }}pt; left: -{{ $page['left'] }}pt; width: {{ $page['width'] }}pt; height: {{ $page['banner_height'] }}pt; }
        .break { page-break-before: always; height: 0; margin: 0; padding: 0; }
        p { margin: 0; }
        .bullet { padding-left: 27pt; text-indent: -15pt; }
        table.grid { width: {{ $contentWidth }}pt; border-collapse: collapse; margin: 0 0 6pt; }
        table.grid th, table.grid td { border: 0.75pt solid #000; padding: 3.5pt 5.5pt; vertical-align: middle; }
        table.grid th { background: #E3F1E6; font-weight: bold; text-align: center; }
        table.grid tr { page-break-inside: avoid; }
        table.photos { width: {{ $contentWidth }}pt; border-collapse: collapse; margin: 0 0 6pt; }
        table.photos td { width: 50%; padding: 4pt; text-align: center; vertical-align: top; }
        table.photos tr { page-break-inside: avoid; }
        .caption { font-size: 10pt; font-style: italic; text-align: center; margin: 2pt 0 8pt; }
        .center { text-align: center; }
    </style>
</head>
<body>
    @if($banner)
        <img class="banner" src="{{ PdfWriter::src($banner) }}" alt="">
    @endif

    @foreach($documents as $docIndex => $document)
        @if($docIndex > 0)
            <div class="break"></div>
        @endif

        @foreach($document->blocks as $block)
            @switch($block['type'])
                @case('pagebreak')
                    <div class="break"></div>
                    @break

                @case('paragraph')
                    @include('reports.partials.narrative-paragraph', ['block' => $block])
                    @break

                @case('table')
                    <table class="grid" style="font-size: {{ $block['size'] }}pt">
                        <thead>
                            <tr>
                                @foreach($block['columns'] as $column)
                                    <th style="width: {{ round($column['width'] * 100, 2) }}%">{{ $column['label'] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($block['rows'] as $cells)
                                <tr>
                                    @foreach($block['columns'] as $i => $column)
                                        <td style="text-align: {{ $column['align'] ?? 'left' }}">{{ $cells[$i] ?? '' }}</td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr><td colspan="{{ count($block['columns']) }}" class="center"><em>{{ $block['empty'] }}</em></td></tr>
                            @endforelse
                            @if($block['total'] ?? null)
                                <tr>
                                    @foreach($block['columns'] as $i => $column)
                                        <td style="text-align: {{ $column['align'] ?? 'left' }}; font-weight: bold">{{ $block['total'][$i] ?? '' }}</td>
                                    @endforeach
                                </tr>
                            @endif
                        </tbody>
                    </table>
                    @break

                @case('photos')
                    @php $photos = array_values(array_filter($block['photos'], fn ($p) => $p['image']->bytes() !== '')); @endphp
                    @if($photos === [])
                        @if($block['empty'] !== '')
                            <p class="center" style="margin-top: 24pt"><em>{{ $block['empty'] }}</em></p>
                        @endif
                    @elseif((int) $block['columns'] === 1)
                        @foreach($photos as $photo)
                            @php [$w, $h] = $photo['image']->fit($contentWidth, (float) $block['max_height']); @endphp
                            <p class="center" style="margin-top: 6pt"><img src="{{ PdfWriter::src($photo['image']) }}" style="width: {{ $w }}pt; height: {{ $h }}pt" alt=""></p>
                            @if($photo['caption'] !== '')
                                <p class="caption">{{ $photo['caption'] }}</p>
                            @endif
                        @endforeach
                    @else
                        <table class="photos">
                            @foreach(array_chunk($photos, 2) as $pair)
                                <tr>
                                    @for($i = 0; $i < 2; $i++)
                                        <td>
                                            @isset($pair[$i])
                                                @php [$w, $h] = $pair[$i]['image']->fit($contentWidth / 2 - 10, (float) $block['max_height']); @endphp
                                                <img src="{{ PdfWriter::src($pair[$i]['image']) }}" style="width: {{ $w }}pt; height: {{ $h }}pt" alt="">
                                                <p class="caption">{{ $pair[$i]['caption'] }}</p>
                                            @endisset
                                        </td>
                                    @endfor
                                </tr>
                            @endforeach
                        </table>
                    @endif
                    @break

                @case('cover')
                    @php
                        $landscape = $document->landscape;
                        $big       = $landscape ? 24 : 28;
                        $logos     = $block['logos'];
                        if ($landscape && $block['club_logo']) {
                            $logos[] = $block['club_logo'];
                        }
                        $box = $landscape ? 120 : 175;
                        // Built here so no whitespace falls between "1" and its raised "st".
                        $term = collect($block['term'])->map(fn ($run) => ! empty($run['sup'])
                            ? '<sup style="font-size: ' . round(($big - 2) * 0.6) . 'pt">' . e($run['text']) . '</sup>'
                            : e($run['text']))->implode('');
                    @endphp
                    <p class="center" style="font-size: {{ $big }}pt; font-weight: bold; margin: {{ $landscape ? 6 : 60 }}pt 0 {{ $landscape ? 10 : 40 }}pt">{{ $block['title'] }}</p>
                    <p class="center" style="font-size: {{ $big - 2 }}pt; font-weight: bold; margin-bottom: 4pt">{!! $term !!}</p>
                    @if($block['year'])
                        <p class="center" style="font-size: {{ $big - 2 }}pt; font-weight: bold; margin-bottom: {{ $landscape ? 14 : 40 }}pt">{{ $block['year'] }}</p>
                    @endif
                    <p class="center" style="margin-bottom: {{ $landscape ? 10 : 50 }}pt">
                        @foreach($logos as $logo)
                            @php [$w, $h] = $logo->fit($box, $box); @endphp
                            <img src="{{ PdfWriter::src($logo) }}" style="width: {{ $w }}pt; height: {{ $h }}pt; margin: 0 {{ $landscape ? 18 : 28 }}pt; vertical-align: middle" alt="">
                        @endforeach
                    </p>
                    @if(! $landscape && $block['club_logo'])
                        @php [$w, $h] = $block['club_logo']->fit(130, 130); @endphp
                        <p class="center" style="margin-bottom: 6pt"><img src="{{ PdfWriter::src($block['club_logo']) }}" style="width: {{ $w }}pt; height: {{ $h }}pt" alt=""></p>
                    @endif
                    <p class="center" style="font-size: {{ $landscape ? 15 : 17 }}pt; font-weight: bold; margin-bottom: 24pt">{{ $block['club'] }}</p>
                    @foreach($block['lines'] as $runs)
                        @include('reports.partials.narrative-paragraph', ['block' => ['runs' => $runs, 'size' => 14, 'align' => 'center', 'space_after' => 2]])
                    @endforeach
                    <p class="center" style="font-size: 14pt; font-weight: bold; margin-top: {{ $landscape ? 10 : 50 }}pt">{{ $block['date'] }}</p>
                    @break
            @endswitch
        @endforeach
    @endforeach
</body>
</html>

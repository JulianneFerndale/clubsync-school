<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Room Booking Letter — {{ $letter['club'] }}</title>
    <style>
        /* Letter paper with the margins of docs/BOOKING OF ROOMS.docx: 1 in all round,
           1.25 in on the right. Times is a PDF core font, so nothing is embedded. */
        @page { margin: 0.7in 1.25in 0.75in 1in; }
        body { font-family: "Times New Roman", Times, serif; font-size: 12pt; color: #000; line-height: 1.2; }
        p { margin: 0; }

        table.head { width: 100%; border-collapse: collapse; }
        table.head td { vertical-align: top; }
        table.head td.mark { width: 1.05in; }
        table.head td.mark img { width: 1in; height: 1in; }
        table.head td.right { text-align: right; }
        table.head td.text { text-align: center; padding-top: 0.2in; }

        .date { margin-top: 0.3in; }
        .to { margin-top: 0.16in; }
        .bold { font-weight: bold; }
        .gap { margin-top: 11pt; }
        .justify { text-align: justify; }

        .signer { margin-top: 11pt; page-break-inside: avoid; }
        .signer .space { height: 0.5in; position: relative; }
        .signer .space img { position: absolute; bottom: -4pt; left: 0; max-height: 0.62in; max-width: 2.4in; }
        .signer .esign { position: absolute; bottom: 2pt; left: 0; font-family: Helvetica, Arial, sans-serif; font-size: 7.5pt; color: #07873e; font-style: italic; }
        .signer .name { font-weight: bold; text-decoration: underline; text-transform: uppercase; }

        .returned { margin-top: 18pt; border: 1px solid #fca5a5; background: #fef2f2; color: #b91c1c; padding: 6pt 8pt;
                    font-family: Helvetica, Arial, sans-serif; font-size: 8.5pt; }
        .foot { position: fixed; bottom: -0.42in; left: 0; right: 0; font-family: Helvetica, Arial, sans-serif; font-size: 7pt; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>

<div class="foot">{{ $letter['reference'] }} · {{ $letter['status'] }}</div>

<table class="head">
    <tr>
        <td class="mark">
            @if($letter['crest'])
                <img src="{{ $letter['crest'] }}" alt="Saint Columban College">
            @endif
        </td>
        <td class="text">
            <p>Saint Columban College</p>
            <p>{{ $letter['club'] }}</p>
            <p>Pagadian City</p>
        </td>
        <td class="mark right">
            @if($letter['club_logo'])
                <img src="{{ $letter['club_logo'] }}" alt="{{ $letter['club'] }}">
            @endif
        </td>
    </tr>
</table>

<p class="date">{{ $letter['date']->format('F j, Y') }}</p>

<div class="to">
    <p class="bold">{{ $letter['addressee'] }}</p>
    <p>{{ $letter['title'] }}</p>
    <p>This institution</p>
</div>

<p class="gap">{{ $letter['salutation'] }}</p>

<p class="gap justify">{{ $letter['greeting'] }}</p>

@foreach($letter['paragraphs'] as $paragraph)
    <p class="gap justify">{{ $paragraph }}</p>
@endforeach

<p class="gap">{{ $letter['closing'] }}</p>

<p class="gap">In Saint Columban,</p>

@foreach(['officer', 'adviser', 'vpa'] as $role)
    @php($signer = $letter['signers'][$role])
    <div class="signer">
        @if($signer['caption'])
            <p>{{ $signer['caption'] }}</p>
        @endif
        <div class="space">
            @if($signer['signed'] && $signer['image'])
                <img src="{{ $signer['image'] }}" alt="">
            @elseif($signer['signed'])
                <span class="esign">Signed electronically via ClubSync, {{ $signer['signed_at']->format('M j, Y g:i A') }}</span>
            @endif
        </div>
        <p class="name">{{ $signer['name'] }}</p>
        <p>{{ $signer['position'] }}</p>
    </div>
@endforeach

@if($letter['returned'])
    <div class="returned">
        Returned by {{ $letter['returned']['by'] }}.@if($letter['returned']['remarks']) Remarks: {{ $letter['returned']['remarks'] }}@endif
    </div>
@endif

</body>
</html>

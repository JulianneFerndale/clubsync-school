<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Receipt {{ $payment->receipt_number }}</title>
    <style>
        @page { margin: 28mm 20mm; }
        /* Helvetica is a PDF core font, so nothing is embedded: the text costs a few KB
           instead of 1.2 MB, which matters when a treasurer issues one per member.
           Latin-1 covers the names this serves, ñ included. */
        body { font-family: Helvetica, Arial, sans-serif; font-size: 11px; color: #1f2937; }

        .head { border-bottom: 2px solid #07873e; padding-bottom: 10px; }
        .head table { width: 100%; border-collapse: collapse; }
        .head td { vertical-align: middle; }
        .head td.mark { width: 22mm; text-align: center; }
        .head td.mark img { width: 18mm; height: 18mm; }
        .head td.text { text-align: center; }
        .head .school { font-size: 14px; font-weight: bold; color: #07873e; letter-spacing: .5px; }
        .head .place  { font-size: 10px; color: #6b7280; margin-top: 2px; }
        .head .club   { font-size: 11px; margin-top: 6px; font-weight: bold; }
        /* Stands in for a club with no logo, the way the app's club cards do. Centred with
           padding: dompdf sets a tall line-height's text low in its box. */
        .head .initials { width: 18mm; height: 11.4mm; padding-top: 6.6mm; margin: 0 auto; border-radius: 9mm;
                          background: #07873e; color: #ffffff; font-size: 16px; line-height: 1; font-weight: bold; text-align: center; }

        .title { text-align: center; font-size: 13px; font-weight: bold; letter-spacing: 3px; margin: 18px 0 4px; }
        .no    { text-align: center; font-size: 12px; font-weight: bold; color: #07873e; }
        .date  { text-align: center; font-size: 10px; color: #6b7280; margin-top: 2px; }

        table.body { width: 100%; margin-top: 18px; border-collapse: collapse; }
        table.body td { padding: 6px 0; vertical-align: top; }
        td.label { width: 34%; color: #6b7280; }
        td.value { font-weight: bold; }

        .amount { margin-top: 16px; border: 1px solid #d1d5db; padding: 10px 12px; }
        .amount .figure { font-size: 18px; font-weight: bold; color: #07873e; }
        .amount .words  { font-size: 10px; color: #6b7280; margin-top: 3px; font-style: italic; }

        .sign { margin-top: 42px; width: 100%; }
        .sign td { width: 50%; font-size: 10px; color: #6b7280; padding-top: 4px; }
        .sign .line { border-top: 1px solid #9ca3af; padding-top: 4px; }

        .note { margin-top: 28px; font-size: 9px; color: #9ca3af; text-align: center; line-height: 1.5; }
    </style>
</head>
<body>

@php
    // Inline and scaled down: dompdf does not load images by URL (see PdfImage).
    $crest    = \App\Support\PdfImage::dataUri('/images/scc-crest.png');
    $clubLogo = \App\Support\PdfImage::dataUri($club->profile_photo_url);
@endphp

<div class="head">
    <table>
        <tr>
            <td class="mark">
                @if($crest)
                    <img src="{{ $crest }}" alt="Saint Columban College">
                @endif
            </td>
            <td class="text">
                <div class="school">SAINT COLUMBAN COLLEGE</div>
                <div class="place">Pagadian City</div>
                <div class="club">{{ $club->name }}{{ $club->acronym ? ' (' . $club->acronym . ')' : '' }}</div>
            </td>
            <td class="mark">
                @if($clubLogo)
                    <img src="{{ $clubLogo }}" alt="{{ $club->name }}">
                @else
                    <div class="initials">{{ strtoupper(substr($club->acronym ?: $club->name, 0, 2)) }}</div>
                @endif
            </td>
        </tr>
    </table>
</div>

<div class="title">OFFICIAL RECEIPT</div>
<div class="no">No. {{ $payment->receipt_number }}</div>
<div class="date">{{ $payment->confirmed_at->format('F j, Y') }} at {{ $payment->confirmed_at->format('g:i A') }}</div>

<table class="body">
    <tr>
        <td class="label">Received from</td>
        <td class="value">{{ $payment->user?->name ?? 'Unknown member' }}</td>
    </tr>
    @if($payment->user?->edp_number)
    <tr>
        <td class="label">EDP Number</td>
        <td class="value">{{ $payment->user->edp_number }}</td>
    </tr>
    @endif
    <tr>
        <td class="label">In payment of</td>
        <td class="value">{{ $fee->title }}</td>
    </tr>
    @if($fee->academic_period)
    <tr>
        <td class="label">Period</td>
        <td class="value">{{ $fee->academic_period }}</td>
    </tr>
    @endif
</table>

<div class="amount">
    <div class="figure">PHP {{ number_format((float) $fee->amount, 2) }}</div>
    <div class="words">{{ ucfirst(peso_in_words((float) $fee->amount)) }}</div>
</div>

<table class="sign">
    <tr>
        <td>&nbsp;</td>
        <td class="line">
            <strong>{{ $payment->confirmer?->name ?? 'Club Treasurer' }}</strong><br>
            Received by
        </td>
    </tr>
</table>

<div class="note">
    This receipt is issued by {{ $club->name }} through ClubSync and is valid for the payment recorded above.<br>
    Generated {{ now()->format('F j, Y g:i A') }}.
</div>

</body>
</html>

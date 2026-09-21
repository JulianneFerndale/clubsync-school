<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $data['club'] }} — {{ $data['semester'] }} Narrative Report</title>
    <style>
        @page { margin: 48px 52px; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 11px; color: #1f2937; line-height: 1.5; }
        .school { text-align: center; font-size: 10px; color: #4b5563; letter-spacing: 0.5px; }
        h1 { text-align: center; font-size: 17px; margin: 6px 0 2px; }
        .subtitle { text-align: center; font-size: 12px; margin: 0; }
        .period { text-align: center; font-size: 10px; color: #6b7280; margin: 2px 0 18px; }
        h2 { font-size: 12px; margin: 20px 0 8px; padding-bottom: 3px; border-bottom: 1px solid #07873e; color: #07873e; }
        h3 { font-size: 11px; margin: 12px 0 2px; }
        p { margin: 0 0 8px; text-align: justify; }
        table { width: 100%; border-collapse: collapse; }
        .meta td { padding: 2px 0; }
        .meta .label { color: #6b7280; width: 130px; }
        .summary th { background: #f3f4f6; font-size: 9.5px; text-align: left; padding: 5px 5px; border: 1px solid #d1d5db; }
        .summary td { font-size: 9.5px; padding: 5px 5px; border: 1px solid #e5e7eb; vertical-align: top; }
        .summary .num { text-align: right; white-space: nowrap; }
        .summary tfoot td { font-weight: bold; background: #f9fafb; }
        .detail .label { color: #6b7280; }
        .note { font-size: 9px; color: #6b7280; margin-top: 22px; border-top: 1px solid #e5e7eb; padding-top: 6px; }
        .signature { margin-top: 36px; width: 240px; }
        .signature .line { border-top: 1px solid #1f2937; padding-top: 3px; text-align: center; }
        .signature .role { text-align: center; font-size: 9.5px; color: #4b5563; }
    </style>
</head>
<body>
    <p class="school">SAINT COLUMBAN COLLEGE · PAGADIAN CITY</p>
    <h1>Semester Narrative Report</h1>
    <p class="subtitle">{{ $data['club'] }}</p>
    <p class="period">{{ $data['semester'] }} · {{ $data['start'] }} – {{ $data['end'] }}</p>

    <table class="meta">
        <tr><td class="label">Submitted to</td><td>Director of Student Affairs</td></tr>
        <tr><td class="label">Club adviser</td><td>{{ $data['adviser'] ?? 'Not assigned' }}</td></tr>
        <tr><td class="label">Date submitted</td><td>{{ $report->submitted_at->format('F j, Y') }}</td></tr>
        <tr><td class="label">Activities completed</td><td>{{ $data['totals']['activities'] }}</td></tr>
    </table>

    <h2>I. Narrative</h2>
    @foreach($paragraphs as $paragraph)
        <p>{{ $paragraph }}</p>
    @endforeach

    <h2>II. Summary of Activities</h2>
    <table class="summary">
        <thead>
            <tr>
                <th>#</th>
                <th>Activity</th>
                <th>Type</th>
                <th>Date and time</th>
                <th>Venue</th>
                <th class="num">Expected</th>
                <th class="num">Attended</th>
                <th class="num">Turnout</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['activities'] as $i => $activity)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $activity['title'] }}</td>
                    <td>{{ $activity['type'] }}</td>
                    <td>{{ $activity['date'] }}<br>{{ $activity['time'] }}</td>
                    <td>{{ $activity['venue'] }}</td>
                    <td class="num">{{ $activity['expected'] }}</td>
                    <td class="num">{{ $activity['attendance_recorded'] ? $activity['attended'] : 'Not recorded' }}</td>
                    <td class="num">{{ $activity['attendance_recorded'] && $activity['turnout'] !== null ? $activity['turnout'] . '%' : '—' }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="5">Total ({{ $data['totals']['unique_attendees'] }} different members attended)</td>
                <td class="num">{{ $data['totals']['expected'] }}</td>
                <td class="num">{{ $data['totals']['attended'] }}</td>
                <td class="num">{{ $data['totals']['turnout'] !== null ? $data['totals']['turnout'] . '%' : '—' }}</td>
            </tr>
        </tfoot>
    </table>

    <h2>III. Activity Details</h2>
    @foreach($data['activities'] as $i => $activity)
        <div class="detail">
            <h3>{{ $i + 1 }}. {{ $activity['title'] }}</h3>
            <p><span class="label">Description:</span> {{ $activity['description'] }}</p>
            <p><span class="label">Purpose / objectives:</span> {{ $activity['purpose'] }}</p>
            @if($activity['post_report'])
                <p><span class="label">Officer's post-activity report:</span> {{ $activity['post_report'] }}</p>
            @endif
        </div>
    @endforeach

    <div class="signature">
        <div class="line">{{ $reviewer?->name ?? $data['adviser'] }}</div>
        <div class="role">Club Adviser — reviewed and approved</div>
    </div>

    <p class="note">
        Sections II and III are taken directly from the ClubSync activity and attendance records. Attendance counts members checked in on each activity's attendance sheet.
        @if($report->ai_available)
            The narrative in Section I was drafted with AI assistance from those records and reviewed{{ $report->adviser_edited_content ? ', edited' : '' }} and approved by the club adviser before submission.
        @else
            The narrative in Section I was written by the club adviser.
        @endif
    </p>
</body>
</html>

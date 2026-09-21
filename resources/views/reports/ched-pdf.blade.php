<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #1f2937; }
        h1 { font-size: 16px; margin-bottom: 4px; }
        h2 { font-size: 13px; margin-top: 18px; margin-bottom: 6px; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 4px 6px; vertical-align: top; }
        .label { color: #6b7280; width: 160px; }
        .roster th { text-align: left; border-bottom: 1px solid #d1d5db; font-size: 11px; color: #6b7280; }
        .roster td { border-bottom: 1px solid #f3f4f6; }
        .note { color: #6b7280; font-style: italic; }
    </style>
</head>
<body>
    <h1>CHED Activity Report</h1>
    <p>{{ $club_name }}</p>

    <table>
        <tr><td class="label">Adviser</td><td>{{ $adviser_name }}</td></tr>
        <tr><td class="label">Activity Title</td><td>{{ $activity_title }}</td></tr>
        <tr><td class="label">Activity Type</td><td>{{ ucfirst(str_replace('_', ' ', $activity_type)) }}</td></tr>
        <tr><td class="label">Date</td><td>{{ $date }}</td></tr>
        <tr><td class="label">Time</td><td>{{ $time }}</td></tr>
        <tr><td class="label">Venue</td><td>{{ $venue }}</td></tr>
        <tr><td class="label">Officer-in-Charge</td><td>{{ $officer_in_charge }}</td></tr>
    </table>

    <h2>Objectives</h2>
    <p>{{ $objectives }}</p>

    <h2>Description</h2>
    <p>{{ $description }}</p>

    <h2>Attendance and Engagement</h2>
    @if($attendance_recorded)
        <table>
            <tr><td class="label">Participants Present</td><td>{{ $participant_count }} (from the activity attendance record)</td></tr>
            <tr><td class="label">Expected Participants</td><td>{{ $expected_participants }} ({{ $turnout_vs_expected }}% of the estimate)</td></tr>
            <tr><td class="label">Eligible Club Members</td><td>{{ $eligible_members }}</td></tr>
            <tr><td class="label">Attendance Rate</td><td>{{ $attendance_rate }}% of eligible members</td></tr>
            <tr><td class="label">Absent</td><td>{{ $absent_count }}</td></tr>
            @if($no_show_count > 0)
                <tr><td class="label">Registered, No Time-In</td><td>{{ $no_show_count }}</td></tr>
            @endif
        </table>

        <h2>Participants</h2>
        <table class="roster">
            <tr><th>Name</th><th>Time In</th><th>Time Out</th></tr>
            @foreach($participants as $participant)
                <tr>
                    <td>{{ $participant['name'] }}</td>
                    <td>{{ $participant['time_in'] }}</td>
                    <td>{{ $participant['time_out'] }}</td>
                </tr>
            @endforeach
        </table>
    @else
        <p class="note">No attendance was recorded for this activity, so no participant list can be certified.
            The club has {{ $eligible_members }} eligible member(s) and the officer expected {{ $expected_participants }} participant(s).</p>
    @endif
</body>
</html>

<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ChedReportExport implements FromArray, WithHeadings
{
    public function __construct(private array $data) {}

    public function headings(): array
    {
        return ['Field', 'Value', ''];
    }

    public function array(): array
    {
        $rows = [
            ['Club', $this->data['club_name'], ''],
            ['Adviser', $this->data['adviser_name'], ''],
            ['Activity Title', $this->data['activity_title'], ''],
            ['Activity Type', $this->data['activity_type'], ''],
            ['Date', $this->data['date'], ''],
            ['Time', $this->data['time'], ''],
            ['Venue', $this->data['venue'], ''],
            ['Officer-in-Charge', $this->data['officer_in_charge'], ''],
            ['Objectives', $this->data['objectives'], ''],
            ['Description', $this->data['description'], ''],
            ['', '', ''],
            ['Participants Present', $this->data['participant_count'], 'from the activity attendance record'],
            ['Expected Participants', $this->data['expected_participants'], $this->data['turnout_vs_expected'] . '% of the estimate'],
            ['Eligible Club Members', $this->data['eligible_members'], ''],
            ['Attendance Rate', $this->data['attendance_rate'] . '%', 'of eligible members'],
            ['Absent', $this->data['absent_count'], ''],
        ];

        if ($this->data['no_show_count'] > 0) {
            $rows[] = ['Registered, No Time-In', $this->data['no_show_count'], ''];
        }

        $rows[] = ['', '', ''];

        if (! $this->data['attendance_recorded']) {
            $rows[] = ['Participants', 'No attendance was recorded for this activity.', ''];

            return $rows;
        }

        $rows[] = ['Participants', 'Time In', 'Time Out'];

        foreach ($this->data['participants'] as $participant) {
            $rows[] = [$participant['name'], $participant['time_in'], $participant['time_out']];
        }

        return $rows;
    }
}

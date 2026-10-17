<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One of a club's semester-end reports, as its president submitted it to the DSA.
 */
class ClubReportSubmission extends Model
{
    /**
     * The reports the DSA collects each semester, in the order the templates list them.
     * `template` is the file bundled in docs/resources-dsa that ReportTemplateSeeder publishes.
     */
    public const TYPES = [
        'accomplishment_report' => [
            'label'       => 'Accomplishment & Documentation Report',
            'prepared_by' => 'Club President',
            'template'    => 'Copy of President - Accomplishment Report.docx',
            'download_as' => 'Accomplishment Report Template.docx',
            'description' => 'Every activity the club held this semester, each with photos, attendance, program, minutes, communication letters and its evaluation meeting (Annexes A–F).',
        ],
        'minutes_of_meetings' => [
            'label'       => 'Minutes of Meetings',
            'prepared_by' => 'Club Secretary',
            'template'    => 'Copy of Secretary - Minutes of Meetings.docx',
            'download_as' => 'Minutes of Meetings Template.docx',
            'description' => 'A list of the semester\'s regular and special meetings, followed by the minutes of each.',
        ],
        'financial_report' => [
            'label'       => 'Financial Transactions & Liquidation Report',
            'prepared_by' => 'Club Treasurer & Auditor',
            'template'    => 'Copy of Treasurer & Auditor - Financial Report.docx',
            'download_as' => 'Financial and Liquidation Report Template.docx',
            'description' => 'Collections and expenses per activity, with the approved budget, the liquidation and the receipts. Prepared by the treasurer and audited by the auditor.',
        ],
        'property_inventory' => [
            'label'       => 'Property Inventory Report',
            'prepared_by' => 'Club Officers',
            'template'    => 'Copy of Property Inventory.docx',
            'download_as' => 'Property Inventory Template.docx',
            'description' => 'Every property the club holds, with its quantity and condition.',
        ],
        'communication_letters' => [
            'label'       => 'Communication Letters & Publication Materials',
            'prepared_by' => 'Club PRO',
            'template'    => 'Copy of PRO-Letters and Pubmats.docx',
            'download_as' => 'Communication Letters and Pubmats Template.docx',
            'description' => 'The semester\'s communication letters and the information the club posted on social media.',
        ],
    ];

    public const STATUSES = [
        'submitted' => 'Awaiting DSA review',
        'accepted'  => 'Accepted',
        'returned'  => 'Returned for revision',
    ];

    protected $fillable = [
        'club_id', 'semester_id', 'report_type',
        'file_path', 'file_name', 'file_size',
        'status', 'submitted_by', 'submitted_at',
        'reviewed_by', 'reviewed_at', 'review_remarks',
    ];

    protected $casts = [
        'file_size'    => 'integer',
        'submitted_at' => 'datetime',
        'reviewed_at'  => 'datetime',
    ];

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public static function typeLabel(string $type): string
    {
        return self::TYPES[$type]['label'] ?? ucfirst(str_replace('_', ' ', $type));
    }

    public function getTypeLabelAttribute(): string
    {
        return self::typeLabel($this->report_type);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    /** Accepted reports are final; anything else the president may still replace. */
    public function isLocked(): bool
    {
        return $this->status === 'accepted';
    }

    /** "CSS - Minutes of Meetings - 2nd Semester 2025-2026.docx" — who and what, not the upload name. */
    public function downloadName(): string
    {
        $extension = strtolower(pathinfo($this->file_name, PATHINFO_EXTENSION) ?: 'pdf');
        $club      = $this->club?->acronym ?: $this->club?->name ?: 'Club';
        $name      = str_replace('&', 'and', "{$club} - {$this->type_label} - {$this->semester?->label}");

        return trim(preg_replace('/[\\\\\/:*?"<>|]+/', '', $name)) . '.' . $extension;
    }
}

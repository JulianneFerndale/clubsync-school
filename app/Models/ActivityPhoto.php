<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A photo an officer attached to an activity for the semester-end reports.
 *
 * The file sits in the reporting folder on the local disk; this row says which
 * activity it belongs to and which report section it goes into. The report documents
 * are compiled from these rows, so a photo is only ever printed where its category
 * puts it.
 */
class ActivityPhoto extends Model
{
    /**
     * What an officer can attach, and where each one is printed.
     *
     * `fields` names the extra figures a category carries; they are what the
     * financial and inventory reports list, so they are required for those categories.
     */
    public const CATEGORIES = [
        'documentation' => [
            'label'    => 'Activity photos',
            'hint'     => 'Photos taken during the activity or meeting.',
            'printed'  => 'Accomplishment Report, Annex A',
            'fields'   => [],
            'multiple' => true,
        ],
        'minutes' => [
            'label'    => 'Minutes of the meeting',
            'hint'     => 'A photo or scan of each page of the written minutes.',
            'printed'  => 'Minutes of Meetings; Accomplishment Report, Annex D',
            'fields'   => [],
            'multiple' => true,
        ],
        'program' => [
            'label'    => 'Program / invitation',
            'hint'     => 'The program cover, program flow or invitation.',
            'printed'  => 'Accomplishment Report, Annex C',
            'fields'   => [],
            'multiple' => true,
        ],
        'letter' => [
            'label'    => 'Communication letter',
            'hint'     => 'Approved or sent letters, with the budget breakdown if any.',
            'printed'  => 'Communication Letters; Accomplishment Report, Annex E; Financial Report',
            'fields'   => [],
            'multiple' => true,
        ],
        'publication' => [
            'label'    => 'Publication material',
            'hint'     => 'Pubmats and information posted on social media (usually the Mass Media Officer).',
            'printed'  => 'Publication Materials',
            'fields'   => [],
            'multiple' => true,
        ],
        'receipt' => [
            'label'    => 'Receipt',
            'hint'     => 'One receipt per upload, with the item and the amount on it.',
            'printed'  => 'Financial Transactions & Liquidation Report',
            'fields'   => ['amount', 'reference_number'],
            'multiple' => false,
        ],
        'property' => [
            'label'    => 'Property',
            'hint'     => 'A club property used for the activity, with its quantity and condition.',
            'printed'  => 'Property Inventory Report',
            'fields'   => ['quantity', 'remarks'],
            'multiple' => false,
        ],
    ];

    protected $fillable = [
        'club_id', 'club_activity_id', 'category', 'caption',
        'amount', 'reference_number', 'quantity', 'remarks',
        'path', 'original_name', 'size', 'width', 'height', 'uploaded_by',
    ];

    protected $casts = [
        'amount'   => 'decimal:2',
        'quantity' => 'integer',
        'size'     => 'integer',
        'width'    => 'integer',
        'height'   => 'integer',
    ];

    public function activity(): BelongsTo
    {
        return $this->belongsTo(ClubActivity::class, 'club_activity_id');
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public static function categoryLabel(string $category): string
    {
        return self::CATEGORIES[$category]['label'] ?? ucfirst($category);
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::categoryLabel($this->category);
    }

    /**
     * The photos a report prints. Once the report has gone to the DSA, a photo added
     * afterwards is not part of what the adviser approved, so it is left out.
     */
    public function scopeForReport(Builder $query, SemesterNarrativeReport $report): Builder
    {
        return $query
            ->whereIn('club_activity_id', $report->activityIds())
            ->when($report->submitted_at, fn (Builder $q) => $q->where('created_at', '<=', $report->submitted_at))
            ->orderBy('created_at')
            ->orderBy('id');
    }
}

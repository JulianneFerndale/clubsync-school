<?php

use App\Models\Semester;
use App\Services\DatabaseStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Guards the destructive semester-archival gate. archivableSemesters() decides
 * which semesters an admin is allowed to export-and-DELETE (attendance + fee
 * payments). It must never expose the live semester's data — including when the
 * is_current flag was never set.
 */

function makeSemester(string $label, string $start, string $end, bool $current = false): Semester
{
    return Semester::create([
        'label'      => $label,
        'start_date' => $start,
        'end_date'   => $end,
        'is_current' => $current,
    ]);
}

it('archives nothing when no semester is flagged current (fail closed)', function () {
    $s1 = makeSemester('AY2023-24 1st Sem', '2023-08-01', '2023-12-15');
    $s2 = makeSemester('AY2024-25 1st Sem', '2024-08-01', '2024-12-15');
    $s3 = makeSemester('AY2025-26 1st Sem', '2025-08-01', '2025-12-15');

    $svc = app(DatabaseStorageService::class);

    // With no current semester identifiable, archiving is disabled entirely.
    expect($svc->archivableSemesters())->toHaveCount(0)
        ->and($svc->isArchivable($s1))->toBeFalse()
        ->and($svc->isArchivable($s2))->toBeFalse()
        ->and($svc->isArchivable($s3))->toBeFalse();
});

it('keeps the current and most-recent-previous semester hot, archiving only older ones', function () {
    $old      = makeSemester('AY2023-24 1st Sem', '2023-08-01', '2023-12-15');
    $previous = makeSemester('AY2024-25 1st Sem', '2024-08-01', '2024-12-15');
    $current  = makeSemester('AY2025-26 1st Sem', '2025-08-01', '2025-12-15', current: true);

    $svc = app(DatabaseStorageService::class);

    expect($svc->archivableSemesters()->pluck('id')->all())->toBe([$old->id])
        ->and($svc->isArchivable($current))->toBeFalse()   // live semester — protected
        ->and($svc->isArchivable($previous))->toBeFalse()  // most-recent prior — kept hot
        ->and($svc->isArchivable($old))->toBeTrue();       // genuinely old — archivable
});

<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ArchiveWorkbook;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\ClubActivity;
use App\Models\Fee;
use App\Models\FeePayment;
use App\Models\Semester;
use App\Services\DatabaseStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StorageController extends Controller
{
    public function __construct(private DatabaseStorageService $storage) {}

    public function index(): View
    {
        $status = $this->storage->status();
        $archivable = $this->storage->archivableSemesters();

        $archives = collect(Storage::disk('local')->files('archives'))
            ->filter(fn ($p) => str_ends_with($p, '.xlsx'))
            ->map(fn ($p) => [
                'name'  => basename($p),
                'size'  => Storage::disk('local')->size($p),
                'mtime' => Storage::disk('local')->lastModified($p),
            ])
            ->sortByDesc('mtime')
            ->values();

        return view('admin.storage.index', compact('status', 'archivable', 'archives'));
    }

    /**
     * Export a past semester's attendance + fee payments to Excel, then prune
     * those (FK-leaf) rows from the database. Export always succeeds before any
     * delete; the delete runs in a transaction so it's all-or-nothing.
     */
    public function archiveSemester(Semester $semester): RedirectResponse
    {
        // Never archive the current or most-recent-previous semester.
        if (! $this->storage->isArchivable($semester)) {
            return back()->withErrors(['semester' => 'This semester is too recent to archive. Only older semesters can be archived.']);
        }

        $activityIds = ClubActivity::whereBetween('date', [$semester->start_date, $semester->end_date])->pluck('id');
        $feeIds = Fee::whereBetween('due_date', [$semester->start_date, $semester->end_date])->pluck('id');

        $attendance = Attendance::whereIn('event_id', $activityIds)
            ->with(['user:id,first_name,last_name,edp_number', 'activity:id,title,date'])
            ->get();

        $payments = FeePayment::whereIn('fee_id', $feeIds)
            ->with(['user:id,first_name,last_name', 'fee:id,title,amount'])
            ->get();

        if ($attendance->isEmpty() && $payments->isEmpty()) {
            return back()->with('info', 'Nothing to archive for ' . $semester->label . ' — no attendance or fee records in range.');
        }

        // 1) Write the archive to disk FIRST (so nothing is deleted unless it is saved).
        $path = 'archives/semester-' . $semester->id . '-' . now()->format('Ymd_His') . '.xlsx';
        Excel::store(new ArchiveWorkbook([
            [
                'title'    => 'Attendance',
                'headings' => ['Activity', 'Date', 'Member', 'EDP No.', 'Time In', 'Time Out'],
                'rows'     => $attendance->map(fn (Attendance $a) => [
                    $a->activity?->title,
                    optional($a->activity?->date)->format('Y-m-d'),
                    trim(($a->user?->first_name ?? '') . ' ' . ($a->user?->last_name ?? '')),
                    $a->user?->edp_number,
                    optional($a->time_in)->format('Y-m-d H:i'),
                    optional($a->time_out)->format('Y-m-d H:i'),
                ])->all(),
            ],
            [
                'title'    => 'Fee Payments',
                'headings' => ['Fee', 'Amount', 'Member', 'Status', 'Confirmed At'],
                'rows'     => $payments->map(fn (FeePayment $p) => [
                    $p->fee?->title,
                    $p->fee?->amount,
                    trim(($p->user?->first_name ?? '') . ' ' . ($p->user?->last_name ?? '')),
                    $p->status,
                    optional($p->confirmed_at)->format('Y-m-d H:i'),
                ])->all(),
            ],
        ]), $path, 'local');

        // 2) Prune the archived rows (both are FK-leaf tables — no cascade risk).
        [$attCount, $payCount] = DB::transaction(function () use ($activityIds, $feeIds) {
            return [
                Attendance::whereIn('event_id', $activityIds)->delete(),
                FeePayment::whereIn('fee_id', $feeIds)->delete(),
            ];
        });

        AuditLog::record('retention.semester_archived', $semester, [
            'attendance_deleted'    => $attCount,
            'fee_payments_deleted'  => $payCount,
            'archive_file'          => $path,
        ]);

        return redirect()->route('admin.storage.index')->with('success',
            "Archived {$semester->label}: exported & removed {$attCount} attendance and {$payCount} fee-payment records. Download the archive below.");
    }

    public function download(Request $request): BinaryFileResponse
    {
        $name = basename((string) $request->query('file'));
        $path = 'archives/' . $name;

        abort_unless($name !== '' && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path);
    }

    /**
     * Prune audit-log rows older than the retention window. POLICY requires keeping
     * at least one academic year, enforced by the config floor of 365 days.
     */
    public function pruneAudit(): RedirectResponse
    {
        $cutoff = now()->subDays((int) config('retention.audit_retention_days', 365));
        $deleted = AuditLog::where('created_at', '<', $cutoff)->delete();

        return redirect()->route('admin.storage.index')
            ->with('success', "Pruned {$deleted} audit-log entries older than " . $cutoff->format('M j, Y') . '.');
    }
}

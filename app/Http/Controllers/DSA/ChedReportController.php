<?php

namespace App\Http\Controllers\DSA;

use App\Http\Controllers\Controller;
use App\Models\ChedReport;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ChedReportController extends Controller
{
    public function index(): View
    {
        $reports = ChedReport::where('is_finalized', true)
            ->with('club', 'activity')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('dsa.reports.index', compact('reports'));
    }

    public function downloadPdf(ChedReport $report)
    {
        if (! $report->is_finalized) {
            abort(404);
        }

        return Storage::disk('local')->download($report->pdf_path);
    }

    public function downloadXlsx(ChedReport $report)
    {
        if (! $report->is_finalized) {
            abort(404);
        }

        return Storage::disk('local')->download($report->xlsx_path);
    }
}

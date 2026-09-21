<?php

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use App\Models\ChedReport;
use App\Models\ClubOfficer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ChedReportController extends Controller
{
    private function officerClub(): ?\App\Models\Club
    {
        return ClubOfficer::where('user_id', auth_user_id())
            ->with('club')
            ->first()
            ?->club;
    }

    private function authorize(ChedReport $report): void
    {
        $club = $this->officerClub();

        if (! $club || $report->club_id !== $club->id) {
            abort(403);
        }
    }

    public function update(Request $request, ChedReport $report): RedirectResponse
    {
        $this->authorize($report);

        if ($report->is_finalized) {
            abort(403, 'This report has already been finalized and can no longer be edited.');
        }

        $validated = $request->validate([
            'narrative' => ['nullable', 'string'],
        ]);

        $report->update($validated);

        return back()->with('success', 'Report narrative saved.');
    }

    public function finalize(ChedReport $report): RedirectResponse
    {
        $this->authorize($report);

        $report->update(['is_finalized' => true]);

        return back()->with('success', 'Report finalized and sent to the DSA.');
    }

    public function downloadPdf(ChedReport $report)
    {
        $this->authorize($report);

        return Storage::disk('local')->download($report->pdf_path);
    }

    public function downloadXlsx(ChedReport $report)
    {
        $this->authorize($report);

        return Storage::disk('local')->download($report->xlsx_path);
    }
}

<?php

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use App\Models\ActivityPhoto;
use App\Models\AuditLog;
use App\Models\ClubActivity;
use App\Models\ClubOfficer;
use App\Services\ActivityPhotoStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * What officers attach to an activity for the semester-end reports: photos (the
 * activity itself, the minutes, the program, letters, publication materials),
 * receipts with their amounts, properties with their quantities, and the budget the
 * receipts are liquidated against.
 *
 * Any officer of the club may attach; the uploader is recorded on each photo. Once
 * the semester's narrative report covering the activity has been submitted to the
 * DSA, what it printed stays as it was: nothing can be removed and the budget is fixed.
 */
class ActivityReportController extends Controller
{
    public function __construct(private ActivityPhotoStorage $storage) {}

    public function storePhoto(Request $request, ClubActivity $event): RedirectResponse
    {
        $this->authorizeActivity($event);

        if ($locked = $this->lockedError($event)) {
            return back()->withErrors(['photos' => $locked]);
        }

        $category = (string) $request->input('category');
        $fields   = ActivityPhoto::CATEGORIES[$category]['fields'] ?? [];
        $multiple = ActivityPhoto::CATEGORIES[$category]['multiple'] ?? true;

        $validated = $request->validate([
            'category'         => ['required', Rule::in(array_keys(ActivityPhoto::CATEGORIES))],
            'photos'           => ['required', 'array', 'min:1', 'max:' . ($multiple ? 10 : 1)],
            'photos.*'         => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'caption'          => [$fields ? 'required' : 'nullable', 'string', 'max:255'],
            'amount'           => [Rule::requiredIf(in_array('amount', $fields, true)), 'nullable', 'numeric', 'min:0.01', 'max:9999999.99'],
            'reference_number' => ['nullable', 'string', 'max:60'],
            'quantity'         => [Rule::requiredIf(in_array('quantity', $fields, true)), 'nullable', 'integer', 'min:1', 'max:100000'],
            'remarks'          => ['nullable', 'string', 'max:255'],
        ], [
            'photos.required'   => 'Choose at least one photo.',
            'photos.max'        => $multiple ? 'Attach at most 10 photos at a time.' : 'Attach one photo at a time for this category.',
            'photos.*.mimes'    => 'Photos must be JPG, PNG or WebP images.',
            'photos.*.max'      => 'Each photo must be 8 MB or smaller.',
            'caption.required'  => $category === 'receipt' ? 'Enter the item or description on the receipt.' : 'Enter the name of the property.',
            'amount.required'   => 'Enter the amount on the receipt.',
            'quantity.required' => 'Enter how many of this property the club has.',
        ]);

        // Figures belong only to the category that prints them.
        $details = ['category' => $category, 'caption' => $validated['caption'] ?? null];
        foreach (['amount', 'reference_number', 'quantity', 'remarks'] as $field) {
            $details[$field] = in_array($field, $fields, true) ? ($validated[$field] ?? null) : null;
        }

        $stored = [];

        try {
            foreach ($request->file('photos') as $file) {
                $stored[] = $this->storage->store($file, $event, $details, auth_user_id());
            }
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->withErrors(['photos' => 'A photo could not be read. Upload it again as a JPG or PNG image.']);
        }

        foreach ($stored as $photo) {
            AuditLog::record('activity.photo.attached', $photo, [
                'club_id'     => $event->club_id,
                'activity_id' => $event->id,
                'category'    => $category,
            ]);
        }

        $count = count($stored);

        return redirect()->to(route('officer.activities.show', $event) . '#report-photos')
            ->with('success', $count . ' ' . str(ActivityPhoto::categoryLabel($category))->lower() . ($count === 1 ? '' : ' files') . ' attached for the semester report.');
    }

    /** The image itself, for the thumbnails on the activity page. */
    public function showPhoto(ClubActivity $event, ActivityPhoto $photo): Response
    {
        $this->authorizeActivity($event);
        abort_unless($photo->club_activity_id === $event->id, 404);

        $bytes = ActivityPhotoStorage::contents($photo);
        abort_if($bytes === null, 404);

        return response($bytes, 200, [
            'Content-Type'  => 'image/jpeg',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    public function destroyPhoto(ClubActivity $event, ActivityPhoto $photo): RedirectResponse
    {
        $this->authorizeActivity($event);
        abort_unless($photo->club_activity_id === $event->id, 404);

        if ($locked = $this->lockedError($event)) {
            return back()->withErrors(['photos' => $locked]);
        }

        $this->storage->delete($photo);

        AuditLog::record('activity.photo.removed', $photo, [
            'club_id'     => $event->club_id,
            'activity_id' => $event->id,
            'category'    => $photo->category,
        ]);

        return redirect()->to(route('officer.activities.show', $event) . '#report-photos')
            ->with('success', 'Removed from the semester report.');
    }

    /** The budget released for the activity, which its receipts are liquidated against. */
    public function updateBudget(Request $request, ClubActivity $event): RedirectResponse
    {
        $this->authorizeActivity($event);

        if ($locked = $this->lockedError($event)) {
            return back()->withErrors(['budget_amount' => $locked]);
        }

        $validated = $request->validate([
            'budget_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
        ]);

        $before = $event->budget_amount;
        $event->update(['budget_amount' => $validated['budget_amount'] ?? null]);

        AuditLog::record('activity.budget.updated', $event, [
            'club_id' => $event->club_id,
            'before'  => $before,
            'after'   => $event->budget_amount,
        ]);

        return redirect()->to(route('officer.activities.show', $event) . '#report-photos')
            ->with('success', $event->budget_amount === null ? 'Budget cleared.' : 'Budget saved.');
    }

    private function authorizeActivity(ClubActivity $event): void
    {
        $isOfficer = ClubOfficer::where('user_id', auth_user_id())
            ->where('club_id', $event->club_id)
            ->exists();

        abort_unless($isOfficer, 403);
    }

    private function lockedError(ClubActivity $event): ?string
    {
        $report = $event->submittedReport();

        return $report
            ? "This activity is in the {$report->semester?->label} narrative report the adviser has already submitted to the DSA, so its report records can no longer be changed."
            : null;
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ChedMemorandum;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The administrator's register of CHED memorandums. Every user reads and downloads
 * them from the shared library (ResourceLibraryController); only an admin adds,
 * corrects or removes one, and each change is written to the audit log.
 */
class ChedMemorandumController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $status = $request->query('status');

        $memos = ChedMemorandum::query()
            ->when(array_key_exists((string) $status, ChedMemorandum::STATUSES), fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->whereRaw('LOWER(title) LIKE ?', ['%' . strtolower($search) . '%'])
                ->orWhereRaw('LOWER(memo_number) LIKE ?', ['%' . strtolower($search) . '%'])
            ))
            ->latestFirst()
            ->paginate(20)
            ->withQueryString();

        return view('admin.ched-memos.index', [
            'memos'    => $memos,
            'search'   => $search,
            'status'   => $status,
            'statuses' => ChedMemorandum::STATUSES,
        ]);
    }

    public function create(): View
    {
        return view('admin.ched-memos.form', ['memo' => new ChedMemorandum(['status' => 'in_effect', 'type' => 'order'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $file = $request->file('file');

        $memo = ChedMemorandum::create($data + [
            'file_path'   => $file->store('ched-memos', 'local'),
            'file_name'   => $file->getClientOriginalName(),
            'file_size'   => $file->getSize(),
            'uploaded_by' => auth_user_id() ?: null,
        ]);

        AuditLog::record('admin.ched_memo.created', $memo, ['citation' => $memo->citation]);

        return redirect()->route('admin.ched-memos.index')
            ->with('success', "{$memo->citation} was added to the library.");
    }

    public function edit(ChedMemorandum $memo): View
    {
        return view('admin.ched-memos.form', compact('memo'));
    }

    public function update(Request $request, ChedMemorandum $memo): RedirectResponse
    {
        $memo->fill($this->validated($request, $memo));
        $oldPath = null;

        if ($file = $request->file('file')) {
            $oldPath = $memo->file_path;
            $memo->fill([
                'file_path' => $file->store('ched-memos', 'local'),
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
            ]);
        }

        $changed = array_keys($memo->getDirty());
        $memo->save();

        // The old PDF goes only once the new one is saved against the record.
        if ($oldPath) {
            Storage::disk('local')->delete($oldPath);
        }

        if ($changed) {
            AuditLog::record('admin.ched_memo.updated', $memo, [
                'citation' => $memo->citation,
                'fields'   => array_values(array_diff($changed, ['file_path', 'file_size'])),
            ]);
        }

        return redirect()->route('admin.ched-memos.index')
            ->with('success', "{$memo->citation} was updated.");
    }

    public function destroy(ChedMemorandum $memo): RedirectResponse
    {
        $citation = $memo->citation;

        AuditLog::record('admin.ched_memo.deleted', $memo, ['citation' => $citation, 'title' => $memo->title]);

        $memo->delete();
        Storage::disk('local')->delete($memo->file_path);

        return redirect()->route('admin.ched-memos.index')
            ->with('success', "{$citation} was removed from the library.");
    }

    private function validated(Request $request, ?ChedMemorandum $memo = null): array
    {
        $data = $request->validate([
            'type'           => ['required', Rule::in(array_keys(ChedMemorandum::TYPES))],
            'memo_number'    => [
                'required', 'string', 'max:20', 'regex:/^[0-9A-Za-z-]+$/',
                Rule::unique('ched_memorandums', 'memo_number')
                    ->where('type', $request->input('type'))
                    ->where('series_year', $request->input('series_year'))
                    ->ignore($memo?->id),
            ],
            // CHED was created in 1994; nothing it issued can be older.
            'series_year'    => ['required', 'integer', 'between:1994,' . (now()->year + 1)],
            'title'          => ['required', 'string', 'max:255'],
            'summary'        => ['nullable', 'string', 'max:5000'],
            'date_issued'    => ['required', 'date', 'before_or_equal:today'],
            'effective_date' => ['nullable', 'date', 'after_or_equal:date_issued'],
            'status'         => ['required', Rule::in(array_keys(ChedMemorandum::STATUSES))],
            'source_url'     => ['nullable', 'url:http,https', 'max:255'],
            'file'           => [$memo ? 'nullable' : 'required', 'file', 'mimes:pdf', 'max:20480'],
        ], [
            'memo_number.unique' => 'This memorandum is already in the library.',
            'memo_number.regex'  => 'Enter just the number, e.g. 09 or 2-A.',
            'file.required'      => 'Attach the memorandum as a PDF.',
            'file.mimes'         => 'The memorandum must be a PDF.',
            'file.max'           => 'The PDF may not be larger than 20 MB.',
        ]);

        unset($data['file']);

        return $data;
    }
}

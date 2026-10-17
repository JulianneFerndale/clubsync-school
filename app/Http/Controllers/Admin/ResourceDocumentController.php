<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ResourceDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The SCC club handbook and the report templates, published by the administrator
 * for every user to download from the shared library.
 */
class ResourceDocumentController extends Controller
{
    /** Office documents and PDFs only — the forms clubs are expected to fill in. */
    private const MIMES = 'pdf,doc,docx,xls,xlsx,ppt,pptx';

    public function index(): View
    {
        $documents = ResourceDocument::orderBy('category')->orderBy('title')->get()->groupBy('category');

        return view('admin.resources.index', [
            'documents'  => $documents,
            'categories' => ResourceDocument::CATEGORIES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $file = $request->file('file');

        $document = ResourceDocument::create($data + [
            'file_path'   => $file->store('resources', 'local'),
            'file_name'   => $file->getClientOriginalName(),
            'file_size'   => $file->getSize(),
            'uploaded_by' => auth_user_id() ?: null,
        ]);

        AuditLog::record('admin.resource.created', $document, [
            'category' => $document->category,
            'title'    => $document->title,
        ]);

        return redirect()->route('admin.resources.index')
            ->with('success', "\"{$document->title}\" is now available to download.");
    }

    public function edit(ResourceDocument $document): View
    {
        return view('admin.resources.edit', [
            'document'   => $document,
            'categories' => ResourceDocument::CATEGORIES,
        ]);
    }

    public function update(Request $request, ResourceDocument $document): RedirectResponse
    {
        $document->fill($this->validated($request, $document));
        $oldPath = null;

        if ($file = $request->file('file')) {
            $oldPath = $document->file_path;
            $document->fill([
                'file_path' => $file->store('resources', 'local'),
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
            ]);
        }

        $changed = array_keys($document->getDirty());
        $document->save();

        if ($oldPath) {
            Storage::disk('local')->delete($oldPath);
        }

        if ($changed) {
            AuditLog::record('admin.resource.updated', $document, [
                'title'  => $document->title,
                'fields' => array_values(array_diff($changed, ['file_path', 'file_size'])),
            ]);
        }

        return redirect()->route('admin.resources.index')
            ->with('success', "\"{$document->title}\" was updated.");
    }

    public function destroy(ResourceDocument $document): RedirectResponse
    {
        AuditLog::record('admin.resource.deleted', $document, [
            'category' => $document->category,
            'title'    => $document->title,
        ]);

        $document->delete();
        Storage::disk('local')->delete($document->file_path);

        return redirect()->route('admin.resources.index')
            ->with('success', "\"{$document->title}\" was removed.");
    }

    private function validated(Request $request, ?ResourceDocument $document = null): array
    {
        $data = $request->validate([
            'category'    => ['required', Rule::in(array_keys(ResourceDocument::CATEGORIES))],
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'file'        => [$document ? 'nullable' : 'required', 'file', 'mimes:' . self::MIMES, 'max:20480'],
        ], [
            'file.required' => 'Choose the file to upload.',
            'file.mimes'    => 'Upload a PDF, Word, Excel or PowerPoint file.',
            'file.max'      => 'The file may not be larger than 20 MB.',
        ]);

        unset($data['file']);

        return $data;
    }
}

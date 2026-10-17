<?php

namespace App\Http\Controllers;

use App\Models\ChedMemorandum;
use App\Models\ResourceDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * The read-only library for the people who run clubs — advisers, officers and the
 * DSA: the SCC club handbook, the report templates, and the CHED memorandums the
 * administrator keeps. Student members don't see it (routes/web.php); managing it
 * lives under admin.*.
 */
class ResourceLibraryController extends Controller
{
    public const TABS = ['handbook', 'templates', 'memos'];

    public function index(Request $request): View
    {
        $tab    = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'handbook';
        $search = trim((string) $request->query('search'));

        $memos = ChedMemorandum::query()
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->whereRaw('LOWER(title) LIKE ?', ['%' . strtolower($search) . '%'])
                ->orWhereRaw('LOWER(memo_number) LIKE ?', ['%' . strtolower($search) . '%'])
            ))
            // What is in effect is what people come looking for; history follows.
            ->orderByRaw("CASE WHEN status = 'in_effect' THEN 0 ELSE 1 END")
            ->latestFirst()
            ->get();

        return view('resources.index', [
            'layout'    => role_layout(auth()->user()?->role),
            'tab'       => $tab,
            'search'    => $search,
            'handbooks' => ResourceDocument::handbooks()->latest()->get(),
            'templates' => ResourceDocument::templates()->orderBy('title')->get(),
            'memos'     => $memos,
        ]);
    }

    public function downloadMemo(ChedMemorandum $memo): Response
    {
        abort_unless(Storage::disk('local')->exists($memo->file_path), 404);

        return Storage::disk('local')->download($memo->file_path, $memo->downloadName());
    }

    public function downloadDocument(ResourceDocument $document): Response
    {
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->download($document->file_path, $document->file_name);
    }
}

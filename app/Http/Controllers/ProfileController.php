<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\SignatureStorage;
use App\Support\PublicUpload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(): View
    {
        $user   = auth()->user();
        $layout = role_layout($user->role ?? 'member');

        return view('profile.show', compact('user', 'layout'));
    }

    public function updatePhoto(Request $request): RedirectResponse
    {
        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $user = auth()->user();

        if (! $user) {
            return redirect()->route('login');
        }

        PublicUpload::delete($user->profile_photo_url);

        $path = $request->file('photo')->store('profile-photos', 'public');

        $user->update(['profile_photo_url' => PublicUpload::url($path)]);

        return back()->with('success', 'Profile photo updated.');
    }

    public function deletePhoto(): RedirectResponse
    {
        $user = auth()->user();

        if (! $user) {
            return redirect()->route('login');
        }

        PublicUpload::delete($user->profile_photo_url);

        $user->update(['profile_photo_url' => null]);

        return back()->with('success', 'Profile photo removed.');
    }

    /** The signed-in user's own signature, for the preview on their profile. Nobody else's. */
    public function showSignature()
    {
        $user = auth()->user();

        if (! $user?->signature_path || ! Storage::disk('local')->exists($user->signature_path)) {
            abort(404);
        }

        return response(Storage::disk('local')->get($user->signature_path), 200, [
            'Content-Type'  => 'image/png',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * Save the signature room booking letters are signed with (POLICY.md §Event
     * Creation): a photo of one written on paper, or one drawn on the page.
     */
    public function updateSignature(Request $request, SignatureStorage $signatures): RedirectResponse
    {
        $user = auth()->user();

        if (! $user?->signsLetters()) {
            abort(403);
        }

        $request->validate([
            'signature_file'  => ['nullable', 'required_without:signature_drawn', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'signature_drawn' => ['nullable', 'required_without:signature_file', 'string', 'max:1500000'],
        ], [
            'signature_file.required_without'  => 'Upload a photo of your signature, or draw it.',
            'signature_drawn.required_without' => 'Upload a photo of your signature, or draw it.',
        ]);

        $signatures->store($user, $request->file('signature_file') ?? (string) $request->input('signature_drawn'));

        AuditLog::record('profile.signature.updated', $user);

        return back()->with('success', 'Signature saved. Letters you sign from now on carry it.');
    }

    public function deleteSignature(SignatureStorage $signatures): RedirectResponse
    {
        $user = auth()->user();

        if (! $user?->signature_path) {
            return back();
        }

        $signatures->remove($user);

        AuditLog::record('profile.signature.removed', $user);

        return back()->with('success', 'Signature removed. Letters you already signed keep it.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Support\PublicUpload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
}

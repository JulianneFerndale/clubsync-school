<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

        // Delete the old photo if it was locally stored
        if ($user->profile_photo_url && Str::startsWith($user->profile_photo_url, '/storage/')) {
            Storage::disk('public')->delete(Str::after($user->profile_photo_url, '/storage/'));
        }

        $path = $request->file('photo')->store('profile-photos', 'public');

        $user->update(['profile_photo_url' => Storage::url($path)]);

        return back()->with('success', 'Profile photo updated.');
    }

    public function deletePhoto(): RedirectResponse
    {
        $user = auth()->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->profile_photo_url && Str::startsWith($user->profile_photo_url, '/storage/')) {
            Storage::disk('public')->delete(Str::after($user->profile_photo_url, '/storage/'));
        }

        $user->update(['profile_photo_url' => null]);

        return back()->with('success', 'Profile photo removed.');
    }
}

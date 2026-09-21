<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AnnouncementComment;
use App\Models\AnnouncementLike;
use App\Models\ClubNarrative;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BulletinController extends Controller
{
    public function index(): View
    {
        $announcements = Announcement::published()
            ->with(['club', 'author', 'likes'])
            ->withCount('comments')
            ->paginate(15);

        $likedIds = AnnouncementLike::where('user_id', auth_user_id())
            ->pluck('announcement_id')
            ->flip();

        // Adviser-approved AI narratives — a read-only recap of what clubs have been up to.
        $narratives = ClubNarrative::published()
            ->with('club')
            ->take(10)
            ->get();

        return view('member.bulletin.index', compact('announcements', 'likedIds', 'narratives'));
    }

    public function show(Announcement $announcement): View
    {
        if ($announcement->status !== 'published') {
            abort(404);
        }

        $announcement->load(['club', 'author', 'likes', 'comments.user']);

        $liked = AnnouncementLike::where('announcement_id', $announcement->id)
            ->where('user_id', auth_user_id())
            ->exists();

        return view('member.bulletin.show', compact('announcement', 'liked'));
    }

    public function like(Announcement $announcement): RedirectResponse
    {
        if ($announcement->status !== 'published') {
            abort(404);
        }

        $existing = AnnouncementLike::where('announcement_id', $announcement->id)
            ->where('user_id', auth_user_id())
            ->first();

        if ($existing) {
            $existing->delete();
        } else {
            AnnouncementLike::create([
                'announcement_id' => $announcement->id,
                'user_id'         => auth_user_id(),
            ]);
        }

        return back();
    }

    public function comment(Request $request, Announcement $announcement): RedirectResponse
    {
        if ($announcement->status !== 'published') {
            abort(404);
        }

        $request->validate([
            'content' => ['required', 'string', 'min:1', 'max:500'],
        ]);

        AnnouncementComment::create([
            'announcement_id' => $announcement->id,
            'user_id'         => auth_user_id(),
            'content'         => $request->input('content'),
        ]);

        return back()->with('commented', true);
    }
}

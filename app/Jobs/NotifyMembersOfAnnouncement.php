<?php

namespace App\Jobs;

use App\Mail\AnnouncementPublished;
use App\Models\Announcement;
use App\Models\ClubMember;
use App\Models\ClubNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * When an announcement is published, notify every active club member both in-app
 * and (per POLICY) with a Gmail copy via the configured institutional mailer.
 */
class NotifyMembersOfAnnouncement implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private int $announcementId) {}

    public function handle(): void
    {
        $announcement = Announcement::with('club')->find($this->announcementId);

        if (! $announcement || $announcement->status !== 'published' || ! $announcement->club) {
            return;
        }

        $club     = $announcement->club;
        $clubName = $club->name;
        $preview  = Str::limit(strip_tags((string) $announcement->content), 140);
        $url      = route('member.bulletin.show', $announcement);

        // Email the Gmail copy only when a real mailer is configured (not the log driver).
        $emailEnabled = config('mail.default') !== 'log';

        ClubMember::where('club_id', $club->id)
            ->where('status', 'active')
            ->with('user')
            ->chunkById(200, function ($members) use ($announcement, $club, $clubName, $preview, $url, $emailEnabled) {
                foreach ($members as $member) {
                    $user = $member->user;
                    if (! $user) {
                        continue;
                    }

                    // In-system notification
                    ClubNotification::create([
                        'recipient_id' => $user->id,
                        'sender_type'  => 'system',
                        'club_id'      => $club->id,
                        'title'        => $clubName . ' posted an announcement',
                        'body'         => ($announcement->title ?? 'New announcement') . ($preview ? ' — ' . $preview : ''),
                        'action_url'   => $url,
                    ]);

                    // Gmail copy
                    if ($emailEnabled && $user->email) {
                        try {
                            Mail::to($user->email)->send(new AnnouncementPublished($announcement, $clubName, $url));
                        } catch (\Throwable $e) {
                            Log::warning('Announcement email to ' . $user->email . ' failed: ' . $e->getMessage());
                        }
                    }
                }
            });
    }
}

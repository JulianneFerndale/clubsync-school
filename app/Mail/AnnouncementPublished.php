<?php

namespace App\Mail;

use App\Models\Announcement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Gmail copy of a published club announcement (in addition to the in-app bulletin).
 * Delivery uses the configured mailer — set MAIL_MAILER=smtp with the institutional
 * Google Workspace SMTP credentials to route through Gmail.
 */
class AnnouncementPublished extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Announcement $announcement,
        public string $clubName,
        public ?string $url = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[' . $this->clubName . '] ' . ($this->announcement->title ?? 'New announcement'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.announcement-published',
        );
    }
}

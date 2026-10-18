<?php

namespace App\Services;

use App\Models\Club;
use App\Models\ClubActivity;
use App\Models\ClubOfficer;
use App\Models\User;
use App\Support\PdfImage;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * The room booking letter an activity needing approval is sent with (POLICY.md
 * §Event Creation), laid out as docs/BOOKING OF ROOMS.docx: the SCC crest and the
 * club's logo over "Saint Columban College / {club} / Pagadian City", the date, the
 * letter to the Vice President for Administration, and three signatures — the officer
 * who sends it, "Noted by" the club adviser and "Approved by" the VPA.
 *
 * Only the body is written per activity, by the AI or from the template below; the
 * officer reads and edits it before sending. Everything around it is fixed.
 *
 * Each signature is recorded at the moment its owner signs, with the signature image
 * they had on file then (letter_signatures), so a letter reads the same however often
 * it is downloaded and whatever anyone uploads later. The letter is composed from the
 * activity on demand: before the VPA or adviser signs, their place is blank.
 */
class BookingLetter
{
    public const GREETING = 'Panagdait sa Dios, sa tanan ug sa tanang kabuhatan!';

    public const CLOSING = 'We look forward to your approval. Thank you.';

    public const POSITION_LABELS = [
        'president' => 'President',
        'treasurer' => 'Treasurer',
        'mmo'       => 'MMO',
    ];

    public function __construct(private SignatureStorage $signatures) {}

    /**
     * The body written from the activity's own details, with no AI: the manual path
     * POLICY.md §AI & Automation requires, and what a letter starts from when the
     * officer sends it without drafting one.
     *
     * @param  array{title: string, date: string, time_start: string, time_end: string, venue: string, purpose: string, expected_participants: int|string}  $fields
     */
    public static function templateBody(string $clubName, array $fields): string
    {
        $date  = Carbon::parse($fields['date'])->format('l, F j, Y');
        $start = Carbon::parse($fields['time_start'])->format('g:i A');
        $end   = Carbon::parse($fields['time_end'])->format('g:i A');

        $purpose = trim((string) $fields['purpose']);
        $purpose = $purpose === '' ? '' : ' Its purpose is as follows: ' . rtrim($purpose, '.') . '.';

        $participants = (int) $fields['expected_participants'];
        $turnout = $participants > 0
            ? ' We expect about ' . $participants . ' ' . Str::plural('participant', $participants) . '.'
            : '';

        return "The {$clubName} will be holding its {$fields['title']} on {$date}, from {$start} to {$end} at {$fields['venue']}.{$purpose}{$turnout}"
            . "\n\n"
            . "In this regard, we respectfully request the use of {$fields['venue']} for this activity. We will ensure that the room is kept clean and orderly after the event.";
    }

    /** "DES Club President": how an officer signs for their club. */
    public static function officerPosition(Club $club, User $officer): string
    {
        $position = ClubOfficer::where('club_id', $club->id)->where('user_id', $officer->id)->value('position');

        return self::clubPrefix($club) . ' Club ' . (self::POSITION_LABELS[$position] ?? 'Officer');
    }

    public static function adviserPosition(Club $club): string
    {
        return self::clubPrefix($club) . ' Club Adviser';
    }

    /**
     * A signature as it stands now, to record on the letter.
     *
     * The VPA signs in the office's name (config/vpa.php), since the account may be run
     * by the SSG; who actually pressed Approve is kept beside it as `signed_by`.
     *
     * @return array{user_id: int, name: string, signed_by: string, position: string, signed_at: string, signature_path: string|null}
     */
    public function sign(User $user, string $position, ?string $printedName = null): array
    {
        return [
            'user_id'        => $user->id,
            'name'           => $printedName ?? $user->name,
            'signed_by'      => $user->name,
            'position'       => $position,
            'signed_at'      => now()->toIso8601String(),
            'signature_path' => $user->signature_path,
        ];
    }

    /** Everything the letter shows, for the page preview and the PDF alike. */
    public function compose(ClubActivity $activity): array
    {
        $activity->loadMissing('club.adviserUser', 'creator');
        $club = $activity->club;

        $officer = $activity->letterSignature('officer');
        $vpa     = $activity->letterSignature('vpa');
        $adviser = $activity->letterSignature('adviser');

        $paragraphs = preg_split("/\R\s*\R/", trim((string) $activity->letter_body)) ?: [];
        $paragraphs = array_values(array_filter(array_map(
            fn (string $p) => trim(preg_replace('/\s*\R\s*/', ' ', $p)),
            $paragraphs,
        )));

        return [
            'reference'  => 'ClubSync room booking #' . $activity->id,
            'club'       => $club?->name ?? 'Club',
            'date'       => $officer ? Carbon::parse($officer['signed_at']) : ($activity->created_at ?? now()),
            'addressee'  => config('vpa.name'),
            'title'      => config('vpa.title'),
            'salutation' => 'Dear ' . config('vpa.salutation') . ',',
            'greeting'   => self::GREETING,
            'paragraphs' => $paragraphs,
            'closing'    => self::CLOSING,
            'crest'      => PdfImage::dataUri('/images/scc-crest.png', 300),
            'club_logo'  => PdfImage::dataUri($club?->profile_photo_url, 300),
            'signers'    => [
                'officer' => $this->signer(null, $officer, $activity->creator?->name ?? 'Club Officer', $club ? self::clubPrefix($club) . ' Club Officer' : 'Club Officer'),
                'adviser' => $this->signer('Noted by:', $adviser, $club?->adviserUser?->name ?? 'Club Adviser', $club ? self::adviserPosition($club) : 'Club Adviser'),
                'vpa'     => $this->signer('Approved by:', $vpa, config('vpa.name'), config('vpa.title')),
            ],
            'status'     => $this->status($activity),
            'returned'   => $activity->approval_status === 'rejected' ? [
                'by'      => $activity->wasRejectedByVpa() ? 'the Vice President for Administration' : 'the club adviser',
                'remarks' => $activity->review_remarks,
            ] : null,
        ];
    }

    /** The letter as a PDF on letter-size paper. */
    public function pdf(ClubActivity $activity): \Barryvdh\DomPDF\PDF
    {
        return Pdf::loadView('reports.booking-letter-pdf', ['letter' => $this->compose($activity)])
            ->setPaper('letter');
    }

    public function filename(ClubActivity $activity): string
    {
        $club = $activity->club?->acronym ?: 'Club';

        return Str::slug("room-booking-letter {$club} {$activity->title}") . '.pdf';
    }

    private function signer(?string $caption, ?array $signed, string $name, string $position): array
    {
        return [
            'caption'   => $caption,
            'name'      => $signed['name'] ?? $name,
            'position'  => $signed['position'] ?? $position,
            'signed'    => $signed !== null,
            'signed_at' => $signed ? Carbon::parse($signed['signed_at']) : null,
            'image'     => $signed ? $this->signatures->dataUri($signed['signature_path'] ?? null) : null,
        ];
    }

    private function status(ClubActivity $activity): string
    {
        return match ($activity->approval_status) {
            'pending_vpa'      => 'Waiting for the approval of the Vice President for Administration.',
            'pending_approval' => 'Approved by the Vice President for Administration; waiting for the club adviser.',
            'approved'         => 'Approved. The room is booked for this activity.',
            'rejected'         => 'Returned — not approved.',
            default            => '',
        };
    }

    private static function clubPrefix(Club $club): string
    {
        return $club->acronym ?: $club->name;
    }
}

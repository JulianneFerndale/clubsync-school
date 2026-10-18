<?php

use App\Models\AuditLog;
use App\Models\Club;
use App\Models\ClubActivity;
use App\Models\ClubMember;
use App\Models\ClubNotification;
use App\Models\ClubOfficer;
use App\Models\User;
use App\Services\BookingLetter;
use App\Services\SessionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
 * POLICY.md §Event Creation — room booking letters.
 *
 * An activity that needs approval is sent with a room booking letter (docs/BOOKING OF
 * ROOMS.docx), signed by the officer who sends it. It goes to the Vice President for
 * Administration first — an account run by the VPA or the SSG — then to the club
 * adviser, and is confirmed once both approve, each signing the letter.
 */

// ── Fixtures ─────────────────────────────────────────────────────────────────

function vlUser(string $role): User
{
    static $n = 0;
    $n++;

    return User::create([
        'first_name'   => 'Letter',
        'last_name'    => "Tester{$n}",
        'edp_number'   => str_pad((string) (5000 + $n), 10, '0', STR_PAD_LEFT),
        'email'        => "vl{$n}@sccpag.edu.ph",
        'password'     => Hash::make('Password1!'),
        'role'         => $role,
        'course_id'    => testCourse()->id,
        'is_admin'     => false,
        'is_suspended' => false,
    ]);
}

/** A club with a president, an adviser and an active member, plus the VPA. */
function vlClub(): array
{
    static $n = 0;
    $n++;

    $club = Club::create([
        'slug'            => 'letter-club-' . $n,
        'acronym'         => 'DES' . $n,
        'club_type'       => 'Non-Academic',
        'type'            => 'non_academic',
        'course_slug'     => 'all',
        'department_slug' => 'all',
        'name'            => 'Dreamhunters Esports Society ' . $n,
        'is_active'       => true,
    ]);

    $officer = vlUser('president');
    ClubOfficer::create(['club_id' => $club->id, 'user_id' => $officer->id, 'position' => 'president']);

    $adviser = vlUser('adviser');
    $club->update(['adviser_id' => $adviser->id]);

    $member = vlUser('member');
    ClubMember::create(['club_id' => $club->id, 'user_id' => $member->id, 'status' => 'active', 'role' => 'Member']);

    $vpa = User::where('role', 'vpa')->first() ?? vlUser('vpa');

    return [$officer, $club->fresh(), $adviser, $member, $vpa];
}

function vlSession(User $user): array
{
    $header  = rtrim(strtr(base64_encode(json_encode(['alg' => 'none'])), '+/', '-_'), '=');
    $payload = rtrim(strtr(base64_encode(json_encode(['exp' => time() + 3600])), '+/', '-_'), '=');

    return [
        'firebase_id_token'  => "{$header}.{$payload}.signature",
        'firebase_uid'       => 'uid-' . $user->id,
        'firebase_user_role' => $user->role,
        'firebase_user_id'   => $user->id,
        'firebase_is_admin'  => $user->is_admin,
    ];
}

function vlPayload(array $overrides = []): array
{
    return array_merge([
        'title'                 => 'General Assembly',
        'description'           => 'Semestral general assembly for all members.',
        'date'                  => now()->addDays(12)->toDateString(),
        'time_start'            => '13:00',
        'time_end'              => '17:00',
        'venue'                 => 'PC 2-1',
        'purpose'               => 'Present the plans of the organization and strengthen unity among members.',
        'expected_participants' => 40,
        'activity_type'         => 'campus_resource_use',
        'confirmed'             => 1,
    ], $overrides);
}

/** Send an activity with its letter as $officer, and return it. */
function vlSend(User $officer, array $overrides = []): ClubActivity
{
    test()->withSession(vlSession($officer))
        ->post(route('officer.activities.store'), vlPayload($overrides));

    test()->flushSession();

    return ClubActivity::where('title', $overrides['title'] ?? 'General Assembly')->latest('id')->firstOrFail();
}

function vlApproveAsVpa(User $vpa, ClubActivity $activity): ClubActivity
{
    test()->withSession(vlSession($vpa))->post(route('vpa.letters.approve', $activity));
    test()->flushSession();

    return $activity->fresh();
}

/** A drawn signature: a dark scribble on a transparent canvas, as the profile pad sends it. */
function vlDrawnSignature(): string
{
    $image = imagecreatetruecolor(300, 100);
    imagesavealpha($image, true);
    imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
    $ink = imagecolorallocate($image, 17, 24, 39);
    imagesetthickness($image, 3);
    imageline($image, 20, 70, 120, 20, $ink);
    imageline($image, 120, 20, 200, 80, $ink);
    imageline($image, 200, 80, 280, 30, $ink);

    ob_start();
    imagepng($image);

    return 'data:image/png;base64,' . base64_encode((string) ob_get_clean());
}

beforeEach(function () {
    Storage::fake('local');
    config(['gemini.api_key' => '']);
});

// ── 1. The officer sends the activity with a signed letter ──────────────────

it('sends an approval-requiring activity to the VPA first, with a signed letter', function () {
    [$officer, $club, $adviser, $member, $vpa] = vlClub();

    $activity = vlSend($officer);

    expect($activity->approval_status)->toBe('pending_vpa')
        ->and($activity->isConfirmed())->toBeFalse()
        ->and($activity->letter_body)->toContain('PC 2-1')
        ->and($activity->letter_ai_drafted)->toBeFalse()
        ->and($activity->letterSignature('officer')['user_id'])->toBe($officer->id)
        ->and($activity->letterSignature('officer')['position'])->toBe($club->acronym . ' Club President')
        ->and($activity->letterSignature('vpa'))->toBeNull();

    // The VPA is told; the adviser and the members are not, yet.
    expect(ClubNotification::where('recipient_id', $vpa->id)->where('title', 'Room booking letter to approve')->exists())->toBeTrue()
        ->and(ClubNotification::where('recipient_id', $adviser->id)->exists())->toBeFalse()
        ->and(ClubNotification::where('recipient_id', $member->id)->exists())->toBeFalse();

    expect(AuditLog::where('action', 'activity.letter.sent')->exists())->toBeTrue();
});

it('keeps the letter the officer reviewed on the form', function () {
    [$officer] = vlClub();

    $activity = vlSend($officer, [
        'letter_body'       => "The club will hold its General Assembly at PC 2-1.\n\nWe will keep the room clean.",
        'letter_ai_drafted' => 1,
    ]);

    expect($activity->letter_body)->toBe("The club will hold its General Assembly at PC 2-1.\n\nWe will keep the room clean.")
        ->and($activity->letter_ai_drafted)->toBeTrue();
});

it('refuses a letter that does not name the room being booked', function () {
    [$officer] = vlClub();

    $this->withSession(vlSession($officer))
        ->post(route('officer.activities.store'), vlPayload(['letter_body' => 'We would like to borrow the AVR for our assembly.']))
        ->assertSessionHasErrors('letter_body');

    expect(ClubActivity::count())->toBe(0);
});

it('never sends an internal meeting to the VPA or writes it a letter', function () {
    [$officer, , , , $vpa] = vlClub();

    $activity = vlSend($officer, ['activity_type' => 'internal_meeting']);

    expect($activity->approval_status)->toBe('no_approval_needed')
        ->and($activity->hasBookingLetter())->toBeFalse()
        ->and(ClubNotification::where('recipient_id', $vpa->id)->exists())->toBeFalse();
});

it('writes the template letter from the activity details', function () {
    $body = BookingLetter::templateBody('Dreamhunters Esports Society', [
        'title'                 => 'General Assembly',
        'date'                  => '2026-10-30',
        'time_start'            => '13:00',
        'time_end'              => '17:00',
        'venue'                 => 'PC 2-1',
        'purpose'               => 'Gather our members',
        'expected_participants' => 40,
    ]);

    expect($body)->toContain('The Dreamhunters Esports Society will be holding its General Assembly on Friday, October 30, 2026, from 1:00 PM to 5:00 PM at PC 2-1.')
        ->toContain('Gather our members.')
        ->toContain('40 participants')
        ->toContain('kept clean and orderly');
});

it('tells the DSA when there is no VPA account to approve the letter', function () {
    [$officer] = vlClub();
    User::where('role', 'vpa')->delete();
    $dsa = vlUser('dsa');

    $activity = vlSend($officer);

    expect($activity->approval_status)->toBe('pending_vpa')
        ->and(ClubNotification::where('recipient_id', $dsa->id)->where('title', 'Letter waiting — no VPA account')->exists())->toBeTrue();
});

// ── 2. The VPA approves or returns it ───────────────────────────────────────

it('shows the VPA the letter on their dashboard and review page', function () {
    [$officer, $club, , , $vpa] = vlClub();
    $activity = vlSend($officer);

    $this->withSession(vlSession($vpa))
        ->get(route('vpa.dashboard'))
        ->assertOk()
        ->assertSee('General Assembly')
        ->assertSee($club->name);

    $this->withSession(vlSession($vpa))
        ->get(route('vpa.letters.show', $activity))
        ->assertOk()
        ->assertSee('Dear ' . config('vpa.salutation') . ',')
        ->assertSee(BookingLetter::GREETING)
        ->assertSee(config('vpa.name'))
        ->assertSee('Noted by:')
        ->assertSee('Approved by:')
        ->assertSee('Approve &amp; Sign', false)
        ->assertSee('Return with remarks');
});

it('signs the letter and passes the activity to the adviser when the VPA approves', function () {
    [$officer, $club, $adviser, $member, $vpa] = vlClub();
    $activity = vlSend($officer);

    $this->withSession(vlSession($vpa))
        ->post(route('vpa.letters.approve', $activity))
        ->assertRedirect(route('vpa.letters.index'));

    $fresh = $activity->fresh();

    expect($fresh->approval_status)->toBe('pending_approval')
        ->and($fresh->vpa_decision)->toBe('approved')
        ->and($fresh->vpa_reviewed_by)->toBe($vpa->id)
        ->and($fresh->letterSignature('vpa')['name'])->toBe(config('vpa.name'))
        ->and($fresh->letterSignature('vpa')['signed_by'])->toBe($vpa->name)
        ->and($fresh->letterSignature('officer')['user_id'])->toBe($officer->id)
        ->and($fresh->isConfirmed())->toBeFalse();

    expect(ClubNotification::where('recipient_id', $adviser->id)->where('title', 'Activity to review')->exists())->toBeTrue()
        ->and(ClubNotification::where('recipient_id', $officer->id)->where('title', 'Letter approved by the VPA')->exists())->toBeTrue()
        ->and(ClubNotification::where('recipient_id', $member->id)->exists())->toBeFalse()
        ->and(AuditLog::where('action', 'activity.letter.vpa_approved')->exists())->toBeTrue();
});

it('needs remarks to return a letter, and releases the room when it does', function () {
    [$officer, , , , $vpa] = vlClub();
    $activity = vlSend($officer);

    $this->withSession(vlSession($vpa))
        ->post(route('vpa.letters.reject', $activity), ['review_remarks' => ''])
        ->assertSessionHasErrors('review_remarks');

    expect($activity->fresh()->approval_status)->toBe('pending_vpa');

    $this->withSession(vlSession($vpa))
        ->post(route('vpa.letters.reject', $activity), ['review_remarks' => 'PC 2-1 is under repair that week.'])
        ->assertRedirect(route('vpa.letters.index'));

    $fresh = $activity->fresh();

    expect($fresh->approval_status)->toBe('rejected')
        ->and($fresh->wasRejectedByVpa())->toBeTrue()
        ->and($fresh->review_remarks)->toBe('PC 2-1 is under repair that week.');

    $notice = ClubNotification::where('recipient_id', $officer->id)->where('title', 'Letter returned by the VPA')->first();
    expect($notice)->not->toBeNull()->and($notice->body)->toContain('under repair');

    // Another club can now book the room for that slot.
    expect(ClubActivity::findVenueConflict('PC 2-1', $fresh->date->toDateString(), '13:00', '17:00'))->toBeNull();
});

it('lets the VPA decide a letter only once', function () {
    [$officer, , , , $vpa] = vlClub();
    $activity = vlApproveAsVpa($vpa, vlSend($officer));

    $this->withSession(vlSession($vpa))
        ->post(route('vpa.letters.reject', $activity), ['review_remarks' => 'Changed my mind about it.'])
        ->assertSessionHas('error');

    expect($activity->fresh()->approval_status)->toBe('pending_approval');
});

it('keeps the VPA pages to the VPA', function (string $role) {
    [$officer, $club, $adviser, $member] = vlClub();
    $activity = vlSend($officer);

    $user = match ($role) {
        'officer' => $officer,
        'adviser' => $adviser,
        'member'  => $member,
        default   => vlUser($role),
    };

    $this->withSession(vlSession($user))->get(route('vpa.dashboard'))->assertForbidden();
    $this->withSession(vlSession($user))->get(route('vpa.letters.show', $activity))->assertForbidden();
    $this->withSession(vlSession($user))->post(route('vpa.letters.approve', $activity))->assertForbidden();

    expect($activity->fresh()->approval_status)->toBe('pending_vpa');
})->with(['officer', 'adviser', 'member', 'dsa']);

// ── 3. The adviser confirms it ──────────────────────────────────────────────

it('does not let the adviser decide before the VPA has', function () {
    [$officer, , $adviser, , $vpa] = vlClub();
    $activity = vlSend($officer);

    $this->withSession(vlSession($adviser))
        ->get(route('adviser.activities.review'))
        ->assertOk()
        ->assertSee('With the VPA');

    $this->withSession(vlSession($adviser))
        ->post(route('adviser.activities.approve', $activity))
        ->assertSessionHas('error');

    expect($activity->fresh()->approval_status)->toBe('pending_vpa');
});

it('confirms the activity and signs the letter when the adviser approves after the VPA', function () {
    [$officer, $club, $adviser, $member, $vpa] = vlClub();
    $activity = vlApproveAsVpa($vpa, vlSend($officer));

    $this->withSession(vlSession($adviser))
        ->get(route('adviser.activities.show', $activity))
        ->assertOk()
        ->assertSee('Approve &amp; Sign', false);

    $this->withSession(vlSession($adviser))
        ->post(route('adviser.activities.approve', $activity));

    $fresh = $activity->fresh();

    expect($fresh->approval_status)->toBe('approved')
        ->and($fresh->isConfirmed())->toBeTrue()
        ->and($fresh->letterSignature('adviser')['user_id'])->toBe($adviser->id)
        ->and($fresh->letterSignature('adviser')['position'])->toBe($club->acronym . ' Club Adviser')
        ->and($fresh->letterSignature('vpa'))->not->toBeNull()
        ->and(ClubNotification::where('recipient_id', $member->id)->exists())->toBeTrue();
});

// ── 4. Editing sends the letter back to the VPA ─────────────────────────────

it('sends a returned letter to the VPA again when the officer edits it', function () {
    [$officer, , , , $vpa] = vlClub();
    $activity = vlSend($officer);

    $this->withSession(vlSession($vpa))
        ->post(route('vpa.letters.reject', $activity), ['review_remarks' => 'Please use a bigger room.']);
    ClubNotification::where('recipient_id', $vpa->id)->delete();

    $this->withSession(vlSession($officer))
        ->patch(route('officer.activities.update', $activity), vlPayload([
            'venue'       => 'AVR',
            'letter_body' => 'We will hold our General Assembly at the AVR. We will keep the AVR clean and orderly.',
        ]))
        ->assertSessionHasNoErrors();

    $fresh = $activity->fresh();

    expect($fresh->approval_status)->toBe('pending_vpa')
        ->and($fresh->vpa_decision)->toBeNull()
        ->and($fresh->review_remarks)->toBeNull()
        ->and($fresh->letter_body)->toContain('AVR')
        ->and(ClubNotification::where('recipient_id', $vpa->id)->exists())->toBeTrue();
});

it('will not send a letter that still describes the old date, time or room', function () {
    [$officer] = vlClub();
    $activity = vlSend($officer);

    $this->withSession(vlSession($officer))
        ->patch(route('officer.activities.update', $activity), vlPayload([
            'venue'       => 'AVR',
            'letter_body' => $activity->letter_body,
        ]))
        ->assertSessionHasErrors('letter_body');

    expect($activity->fresh()->venue)->toBe('PC 2-1');
});

it('leaves an approved activity approved when it is saved unchanged', function () {
    [$officer, , $adviser, , $vpa] = vlClub();
    $activity = vlApproveAsVpa($vpa, vlSend($officer));

    $this->withSession(vlSession($adviser))->post(route('adviser.activities.approve', $activity));
    $this->flushSession();

    $this->withSession(vlSession($officer))
        ->patch(route('officer.activities.update', $activity), vlPayload(['letter_body' => $activity->letter_body]))
        ->assertSessionHasNoErrors();

    expect($activity->fresh()->approval_status)->toBe('approved');
});

// ── 5. The letter as a PDF ──────────────────────────────────────────────────

it('downloads the letter as a PDF for the club, the VPA, the adviser and the DSA', function () {
    [$officer, , $adviser, , $vpa] = vlClub();
    $activity = vlApproveAsVpa($vpa, vlSend($officer));

    foreach ([
        [$officer, route('officer.activities.booking-letter', $activity)],
        [$vpa,     route('vpa.letters.pdf', $activity)],
        [$adviser, route('adviser.activities.booking-letter', $activity)],
        [vlUser('dsa'), route('dsa.activities.booking-letter', $activity)],
    ] as [$user, $url]) {
        $this->withSession(vlSession($user))
            ->get($url)
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
});

it('keeps one club\'s letter from another club\'s officer and adviser', function () {
    [$officer] = vlClub();
    $activity = vlSend($officer);

    [$otherOfficer, , $otherAdviser] = vlClub();

    $this->withSession(vlSession($otherOfficer))
        ->get(route('officer.activities.booking-letter', $activity))
        ->assertNotFound();

    $this->withSession(vlSession($otherAdviser))
        ->get(route('adviser.activities.booking-letter', $activity))
        ->assertForbidden();
});

// ── 6. Drafting the letter (AI, or the template) ────────────────────────────

it('drafts the letter from the template when the AI is unavailable', function () {
    [$officer] = vlClub();
    Http::fake();

    $response = $this->withSession(vlSession($officer))
        ->postJson(route('officer.activities.letter-draft'), vlPayload())
        ->assertOk();

    expect($response->json('ai'))->toBeFalse()
        ->and($response->json('body'))->toContain('PC 2-1')
        ->and($response->json('notice'))->toContain('AI Unavailable — Manual Draft Required');

    Http::assertNothingSent();
    expect(ClubActivity::count())->toBe(0);
});

it('returns the AI draft without a greeting or closing of its own, and saves nothing', function () {
    [$officer] = vlClub();
    config(['gemini.api_key' => 'test-key']);

    Http::fake(['*generativelanguage*' => Http::response([
        'candidates' => [[
            'finishReason' => 'STOP',
            'content'      => ['parts' => [['text' => "Dear Dr. Omandam,\n\nThe club will be holding its General Assembly at PC 2-1 to gather our members.\n\nWe will ensure the room is kept clean. Thank you.\n\nWe look forward to your approval."]]],
        ]],
    ])]);

    $response = $this->withSession(vlSession($officer))
        ->postJson(route('officer.activities.letter-draft'), vlPayload())
        ->assertOk();

    expect($response->json('ai'))->toBeTrue()
        ->and($response->json('body'))->toBe("The club will be holding its General Assembly at PC 2-1 to gather our members.\n\nWe will ensure the room is kept clean.")
        ->and($response->json('notice'))->toContain('AI-assisted draft');

    expect(ClubActivity::count())->toBe(0);
});

it('falls back to the template when the AI letter does not name the room', function () {
    [$officer] = vlClub();
    config(['gemini.api_key' => 'test-key']);

    Http::fake(['*generativelanguage*' => Http::response([
        'candidates' => [[
            'finishReason' => 'STOP',
            'content'      => ['parts' => [['text' => 'We will hold our assembly in the gym.']]],
        ]],
    ])]);

    $response = $this->withSession(vlSession($officer))
        ->postJson(route('officer.activities.letter-draft'), vlPayload())
        ->assertOk();

    expect($response->json('ai'))->toBeFalse()
        ->and($response->json('body'))->toContain('at PC 2-1');
});

// ── 7. Signatures ───────────────────────────────────────────────────────────

it('signs letters with the signature the officer saved, kept as it was when signed', function () {
    [$officer, , , , $vpa] = vlClub();

    $this->withSession(vlSession($officer))
        ->post(route('profile.signature'), ['signature_drawn' => vlDrawnSignature()])
        ->assertSessionHasNoErrors();

    $path = $officer->fresh()->signature_path;
    expect($path)->not->toBeNull();
    Storage::disk('local')->assertExists($path);

    // Stored as a transparent PNG cropped to the ink.
    $stored = imagecreatefromstring(Storage::disk('local')->get($path));
    expect(imagesx($stored))->toBeLessThan(300)
        ->and((imagecolorat($stored, 0, 0) >> 24) & 0x7F)->toBe(127);

    $activity = vlSend($officer);
    expect($activity->letterSignature('officer')['signature_path'])->toBe($path);

    // A new signature does not change the letter already signed, nor delete what it was signed with.
    $this->withSession(vlSession($officer))
        ->post(route('profile.signature'), ['signature_drawn' => vlDrawnSignature()]);
    $this->flushSession();

    expect($officer->fresh()->signature_path)->not->toBe($path);
    Storage::disk('local')->assertExists($path);

    $letter = app(BookingLetter::class)->compose($activity->fresh());
    expect($letter['signers']['officer']['image'])->toStartWith('data:image/png;base64,');
});

it('reads the ink off a photo of a signature on white paper', function () {
    [, , $adviser] = vlClub();

    $photo = imagecreatetruecolor(400, 200);
    imagefill($photo, 0, 0, imagecolorallocate($photo, 250, 250, 245));
    imagesetthickness($photo, 4);
    imageline($photo, 40, 150, 360, 60, imagecolorallocate($photo, 20, 30, 120));
    $file = UploadedFile::fake()->createWithContent('signature.jpg', (function () use ($photo) {
        ob_start();
        imagejpeg($photo, null, 95);

        return (string) ob_get_clean();
    })());

    $this->withSession(vlSession($adviser))
        ->post(route('profile.signature'), ['signature_file' => $file])
        ->assertSessionHasNoErrors();

    $stored = imagecreatefromstring(Storage::disk('local')->get($adviser->fresh()->signature_path));
    expect((imagecolorat($stored, 0, 0) >> 24) & 0x7F)->toBe(127); // the paper is gone
});

it('refuses a blank signature', function () {
    [$officer] = vlClub();

    $blank = imagecreatetruecolor(200, 80);
    imagefill($blank, 0, 0, imagecolorallocate($blank, 255, 255, 255));
    ob_start();
    imagepng($blank);
    $uri = 'data:image/png;base64,' . base64_encode((string) ob_get_clean());

    $this->withSession(vlSession($officer))
        ->post(route('profile.signature'), ['signature_drawn' => $uri])
        ->assertSessionHasErrors('signature');

    expect($officer->fresh()->signature_path)->toBeNull();
});

it('only asks the people who sign letters for a signature', function () {
    [, , , $member] = vlClub();

    $this->withSession(vlSession($member))
        ->post(route('profile.signature'), ['signature_drawn' => vlDrawnSignature()])
        ->assertForbidden();
});

it('serves a signature only to its owner', function () {
    [$officer, , $adviser] = vlClub();

    $this->withSession(vlSession($officer))->post(route('profile.signature'), ['signature_drawn' => vlDrawnSignature()]);
    $this->flushSession();

    $this->withSession(vlSession($officer))->get(route('profile.signature.show'))->assertOk()->assertHeader('content-type', 'image/png');
    $this->flushSession();

    // The adviser has none of their own, and there is no way to ask for someone else's.
    $this->withSession(vlSession($adviser))->get(route('profile.signature.show'))->assertNotFound();
});

// ── 8. The VPA account ──────────────────────────────────────────────────────

it('takes the VPA to their own dashboard when they sign in', function () {
    expect(app(SessionManager::class)->redirectByRole('vpa')->getTargetUrl())->toBe(route('vpa.dashboard'));
});

it('creates the VPA account with the artisan command', function () {
    Http::fake(['*accounts:signUp*' => Http::response(['localId' => 'firebase-vpa-uid'])]);

    $this->artisan('app:create-vpa', ['--email' => 'vpa.office@sccpag.edu.ph', '--password' => 'Approve1Letters'])
        ->assertSuccessful();

    $vpa = User::where('email', 'vpa.office@sccpag.edu.ph')->first();

    expect($vpa)->not->toBeNull()
        ->and($vpa->role)->toBe('vpa')
        ->and($vpa->firebase_uid)->toBe('firebase-vpa-uid');
});

it('refuses a VPA address outside the school', function () {
    Http::fake();

    $this->artisan('app:create-vpa', ['--email' => 'vpa@gmail.com'])->assertFailed();

    Http::assertNothingSent();
});

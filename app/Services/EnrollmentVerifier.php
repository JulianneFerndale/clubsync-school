<?php

namespace App\Services;

use App\Exceptions\UnreadableDocument;
use App\Models\AuditLog;
use App\Models\ClubNotification;
use App\Models\Course;
use App\Models\EnrollmentVerification;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Checks that a registering student is a bona fide SCC student (POLICY.md §Registration).
 *
 * The student uploads their Certificate of Enrollment or the front of their school ID.
 * Gemini reads it — what kind of document it is, and the name, EDP number and course
 * printed on it — and the comparison with what they registered with happens HERE, in
 * code, so the model is never shown the answer it is supposed to confirm.
 *
 * A clean match lets them straight in. When the check cannot decide — Gemini is off or
 * down, the document looks edited, or a genuine student's photo keeps failing and they
 * ask for a person — the case goes to the DSA, and an administrator may decide it too.
 * That is POLICY.md §AI & Automation's "manual alternatives stay fully functional".
 * A case waiting only because Gemini was down is also checked again automatically once
 * it is back (recheck()), so an outage does not leave the DSA a pile to clear by hand.
 *
 * RA 10173: the document carries more than ClubSync needs (a COE has the birth date, an
 * ID the face), so it is never kept once decided. A passing document is never stored at
 * all; one waiting for a person is stored encrypted until they decide, then deleted.
 */
class EnrollmentVerifier
{
    public const DISK = 'local';

    /** Encrypted documents waiting for a person; nothing here outlives its decision. */
    public const ROOT = 'enrollment-documents';

    /** In kilobytes. Photos are shrunk in the browser first, so this is mostly for PDFs. */
    public const MAX_KB = 10240;

    /** Failed automatic checks before the student may hand the document to a person instead. */
    public const ATTEMPTS_BEFORE_REVIEW = 2;

    /** Enough for the small print on a COE photographed whole. */
    private const MAX_SIDE = 2000;

    private const JPEG_QUALITY = 85;

    /** Name parts a document may print or leave out, so they are never required to match. */
    private const NAME_SUFFIXES = ['JR', 'SR', 'II', 'III', 'IV', 'V'];

    public function __construct(
        private GeminiService $gemini,
        private AcademicClubEnrollment $enrollment,
    ) {}

    public static function rules(): array
    {
        return [
            'document' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:' . self::MAX_KB],
            'action'   => ['nullable', 'in:check,review'],
        ];
    }

    public static function messages(): array
    {
        return [
            'document.required' => 'Upload your Certificate of Enrollment or a photo of the front of your school ID.',
            'document.file'     => 'Upload your Certificate of Enrollment or a photo of the front of your school ID.',
            'document.mimes'    => 'Upload a photo (JPG, PNG or WEBP) or a PDF.',
            'document.max'      => 'That file is larger than 10 MB. Upload a smaller photo or PDF.',
            'document.uploaded' => 'The file did not finish uploading — it may be too large. Try a smaller photo.',
        ];
    }

    /**
     * Read the document and compare it with the student's registration.
     *
     * @param  array{first_name: string, last_name: string, edp_number: string}  $student
     *
     * @throws UnreadableDocument when the file is neither an image nor a PDF
     */
    public function check(UploadedFile $file, array $student, Course $course): EnrollmentCheck
    {
        [$bytes, $mime] = $this->prepare($file);

        $findings = $this->gemini->readEnrollmentDocument($bytes, $mime, $this->courseList());

        if ($findings === null) {
            $this->audit(null, EnrollmentCheck::UNAVAILABLE, null, []);

            return new EnrollmentCheck(EnrollmentCheck::UNAVAILABLE, $bytes, $mime);
        }

        $problems = $this->compare($findings, $student, $course);

        $outcome = match (true) {
            $problems !== []           => EnrollmentCheck::FAILED,
            $findings['looks_altered'] => EnrollmentCheck::FLAGGED,
            default                    => EnrollmentCheck::PASSED,
        };

        $this->audit(null, $outcome, $findings['document_type'], array_keys($problems));

        return new EnrollmentCheck($outcome, $bytes, $mime, $findings, $problems);
    }

    /**
     * The automatic check passed: record it and put the student forward to their
     * academic club(s), exactly as an approval would.
     *
     * @return Collection<int, \App\Models\Club>
     */
    public function markVerified(User $user, EnrollmentCheck $check): Collection
    {
        $verification = EnrollmentVerification::create([
            'user_id'       => $user->id,
            'status'        => EnrollmentVerification::STATUS_VERIFIED,
            'method'        => 'automatic',
            'document_type' => $check->findings['document_type'] ?? null,
            'reviewed_at'   => now(),
        ]);

        AuditLog::record('enrollment.verified', $verification, [
            'method'        => 'automatic',
            'document_type' => $verification->document_type,
        ], $user->id);

        return $this->admit($user);
    }

    /**
     * Hand the document to a person: stored encrypted, the DSA told, the student held
     * on the verification page until someone decides.
     */
    public function sendForReview(User $user, EnrollmentCheck $check, string $reason): EnrollmentVerification
    {
        $path = self::ROOT . '/' . Str::uuid() . '.enc';

        if (! Storage::disk(self::DISK)->put($path, Crypt::encryptString($check->bytes))) {
            throw new RuntimeException('The enrollment document could not be saved for review.');
        }

        // A student re-submitting after a rejection replaces the case, not adds to it.
        $this->discardPending($user);

        $verification = EnrollmentVerification::create([
            'user_id'       => $user->id,
            'status'        => EnrollmentVerification::STATUS_PENDING,
            'review_reason' => $reason,
            'document_type' => $check->findings['document_type'] ?? null,
            'document_path' => $path,
            'document_mime' => $check->mime,
            'findings'      => $check->findingsForRecord(),
        ]);

        $user->forceFill(['enrollment_status' => EnrollmentVerification::STATUS_PENDING])->save();

        AuditLog::record('enrollment.review_requested', $verification, [
            'reason'        => $reason,
            'document_type' => $verification->document_type,
        ], $user->id);

        $this->alertDsa($user, $verification);

        return $verification;
    }

    /**
     * A person accepted the document. $method is 'dsa' or 'admin' — who decided.
     *
     * @return Collection<int, \App\Models\Club>
     */
    public function approve(EnrollmentVerification $verification, int $reviewerId, string $method): Collection
    {
        $this->decide($verification, EnrollmentVerification::STATUS_VERIFIED, $reviewerId, $method);

        $user = $verification->user;

        AuditLog::record('enrollment.approved', $verification, [
            'method'     => $method,
            'student_id' => $user->id,
        ], $reviewerId);

        $clubs = $this->admit($user);

        ClubNotification::create([
            'recipient_id' => $user->id,
            'sender_type'  => 'system',
            'club_id'      => null,
            'title'        => 'Your enrollment is verified',
            'body'         => 'The Student Affairs Office checked your proof of enrollment and your ClubSync account is now active.',
            'action_url'   => route('member.dashboard'),
        ]);

        return $clubs;
    }

    /** A person turned the document down; the student is told why and may upload another. */
    public function reject(EnrollmentVerification $verification, int $reviewerId, string $method, string $remarks): void
    {
        $this->decide($verification, EnrollmentVerification::STATUS_REJECTED, $reviewerId, $method, $remarks);

        $user = $verification->user;

        $user->forceFill(['enrollment_status' => EnrollmentVerification::STATUS_REJECTED])->save();

        AuditLog::record('enrollment.rejected', $verification, [
            'method'     => $method,
            'student_id' => $user->id,
        ], $reviewerId);

        ClubNotification::create([
            'recipient_id' => $user->id,
            'sender_type'  => 'system',
            'club_id'      => null,
            'title'        => 'Your proof of enrollment was not accepted',
            'body'         => "The Student Affairs Office could not accept the document you uploaded: {$remarks} Please upload your Certificate of Enrollment or the front of your school ID again.",
            'action_url'   => route('verification.show'),
        ]);
    }

    // ── Re-checking once the AI is back ─────────────────────────────────────

    /**
     * Whether a waiting case is only waiting because the automatic check could not run
     * when it was uploaded. Those are the cases the AI may still decide; a document the
     * check flagged, or one the student chose to hand to a person, stays with a person.
     */
    public static function awaitsRecheck(EnrollmentVerification $verification): bool
    {
        return $verification->isPending()
            && $verification->review_reason === EnrollmentVerification::REASON_AI_UNAVAILABLE
            && $verification->document_path !== null;
    }

    /**
     * Re-check the oldest waiting cases (POLICY.md §Registration: re-checked automatically
     * once the AI is back). Stops as soon as the AI is down again; the rest wait for the
     * next run or for a person, whichever comes first.
     *
     * @return array{verified: int, sent_back: int, flagged: int, waiting: int}
     */
    public function recheckWaiting(int $limit): array
    {
        $tally = ['verified' => 0, 'sent_back' => 0, 'flagged' => 0, 'waiting' => 0];

        $cases = EnrollmentVerification::where('status', EnrollmentVerification::STATUS_PENDING)
            ->where('review_reason', EnrollmentVerification::REASON_AI_UNAVAILABLE)
            ->whereNotNull('document_path')
            ->oldest()
            ->limit($limit)
            ->get();

        foreach ($cases as $case) {
            if (! $this->gemini->isReady()) {
                break;
            }

            match ($this->recheck($case)) {
                EnrollmentCheck::PASSED  => $tally['verified']++,
                EnrollmentCheck::FAILED  => $tally['sent_back']++,
                EnrollmentCheck::FLAGGED => $tally['flagged']++,
                default                  => null,
            };
        }

        $tally['waiting'] = EnrollmentVerification::where('status', EnrollmentVerification::STATUS_PENDING)
            ->where('review_reason', EnrollmentVerification::REASON_AI_UNAVAILABLE)
            ->count();

        return $tally;
    }

    /**
     * Run the automatic check on a document that was saved as Pending Verification
     * because the check was unavailable, and settle it the way the check would have at
     * upload time:
     *   - passed   verified, the document deleted, the student admitted and told.
     *   - failed   sent back to the student with the reasons, as a refusal at upload
     *              would have been. They upload again, and may still ask for a person.
     *   - flagged  kept for the DSA, now marked as flagged, and the DSA told.
     *
     * Returns the outcome, or null when nothing changed: the AI is still unavailable, or
     * a person decided the case while the check was running.
     */
    public function recheck(EnrollmentVerification $verification): ?string
    {
        if (! self::awaitsRecheck($verification) || ! $this->gemini->isReady()) {
            return null;
        }

        $user  = User::with('course')->find($verification->user_id);
        $bytes = self::documentContents($verification);

        if (! $user?->course || $bytes === null) {
            return null;
        }

        $findings = $this->gemini->readEnrollmentDocument($bytes, (string) $verification->document_mime, $this->courseList());

        if ($findings === null) {
            return null;
        }

        $problems = $this->compare($findings, [
            'first_name' => (string) $user->first_name,
            'last_name'  => (string) $user->last_name,
            'edp_number' => (string) $user->edp_number,
        ], $user->course);

        $outcome = match (true) {
            $problems !== []           => EnrollmentCheck::FAILED,
            $findings['looks_altered'] => EnrollmentCheck::FLAGGED,
            default                    => EnrollmentCheck::PASSED,
        };

        $this->audit(null, $outcome, $findings['document_type'], array_keys($problems), recheck: true);

        // The AI took seconds; a person may have decided in the meantime, and their
        // decision stands. Locked, so the two can never both land.
        return DB::transaction(function () use ($verification, $user, $findings, $problems, $outcome) {
            $case = EnrollmentVerification::whereKey($verification->id)->lockForUpdate()->first();

            if (! $case || ! self::awaitsRecheck($case) || $case->document_path !== $verification->document_path) {
                return null;
            }

            $case->document_type = $findings['document_type'];

            match ($outcome) {
                EnrollmentCheck::PASSED  => $this->verifyOnRecheck($case, $user),
                EnrollmentCheck::FAILED  => $this->sendBackOnRecheck($case, $user, $problems, $findings),
                EnrollmentCheck::FLAGGED => $this->flagOnRecheck($case, $user, $findings),
            };

            return $outcome;
        });
    }

    private function verifyOnRecheck(EnrollmentVerification $case, User $user): void
    {
        $this->decide($case, EnrollmentVerification::STATUS_VERIFIED, null, 'automatic');

        AuditLog::record('enrollment.verified', $case, [
            'method'        => 'automatic',
            'document_type' => $case->document_type,
            'recheck'       => true,
        ]);

        $clubs = $this->admit($user);

        ClubNotification::create([
            'recipient_id' => $user->id,
            'sender_type'  => 'system',
            'club_id'      => null,
            'title'        => 'Your enrollment is verified',
            'body'         => 'ClubSync checked your proof of enrollment as soon as the automatic check was available again, '
                . 'and your account is now active. ' . $this->enrollment->summary($clubs, $user->course),
            'action_url'   => route('member.dashboard'),
        ]);
    }

    /** @param  array<string, string>  $problems */
    private function sendBackOnRecheck(EnrollmentVerification $case, User $user, array $problems, array $findings): void
    {
        $remarks = self::remarksFor($problems, $findings);

        $this->decide($case, EnrollmentVerification::STATUS_REJECTED, null, 'automatic', $remarks);

        $user->forceFill(['enrollment_status' => EnrollmentVerification::STATUS_REJECTED])->save();

        AuditLog::record('enrollment.rejected', $case, [
            'method'     => 'automatic',
            'student_id' => $user->id,
            'failed'     => array_keys($problems),
            'recheck'    => true,
        ]);

        ClubNotification::create([
            'recipient_id' => $user->id,
            'sender_type'  => 'system',
            'club_id'      => null,
            'title'        => 'Your proof of enrollment did not match',
            'body'         => 'ClubSync checked the document you uploaded as soon as the automatic check was available again, '
                . "and it did not match your registration: {$remarks} Please upload your Certificate of Enrollment or the "
                . 'front of your school ID again.',
            'action_url'   => route('verification.show'),
        ]);
    }

    private function flagOnRecheck(EnrollmentVerification $case, User $user, array $findings): void
    {
        $case->forceFill([
            'review_reason' => EnrollmentVerification::REASON_FLAGGED,
            'findings'      => $findings + ['problems' => []],
        ])->save();

        AuditLog::record('enrollment.review_requested', $case, [
            'reason'        => EnrollmentVerification::REASON_FLAGGED,
            'document_type' => $case->document_type,
            'recheck'       => true,
        ]);

        // It was waiting for the AI; now it needs a person, so say so.
        $this->alertDsa($user, $case);
    }

    /**
     * What the student is told, and what is kept, when a re-check does not match.
     *
     * The upload-time messages quote what was read off the document. These are kept on
     * the record, so they say which field did not match without repeating the value read
     * (POLICY.md §Registration: only the outcome is kept).
     *
     * @param  array<string, string>  $problems
     */
    public static function remarksFor(array $problems, array $findings): string
    {
        $mismatch = [
            'name'       => [$findings['full_name'] ?? '', 'The name on the document does not match the name you registered with.'],
            'edp_number' => [preg_replace('/\D/', '', (string) ($findings['edp_number'] ?? '')), 'The EDP number on the document does not match the one you entered.'],
            'course'     => [$findings['course'] ?? '', 'The course on the document does not match the course you chose. If you picked the wrong course, change it before uploading again.'],
        ];

        return collect($problems)
            ->map(fn (string $message, string $field) => isset($mismatch[$field]) && $mismatch[$field][0] !== ''
                ? $mismatch[$field][1]
                : $message)
            ->implode(' ');
    }

    /** The decrypted document, for the reviewer's screen only. */
    public static function documentContents(EnrollmentVerification $verification): ?string
    {
        $disk = Storage::disk(self::DISK);

        if (! $verification->document_path || ! $disk->exists($verification->document_path)) {
            return null;
        }

        try {
            return Crypt::decryptString($disk->get($verification->document_path));
        } catch (\Throwable $e) {
            Log::warning("Enrollment document {$verification->id} could not be decrypted: " . class_basename($e));

            return null;
        }
    }

    // ── Matching ────────────────────────────────────────────────────────────

    /**
     * What on the document does not agree with the registration, field => message.
     * Empty means everything matched.
     *
     * @return array<string, string>
     */
    public function compare(array $findings, array $student, Course $course): array
    {
        if ($findings['document_type'] === 'school_id_back') {
            return ['document' => 'That is the back of your school ID. Upload the front, where your name, course and EDP number are printed.'];
        }

        if (! in_array($findings['document_type'], ['certificate_of_enrollment', 'school_id'], true)) {
            return ['document' => 'This does not look like a Saint Columban College Certificate of Enrollment or school ID. '
                . 'Upload a clear photo of your COE or of the front of your SCC ID.'];
        }

        $problems = [];

        if (! str_contains(self::letters($findings['school_name']), 'COLUMBAN')) {
            $problems['school'] = 'The document does not show that it was issued by Saint Columban College.';
        }

        $registeredName = trim($student['first_name'] . ' ' . $student['last_name']);

        if ($findings['full_name'] === '') {
            $problems['name'] = 'Your name could not be read on the document. Make sure it is in focus and not covered or cut off.';
        } elseif (! self::nameMatches($findings['full_name'], $student['first_name'], $student['last_name'])) {
            $problems['name'] = "The name on the document ({$findings['full_name']}) does not match the name you registered with ({$registeredName}).";
        }

        if (preg_replace('/\D/', '', $findings['edp_number']) === '') {
            $problems['edp_number'] = 'Your EDP number could not be read on the document. Make sure it is in focus and not covered or cut off.';
        } elseif (! self::edpMatches($findings['edp_number'], $student['edp_number'])) {
            $problems['edp_number'] = "The EDP number on the document ({$findings['edp_number']}) does not match the one you entered ({$student['edp_number']}).";
        }

        if (! self::courseMatches($findings, $course)) {
            $problems['course'] = $findings['course'] === ''
                ? 'Your course could not be read on the document. Make sure it is in focus and not covered or cut off.'
                : "The course on the document ({$findings['course']}) does not match the course you chose ({$course->name}).";
        }

        return $problems;
    }

    /**
     * Whether the printed name is the registered one.
     *
     * Every part of the registered first and last name must be on the document, in any
     * order — a COE prints "FLORES, JULIANNE FERNDALE T", an ID "JULIANNE FERNDALE T.
     * FLORES". Initials and suffixes are not required (documents print them or not), and
     * spacing may differ: "Dela Cruz" matches "DELACRUZ" and the other way round.
     */
    public static function nameMatches(string $printed, string $firstName, string $lastName): bool
    {
        $printedTokens = self::nameTokens($printed);

        if ($printedTokens === []) {
            return false;
        }

        foreach ([$firstName, $lastName] as $part) {
            $tokens = self::nameTokens($part);

            $required = array_values(array_filter(
                $tokens,
                fn (string $token) => strlen($token) > 1 && ! in_array($token, self::NAME_SUFFIXES, true),
            )) ?: $tokens;

            if ($required === []) {
                return false;
            }

            $allPresent = array_diff($required, $printedTokens) === [];

            if (! $allPresent && ! self::isRunOf(implode('', $required), $printedTokens)) {
                return false;
            }
        }

        return true;
    }

    /** Same digits, ignoring spaces, dashes and leading zeros. */
    public static function edpMatches(string $printed, string $entered): bool
    {
        $printed = ltrim((string) preg_replace('/\D/', '', $printed), '0');
        $entered = ltrim((string) preg_replace('/\D/', '', $entered), '0');

        return $printed !== '' && $printed === $entered;
    }

    /**
     * Whether the printed course is the chosen one: the course Gemini mapped it to, or —
     * if it mapped it to nothing — the printed text read as the course's abbreviation or
     * full name, so a missed mapping cannot fail an exact match.
     */
    public static function courseMatches(array $findings, Course $course): bool
    {
        if ($findings['course_id'] !== null) {
            return (int) $findings['course_id'] === (int) $course->id;
        }

        $printed = self::letters($findings['course']);

        return $printed !== ''
            && ($printed === self::letters(self::courseCode($course)) || $printed === self::letters($course->name));
    }

    /** "course_bsed_math" → "BSED MATH": the abbreviation hint the course slugs carry. */
    public static function courseCode(Course $course): string
    {
        return strtoupper(str_replace(['_', '-'], ' ', Str::after($course->slug, 'course_')));
    }

    // ── Internals ───────────────────────────────────────────────────────────

    /**
     * The document as it will be read and, if it comes to that, stored: a PDF as it
     * came, an image re-encoded as a JPEG — upright, no larger than MAX_SIDE, and with
     * the camera's metadata (GPS position included) left behind.
     *
     * @return array{0: string, 1: string} bytes, mime type
     */
    private function prepare(UploadedFile $file): array
    {
        $bytes = (string) file_get_contents($file->getRealPath());

        if (str_starts_with($bytes, '%PDF-')) {
            return [$bytes, 'application/pdf'];
        }

        $source = @imagecreatefromstring($bytes);

        if (! $source) {
            throw new UnreadableDocument('That file could not be opened. Upload a photo (JPG, PNG or WEBP) or a PDF.');
        }

        $source = $this->upright($source, $file);

        $width  = imagesx($source);
        $height = imagesy($source);
        $scale  = min(1, self::MAX_SIDE / max($width, $height));
        $w      = max(1, (int) round($width * $scale));
        $h      = max(1, (int) round($height * $scale));

        // White underneath, so a transparent PNG reads as print on paper.
        $canvas = imagecreatetruecolor($w, $h);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $w, $h, $width, $height);

        ob_start();
        imagejpeg($canvas, null, self::JPEG_QUALITY);

        return [(string) ob_get_clean(), 'image/jpeg'];
    }

    /** Phones store a portrait photo sideways with an orientation tag; apply the tag. */
    private function upright(\GdImage $image, UploadedFile $file): \GdImage
    {
        if (! function_exists('exif_read_data') || ! in_array($file->getMimeType(), ['image/jpeg', 'image/jpg'], true)) {
            return $image;
        }

        $orientation = (int) (@exif_read_data($file->getRealPath())['Orientation'] ?? 1);

        $rotated = match ($orientation) {
            3       => imagerotate($image, 180, 0),
            6       => imagerotate($image, -90, 0),
            8       => imagerotate($image, 90, 0),
            default => $image,
        };

        return $rotated ?: $image;
    }

    /** @return array<int, array{name: string, code: string}> */
    private function courseList(): array
    {
        return Course::orderBy('name')->get()
            ->mapWithKeys(fn (Course $course) => [$course->id => ['name' => $course->name, 'code' => self::courseCode($course)]])
            ->all();
    }

    /** Verified: the account opens, and the course makes them a member of their academic club. */
    private function admit(User $user): Collection
    {
        $user->forceFill(['enrollment_status' => EnrollmentVerification::STATUS_VERIFIED])->save();

        $course = $user->course;

        return $course ? $this->enrollment->enroll($user, $course) : collect();
    }

    /**
     * Close a case and delete its document — the decision is what is kept, not the file.
     * $reviewerId is null when no person decided it (a re-check once the AI was back).
     */
    private function decide(EnrollmentVerification $verification, string $status, ?int $reviewerId, string $method, ?string $remarks = null): void
    {
        $path = $verification->document_path;

        $verification->forceFill([
            'status'        => $status,
            'method'        => $method,
            'remarks'       => $remarks,
            'reviewed_by'   => $reviewerId,
            'reviewed_at'   => now(),
            'document_path' => null,
            'document_mime' => null,
            'findings'      => null,
        ])->save();

        if ($path) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    /** An undecided case being replaced: its file goes, and the row with it. */
    private function discardPending(User $user): void
    {
        EnrollmentVerification::where('user_id', $user->id)
            ->where('status', EnrollmentVerification::STATUS_PENDING)
            ->get()
            ->each(function (EnrollmentVerification $old) {
                if ($old->document_path) {
                    Storage::disk(self::DISK)->delete($old->document_path);
                }

                $old->delete();
            });
    }

    /** POLICY.md §Notifications: in-app is the system of record. */
    private function alertDsa(User $user, EnrollmentVerification $verification): void
    {
        foreach (User::where('role', 'dsa')->get() as $dsaUser) {
            ClubNotification::create([
                'recipient_id' => $dsaUser->id,
                'sender_type'  => 'system',
                'club_id'      => null,
                'title'        => 'Proof of enrollment to review',
                'body'         => "{$user->name} is waiting for their proof of enrollment to be checked. "
                    . $verification->review_reason_label . '.',
                'action_url'   => route('dsa.verifications.show', $verification),
            ]);
        }
    }

    /**
     * POLICY.md §Data Privacy: every AI event is audit-logged. The outcome and which
     * fields failed — never the values read off the document.
     *
     * @param  array<int, string>  $failed
     */
    private function audit(?int $actorId, string $outcome, ?string $documentType, array $failed, bool $recheck = false): void
    {
        AuditLog::record('ai.enrollment_document.checked', null, [
            'outcome'       => $outcome,
            'document_type' => $documentType,
            'failed'        => $failed,
        ] + ($recheck ? ['recheck' => true] : []), $actorId);
    }

    /** Upper-case ASCII letters and digits only — for comparing printed text. */
    private static function letters(string $value): string
    {
        return (string) preg_replace('/[^A-Z0-9]/', '', strtoupper(Str::ascii($value)));
    }

    /** @return array<int, string> */
    private static function nameTokens(string $value): array
    {
        $clean = (string) preg_replace('/[^A-Z]+/', ' ', strtoupper(Str::ascii($value)));

        return array_values(array_filter(explode(' ', $clean), fn (string $token) => $token !== ''));
    }

    /** Whether $joined is some run of consecutive $tokens written together. */
    private static function isRunOf(string $joined, array $tokens): bool
    {
        $count = count($tokens);

        for ($start = 0; $start < $count; $start++) {
            $run = '';

            for ($end = $start; $end < $count && strlen($run) < strlen($joined); $end++) {
                $run .= $tokens[$end];

                if ($run === $joined) {
                    return true;
                }
            }
        }

        return false;
    }
}

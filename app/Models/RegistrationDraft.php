<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

/**
 * A registration that has not reached its proof of enrollment yet (POLICY.md
 * §Registration). The wizard keeps its answers in the session, which is gone once the
 * browser is closed or the student signs out. Saving them here after each step means
 * signing in again resumes at the step reached, instead of "not registered".
 *
 * Only the wizard's own answers are kept: never the password (Firebase holds it) and
 * never a Firebase token. They are encrypted, the address is stored only as an HMAC for
 * lookup, and the row goes when the account is created or after a short while.
 */
class RegistrationDraft extends Model
{
    /** The answers kept: exactly what the wizard collects, nothing else. */
    private const FIELDS = [
        'first_name', 'last_name', 'edp_number', 'email', 'mobile_number', 'year_level',
        'via_google', 'photo_url', 'department_id', 'course_id',
    ];

    protected $fillable = ['firebase_uid', 'email_hash', 'details', 'expires_at'];

    protected $casts = [
        'details'    => 'encrypted:array',
        'expires_at' => 'datetime',
    ];

    public static function keepDays(): int
    {
        return max(1, (int) config('retention.registration_draft_days', 14));
    }

    /** Save the wizard as it stands. Every step calls this once its answer is in the session. */
    public static function saveFromSession(): void
    {
        $uid   = session('register.firebase_uid');
        $email = session('register.email');

        if (! $uid || ! $email) {
            return;
        }

        $details = [];

        foreach (self::FIELDS as $field) {
            $details[$field] = session("register.{$field}");
        }

        // Saving progress is a convenience: it must never be what stops a registration.
        try {
            // One draft per address: a stale one left by an earlier Firebase account gives way.
            static::where('email_hash', self::hashEmail($email))->where('firebase_uid', '!=', $uid)->delete();

            static::updateOrCreate(['firebase_uid' => $uid], [
                'email_hash' => self::hashEmail($email),
                'details'    => $details,
                'expires_at' => now()->addDays(self::keepDays()),
            ]);
        } catch (QueryException $e) {
            report($e);
        }
    }

    public static function forFirebaseUid(string $uid): ?self
    {
        return self::lookup(fn () => static::where('firebase_uid', $uid)->first());
    }

    public static function forEmail(string $email): ?self
    {
        return self::lookup(fn () => static::where('email_hash', self::hashEmail($email))->first());
    }

    /** The account was created, or the student dropped this registration. */
    public static function discard(?string $uid): void
    {
        if (! $uid) {
            return;
        }

        try {
            static::where('firebase_uid', $uid)->delete();
        } catch (QueryException $e) {
            // Left to expire: a created account is found before any draft is looked for.
            report($e);
        }
    }

    /** RA 10173 storage limitation: unfinished registrations past their time. */
    public static function pruneExpired(): int
    {
        return static::where('expires_at', '<', now())->delete();
    }

    /**
     * Put the wizard back in the session, with the Firebase tokens from the sign-in that
     * found it, and answer the route of the step it stopped at.
     */
    public function restore(array $account, ?string $password = null): string
    {
        $details = $this->details;

        $department = Department::find($details['department_id'] ?? null);
        $course     = Course::find($details['course_id'] ?? null);

        // Answers that no longer hold (a course moved or removed) are asked again.
        if (! $department) {
            $details['department_id'] = null;
        }

        if (! $department || ! $course || (int) $course->department_id !== (int) $department->id) {
            $details['course_id'] = null;
        }

        session()->forget('register');

        $values = [];

        foreach (self::FIELDS as $field) {
            $values["register.{$field}"] = $details[$field] ?? null;
        }

        session($values + [
            'register.via_google'      => (bool) ($details['via_google'] ?? false),
            'register.firebase_uid'    => $this->firebase_uid,
            'register.id_token'        => $account['idToken'] ?? null,
            'register.refresh_token'   => $account['refreshToken'] ?? null,
            'register.password'        => $password,
            'register.verify_attempts' => 0,
        ]);

        return match (true) {
            $details['course_id'] !== null     => 'register.verify',
            $details['department_id'] !== null => 'register.course',
            default                            => 'register.department',
        };
    }

    public static function hashEmail(string $email): string
    {
        return hash_hmac('sha256', strtolower(trim($email)), (string) config('app.key'));
    }

    /** The draft $query finds, unless it has run out. A lookup that fails finds nothing. */
    private static function lookup(callable $query): ?self
    {
        try {
            $draft = $query();

            if ($draft && $draft->expires_at->isPast()) {
                $draft->delete();

                return null;
            }

            return $draft;
        } catch (QueryException $e) {
            report($e);

            return null;
        }
    }
}

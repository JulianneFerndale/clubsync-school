<?php

namespace App\Services;

use App\Models\EnrollmentVerification;

/**
 * The result of one automatic check of a proof-of-enrollment document.
 *
 * Four outcomes, and only one of them lets the student straight in:
 *   - passed       the document is an SCC COE or ID front and its name, EDP number and
 *                  course all match the registration.
 *   - failed       it is not one of those documents, or something on it does not match.
 *                  `problems` says what, field by field, in words for the student.
 *   - unavailable  the check could not run (Gemini off, down, out of quota, or it gave
 *                  an unusable answer). Nothing is known either way, so a person decides.
 *   - flagged      everything matched, but the document looks edited. A person decides.
 *
 * `bytes`/`mime` are the prepared document (images re-encoded), kept in memory so a
 * case that goes to a person can be stored without asking for the file again.
 */
final class EnrollmentCheck
{
    public const PASSED      = 'passed';
    public const FAILED      = 'failed';
    public const UNAVAILABLE = 'unavailable';
    public const FLAGGED     = 'flagged';

    /**
     * @param  array<string, string>  $problems  field => message, for a failed check
     */
    public function __construct(
        public readonly string $outcome,
        public readonly string $bytes,
        public readonly string $mime,
        public readonly ?array $findings = null,
        public readonly array $problems = [],
    ) {}

    public function passed(): bool
    {
        return $this->outcome === self::PASSED;
    }

    public function failed(): bool
    {
        return $this->outcome === self::FAILED;
    }

    /** Why a person has to decide when the check itself could not. */
    public function reviewReason(): string
    {
        return $this->outcome === self::FLAGGED
            ? EnrollmentVerification::REASON_FLAGGED
            : EnrollmentVerification::REASON_AI_UNAVAILABLE;
    }

    /** What was read and what did not match, without the document itself. */
    public function findingsForRecord(): ?array
    {
        if ($this->findings === null) {
            return null;
        }

        return $this->findings + ['problems' => array_values($this->problems)];
    }
}

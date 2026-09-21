<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A link to a page on Facebook, and nothing else.
 *
 * Club pages show this link to every user as a "Facebook" button, so it must not
 * be usable to send students to a look-alike site. The host is compared exactly —
 * a pattern match would let "facebook.com.example.net" or "facebook.com@example.net"
 * through.
 */
class FacebookPageUrl implements ValidationRule
{
    public const HOSTS = ['facebook.com', 'www.facebook.com', 'm.facebook.com', 'web.facebook.com', 'fb.com', 'www.fb.com'];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $parts = is_string($value) ? parse_url($value) : false;

        $valid = is_array($parts)
            && strtolower($parts['scheme'] ?? '') === 'https'
            && in_array(strtolower($parts['host'] ?? ''), self::HOSTS, true)
            && ! isset($parts['user'])
            && ! isset($parts['port'])
            && trim($parts['path'] ?? '', '/') !== '';

        if (! $valid) {
            $fail('Enter the full link to the club\'s Facebook page, starting with https://www.facebook.com/');
        }
    }
}

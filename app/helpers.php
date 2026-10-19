<?php

if (! function_exists('auth_role')) {
    function auth_role(): string
    {
        return session('firebase_user_role', '');
    }
}

if (! function_exists('auth_uid')) {
    function auth_uid(): string
    {
        return session('firebase_uid', '');
    }
}

if (! function_exists('auth_user_id')) {
    function auth_user_id(): int
    {
        return (int) session('firebase_user_id', 0);
    }
}

if (! function_exists('auth_is_admin')) {
    function auth_is_admin(): bool
    {
        return (bool) session('firebase_is_admin', false);
    }
}

if (! function_exists('role_layout')) {
    /** The app layout a page shared by every role (profile, chat) should extend. */
    function role_layout(?string $role): string
    {
        if ($role === 'officer' || \App\Models\User::isOfficerRole($role)) {
            return 'layouts.app-officer';
        }

        return match ($role) {
            'dsa'                            => 'layouts.app-dsa',
            'vpa'                            => 'layouts.app-vpa',
            'adviser'                        => 'layouts.app-adviser',
            default                          => 'layouts.app-member',
        };
    }
}

if (! function_exists('year_level_label')) {
    /** Turn a numeric school year level (1–4) into an ordinal label like "1st Year". */
    function year_level_label(int $level): string
    {
        return match ($level) {
            1 => '1st Year',
            2 => '2nd Year',
            3 => '3rd Year',
            4 => '4th Year',
            default => $level . 'th Year',
        };
    }
}

if (! function_exists('number_in_words')) {
    /**
     * Spell a whole number out in English, up to the millions.
     *
     * Written here rather than leaning on ext-intl's NumberFormatter, which is not
     * guaranteed to be installed on the server this runs on — and a receipt that
     * silently loses its amount in words is worse than one that never had it.
     */
    function number_in_words(int $number): string
    {
        if ($number === 0) {
            return 'zero';
        }

        if ($number < 0) {
            return 'negative ' . number_in_words(-$number);
        }

        $ones = [
            1 => 'one', 2 => 'two', 3 => 'three', 4 => 'four', 5 => 'five', 6 => 'six',
            7 => 'seven', 8 => 'eight', 9 => 'nine', 10 => 'ten', 11 => 'eleven',
            12 => 'twelve', 13 => 'thirteen', 14 => 'fourteen', 15 => 'fifteen',
            16 => 'sixteen', 17 => 'seventeen', 18 => 'eighteen', 19 => 'nineteen',
        ];

        $tens = [
            2 => 'twenty', 3 => 'thirty', 4 => 'forty', 5 => 'fifty',
            6 => 'sixty', 7 => 'seventy', 8 => 'eighty', 9 => 'ninety',
        ];

        foreach ([1000000 => 'million', 1000 => 'thousand', 100 => 'hundred'] as $unit => $name) {
            if ($number >= $unit) {
                $head = number_in_words(intdiv($number, $unit)) . ' ' . $name;
                $rest = $number % $unit;

                return $rest ? $head . ' ' . number_in_words($rest) : $head;
            }
        }

        if ($number < 20) {
            return $ones[$number];
        }

        $rest = $number % 10;

        return $tens[intdiv($number, 10)] . ($rest ? '-' . $ones[$rest] : '');
    }
}

if (! function_exists('peso_in_words')) {
    /** A peso amount written the way a receipt writes it: "one hundred fifty pesos only". */
    function peso_in_words(float $amount): string
    {
        $pesos    = (int) floor(abs($amount));
        $centavos = (int) round((abs($amount) - $pesos) * 100);

        // Rounding up to a full peso, e.g. 99.999.
        if ($centavos === 100) {
            $pesos++;
            $centavos = 0;
        }

        $words = number_in_words($pesos) . ' ' . ($pesos === 1 ? 'peso' : 'pesos');

        if ($centavos > 0) {
            $words .= ' and ' . number_in_words($centavos) . ' ' . ($centavos === 1 ? 'centavo' : 'centavos');
        }

        return $words . ' only';
    }
}

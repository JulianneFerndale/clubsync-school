<?php

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\ClubOfficer;
use App\Models\Fee;
use App\Models\FeePayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
 * Confirming a payment issues a receipt, numbered once and kept.
 *
 * The number is the thing a member quotes back and the treasurer looks up, so it has
 * to be unique, gap-free within its year, and never reassigned — including when a
 * payment is undone and confirmed again.
 */

function frUser(string $role = 'member'): User
{
    static $n = 0;
    $n++;

    return User::create([
        'first_name'   => 'Fee',
        'last_name'    => "Payer{$n}",
        'edp_number'   => str_pad((string) (600 + $n), 10, '0', STR_PAD_LEFT),
        'email'        => "fr{$n}@sccpag.edu.ph",
        'password'     => Hash::make('Str0ng!Pass1'),
        'role'         => $role,
        'course_id'    => testCourse()->id,
        'is_admin'     => false,
        'is_suspended' => false,
    ]);
}

function frSession(User $user): array
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

/** A club with a treasurer, a fee and one member who owes it. */
function frSetup(float $amount = 150.50): array
{
    static $n = 0;
    $n++;

    $club = Club::create([
        'slug'            => "fee-club-{$n}",
        'acronym'         => "FC{$n}",
        'club_type'       => 'Non-Academic',
        'type'            => 'non_academic',
        'course_slug'     => 'all',
        'department_slug' => 'all',
        'name'            => "Fee Club {$n}",
        'is_active'       => true,
    ]);

    $officer = frUser('treasurer');
    ClubOfficer::create(['club_id' => $club->id, 'user_id' => $officer->id, 'position' => 'treasurer']);

    $member = frUser('member');
    ClubMember::create(['club_id' => $club->id, 'user_id' => $member->id, 'status' => 'active', 'role' => 'Member']);

    $fee = Fee::create([
        'club_id'         => $club->id,
        'title'           => 'Membership Fee',
        'amount'          => $amount,
        'due_date'        => now()->addDays(14)->toDateString(),
        'academic_period' => '1st Semester, A.Y. 2026-2027',
    ]);

    FeePayment::create(['fee_id' => $fee->id, 'user_id' => $member->id, 'status' => 'pending']);

    return [$club, $officer, $member, $fee];
}

function frMarkPaid(User $officer, Fee $fee, User $member)
{
    return test()->withSession(frSession($officer))
        ->post(route('officer.fees.paid', [$fee, $member->id]));
}

// ── Issuing ─────────────────────────────────────────────────────────────────

it('issues a receipt number when the payment is confirmed', function () {
    [, $officer, $member, $fee] = frSetup();

    expect(FeePayment::first()->receipt_number)->toBeNull();

    frMarkPaid($officer, $fee, $member);

    $payment = FeePayment::first();

    expect($payment->status)->toBe('paid')
        ->and($payment->receipt_number)->toMatch('/^OR-\d{4}-\d{5}$/')
        ->and($payment->hasReceipt())->toBeTrue();
});

it('numbers receipts in one series per year', function () {
    $year = now()->year;

    foreach (range(1, 3) as $i) {
        [, $officer, $member, $fee] = frSetup();
        frMarkPaid($officer, $fee, $member);
    }

    expect(FeePayment::whereNotNull('receipt_number')->orderBy('id')->pluck('receipt_number')->all())
        ->toBe(["OR-{$year}-00001", "OR-{$year}-00002", "OR-{$year}-00003"]);
});

it('never issues the same number twice', function () {
    foreach (range(1, 5) as $i) {
        [, $officer, $member, $fee] = frSetup();
        frMarkPaid($officer, $fee, $member);
    }

    $numbers = FeePayment::whereNotNull('receipt_number')->pluck('receipt_number');

    expect($numbers)->toHaveCount(5)
        ->and($numbers->unique())->toHaveCount(5);
});

it('keeps the same number when a payment is undone and confirmed again', function () {
    // One payment must never put two receipts in circulation.
    [, $officer, $member, $fee] = frSetup();

    frMarkPaid($officer, $fee, $member);
    $issued = FeePayment::first()->receipt_number;

    $this->withSession(frSession($officer))->post(route('officer.fees.unpaid', [$fee, $member->id]));

    // Undone: the number stays on the record, but there is nothing to receipt.
    $payment = FeePayment::first();
    expect($payment->status)->toBe('pending')
        ->and($payment->receipt_number)->toBe($issued)
        ->and($payment->hasReceipt())->toBeFalse();

    frMarkPaid($officer, $fee, $member);

    expect(FeePayment::first()->receipt_number)->toBe($issued);
});

// ── Handing it over ─────────────────────────────────────────────────────────

it('downloads the receipt as a PDF named after its number', function () {
    [, $officer, $member, $fee] = frSetup();
    frMarkPaid($officer, $fee, $member);

    $number = FeePayment::first()->receipt_number;

    $response = $this->withSession(frSession($officer))
        ->get(route('officer.fees.receipt', [$fee, $member->id]));

    $response->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertDownload("receipt-{$number}.pdf");
});

it('puts the payment on the receipt', function () {
    [$club, $officer, $member, $fee] = frSetup(150.50);
    frMarkPaid($officer, $fee, $member);

    // Render the same view the PDF is built from, so the contents can be read.
    $payment = FeePayment::with('user', 'confirmer')->first();
    $html    = view('fees.receipt-pdf', compact('payment', 'fee', 'club'))->render();

    expect($html)->toContain($payment->receipt_number)
        ->toContain('OFFICIAL RECEIPT')
        ->toContain('SAINT COLUMBAN COLLEGE')
        ->toContain($club->name)
        ->toContain($member->first_name)
        ->toContain($member->edp_number)
        ->toContain('Membership Fee')
        ->toContain('1st Semester, A.Y. 2026-2027')
        ->toContain('150.50')
        // The amount in words, as a receipt writes it.
        ->toContain('One hundred fifty pesos and fifty centavos only')
        ->toContain($officer->first_name);   // received by
});

// ── The letterhead: the school's crest and the club's own logo ──────────────

/** The receipt as HTML, rendered from the same view the PDF is built from. */
function frReceiptHtml(Club $club, Fee $fee): string
{
    $payment = FeePayment::with('user', 'confirmer')->first();

    return view('fees.receipt-pdf', compact('payment', 'fee', 'club'))->render();
}

/** A one-colour image, the way an upload arrives. */
function frImage(int $width, int $height, array $rgb = [220, 30, 30], string $type = 'jpeg'): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, imagecolorallocate($image, ...$rgb));

    ob_start();
    $type === 'png' ? imagepng($image) : imagejpeg($image, null, 90);

    return ob_get_clean();
}

/** The image embedded in the receipt under `$alt`, as [mime type, decoded image]. */
function frEmbedded(string $html, string $alt): ?array
{
    $pattern = '/<img src="data:(image\/[a-z]+);base64,([^"]+)" alt="' . preg_quote(e($alt), '/') . '"/';

    if (! preg_match($pattern, $html, $m)) {
        return null;
    }

    return [$m[1], imagecreatefromstring(base64_decode($m[2]))];
}

function frRgb(GdImage $image, int $x, int $y): array
{
    $c = imagecolorat($image, $x, $y);

    return [($c >> 16) & 0xFF, ($c >> 8) & 0xFF, $c & 0xFF];
}

it('prints the Saint Columban College crest', function () {
    [$club, $officer, $member, $fee] = frSetup();
    frMarkPaid($officer, $fee, $member);

    [$mime, $crest] = frEmbedded(frReceiptHtml($club, $fee), 'Saint Columban College');

    // Inline, because dompdf does not load images by URL.
    expect($mime)->toBe('image/png')
        ->and(imagesx($crest))->toBe(200);
});

it('prints the club logo the DSA uploaded, scaled down and not cropped', function (Closure $url) {
    Storage::fake('public');
    Storage::disk('public')->put('clubs/logos/logo.jpg', frImage(800, 400));

    [$club, $officer, $member, $fee] = frSetup();
    $club->update(['profile_photo_url' => $url()]);
    frMarkPaid($officer, $fee, $member);

    $html = frReceiptHtml($club, $fee);
    [$mime, $logo] = frEmbedded($html, $club->name);

    // A wide logo fits inside the square whole: red across the middle, white above
    // and below it, never stretched or cut.
    expect($mime)->toBe('image/jpeg')
        ->and([imagesx($logo), imagesy($logo)])->toBe([200, 200])
        ->and(frRgb($logo, 100, 100)[0])->toBeGreaterThan(180)
        ->and(frRgb($logo, 100, 100)[1])->toBeLessThan(80)
        ->and(frRgb($logo, 100, 10))->each->toBeGreaterThan(240)
        ->and($html)->not->toContain('class="initials"');
})->with([
    'as stored by the DSA upload' => fn () => '/storage/clubs/logos/logo.jpg',
    'as the public disk full URL' => fn () => config('filesystems.disks.public.url') . '/clubs/logos/logo.jpg',
]);

it('prints a club logo kept on another host, fetching it once', function () {
    Http::preventStrayRequests();
    Http::fake(['storage.googleapis.com/*' => Http::response(frImage(300, 300, [0, 0, 200], 'png'))]);

    [$club, $officer, $member, $fee] = frSetup();
    $club->update(['profile_photo_url' => 'https://storage.googleapis.com/bucket/club_images/logo.png']);
    frMarkPaid($officer, $fee, $member);

    frReceiptHtml($club, $fee);
    [$mime, $logo] = frEmbedded(frReceiptHtml($club, $fee), $club->name);

    expect($mime)->toBe('image/png')
        ->and(frRgb($logo, 100, 100)[2])->toBeGreaterThan(180);

    // A treasurer printing a receipt per member does not fetch the logo per receipt.
    Http::assertSentCount(1);
});

it('prints the club initials when its logo cannot be shown', function (?string $url) {
    Http::preventStrayRequests();
    Http::fake(['storage.googleapis.com/*' => Http::response('Forbidden', 403)]);
    Storage::fake('public');
    Storage::disk('public')->put('clubs/logos/notes.jpg', 'not an image');

    [$club, $officer, $member, $fee] = frSetup();
    $club->update(['profile_photo_url' => $url]);
    frMarkPaid($officer, $fee, $member);

    $html = frReceiptHtml($club, $fee);

    // The receipt still prints, with the crest, and the club is still marked.
    expect(frEmbedded($html, $club->name))->toBeNull()
        ->and(frEmbedded($html, 'Saint Columban College'))->not->toBeNull()
        ->and($html)->toMatch('/class="initials">\s*FC\s*</');

    $this->withSession(frSession($officer))
        ->get(route('officer.fees.receipt', [$fee, $member->id]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
})->with([
    'no logo'           => [null],
    'a dead link'       => ['https://storage.googleapis.com/bucket/club_images/gone.jpg'],
    'a missing file'    => ['/storage/clubs/logos/gone.jpg'],
    'not an image'      => ['/storage/clubs/logos/notes.jpg'],
    'a path outside'    => ['/storage/../../.env'],
]);

it('keeps the receipt small when the club logo is a full-size upload', function () {
    // Noise does not compress, so this is the worst a 788 px upload can be — the size
    // of the largest club logo on record. Embedded as uploaded it is over 500 KB.
    $image = imagecreatetruecolor(788, 788);
    for ($x = 0; $x < 788; $x++) {
        for ($y = 0; $y < 788; $y++) {
            imagesetpixel($image, $x, $y, mt_rand(0, 0xFFFFFF));
        }
    }
    ob_start();
    imagejpeg($image, null, 90);
    $upload = ob_get_clean();

    Storage::fake('public');
    Storage::disk('public')->put('clubs/logos/big.jpg', $upload);

    [$club, $officer, $member, $fee] = frSetup();
    $club->update(['profile_photo_url' => '/storage/clubs/logos/big.jpg']);
    frMarkPaid($officer, $fee, $member);

    $pdf = $this->withSession(frSession($officer))
        ->get(route('officer.fees.receipt', [$fee, $member->id]))
        ->assertOk()
        ->getContent();

    expect(strlen($upload))->toBeGreaterThan(500 * 1024)
        ->and(strlen($pdf))->toBeLessThan(150 * 1024);
});

it('has no receipt to give before the payment is confirmed', function () {
    [, $officer, $member, $fee] = frSetup();

    $this->withSession(frSession($officer))
        ->get(route('officer.fees.receipt', [$fee, $member->id]))
        ->assertNotFound();
});

it('stops offering the receipt once the payment is undone', function () {
    [, $officer, $member, $fee] = frSetup();

    frMarkPaid($officer, $fee, $member);
    $this->withSession(frSession($officer))->post(route('officer.fees.unpaid', [$fee, $member->id]));

    $this->withSession(frSession($officer))
        ->get(route('officer.fees.receipt', [$fee, $member->id]))
        ->assertNotFound();
});

it('refuses a receipt for another club fee', function () {
    // POLICY.md §Data Privacy — cross-club access is blocked.
    [, , $member, $fee] = frSetup();
    [, $otherOfficer]   = frSetup();

    $this->withSession(frSession($otherOfficer))
        ->get(route('officer.fees.receipt', [$fee, $member->id]))
        ->assertForbidden();
});

it('shows the number and a receipt link on the fee page', function () {
    [, $officer, $member, $fee] = frSetup();
    frMarkPaid($officer, $fee, $member);

    $number = FeePayment::first()->receipt_number;

    $this->withSession(frSession($officer))
        ->get(route('officer.fees.show', $fee))
        ->assertOk()
        ->assertSee($number)
        ->assertSee(route('officer.fees.receipt', [$fee, $member->id]), false);
});

// ── The period that lands on the receipt ────────────────────────────────────

it('offers well-formed academic periods on the fee form', function () {
    // These were built in a @php block with Blade's `{{ }}` inside PHP strings, so
    // every option read "1st Semester {{ 2026 }}-{{ 2026 + 1 }}" — and that string was
    // stored on the fee and printed on its receipt.
    [, $officer] = frSetup();

    $year = now()->year;

    $this->withSession(frSession($officer))
        ->get(route('officer.fees.create'))
        ->assertOk()
        ->assertSee('1st Semester ' . $year . '-' . ($year + 1))
        ->assertSee('Summer ' . ($year + 1))
        ->assertDontSee('Semester {{');
});

// ── The amount in words ─────────────────────────────────────────────────────

it('writes peso amounts the way a receipt writes them', function (float $amount, string $words) {
    expect(peso_in_words($amount))->toBe($words);
})->with([
    [1.00,      'one peso only'],
    [15.00,     'fifteen pesos only'],
    [42.00,     'forty-two pesos only'],
    [100.00,    'one hundred pesos only'],
    [150.50,    'one hundred fifty pesos and fifty centavos only'],
    [1250.00,   'one thousand two hundred fifty pesos only'],
    [99999.99,  'ninety-nine thousand nine hundred ninety-nine pesos and ninety-nine centavos only'],
    [0.01,      'zero pesos and one centavo only'],
]);

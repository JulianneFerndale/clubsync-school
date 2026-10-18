<?php

namespace App\Services;

use App\Models\ClubActivity;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The signatures officers, advisers and the VPA sign room booking letters with
 * (POLICY.md §Event Creation). Optional: a letter signed without one says it was signed
 * electronically.
 *
 * A signature arrives as a photo or scan of one written on paper, or drawn on the
 * profile page. Either way it is kept as a transparent PNG cropped to the ink, so it
 * sits on the signature line of a letter instead of in a white box: paper-white pixels
 * become transparent and the rest keep their colour.
 *
 * Files are on the private disk and never served by URL. They are personal data, so
 * they reach a page or a PDF only inline, inside the letter they sign. Replacing a
 * signature keeps the old file while a letter is signed with it, because what a letter
 * was signed with must not change afterwards.
 */
class SignatureStorage
{
    private const DISK = 'local';

    /** Work on at most this many pixels across: plenty for ink, and quick to scan. */
    private const WORKING_WIDTH = 1200;

    /** The stored signature fits in this box. */
    private const MAX_WIDTH = 600;
    private const MAX_HEIGHT = 220;

    /** Brighter than this (0–255) counts as paper. */
    private const PAPER_LUMINANCE = 200;

    /** A drawn signature is a small PNG; anything bigger is not one. */
    private const MAX_DRAWN_BYTES = 1024 * 1024;

    /**
     * Store `$source` as `$user`'s signature and return its path.
     *
     * @param  UploadedFile|string  $source  An uploaded image, or a data:image/png URI from the drawing pad.
     *
     * @throws ValidationException when it is not an image or holds no ink.
     */
    public function store(User $user, UploadedFile|string $source): string
    {
        $bytes = $source instanceof UploadedFile ? $source->get() : $this->drawnBytes($source);
        $png   = $this->normalise((string) $bytes);

        $path = 'signatures/' . $user->id . '/' . Str::random(32) . '.png';
        Storage::disk(self::DISK)->put($path, $png);

        $previous = $user->signature_path;
        $user->update(['signature_path' => $path]);

        $this->forget($previous);

        return $path;
    }

    /** Take the signature off `$user`'s profile. Letters already signed keep theirs. */
    public function remove(User $user): void
    {
        $previous = $user->signature_path;
        $user->update(['signature_path' => null]);

        $this->forget($previous);
    }

    /** A data: URI for a stored signature, or null when there is none to show. */
    public function dataUri(?string $path): ?string
    {
        if (! $path || ! str_starts_with($path, 'signatures/') || str_contains($path, '..')) {
            return null;
        }

        try {
            $disk = Storage::disk(self::DISK);

            return $disk->exists($path) ? 'data:image/png;base64,' . base64_encode($disk->get($path)) : null;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /** Delete a signature file no letter is signed with. */
    private function forget(?string $path): void
    {
        if (! $path) {
            return;
        }

        // letter_signatures is JSON with escaped slashes, so look for the file name, which is unique.
        $inUse = ClubActivity::query()
            ->whereNotNull('letter_signatures')
            ->whereRaw('CAST(letter_signatures AS TEXT) LIKE ?', ['%' . basename($path) . '%'])
            ->exists();

        if (! $inUse) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    private function drawnBytes(string $dataUri): string
    {
        if (! preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $dataUri, $m)) {
            throw ValidationException::withMessages(['signature' => 'The drawn signature could not be read. Please draw it again.']);
        }

        $bytes = base64_decode($m[1], true);

        if ($bytes === false || strlen($bytes) > self::MAX_DRAWN_BYTES) {
            throw ValidationException::withMessages(['signature' => 'The drawn signature could not be read. Please draw it again.']);
        }

        return $bytes;
    }

    /** Ink on a transparent background, cropped and scaled to fit MAX_WIDTH × MAX_HEIGHT. */
    private function normalise(string $bytes): string
    {
        $image = @imagecreatefromstring($bytes);

        if (! $image) {
            throw ValidationException::withMessages(['signature' => 'That file is not an image we can read. Use a JPG, PNG or WebP.']);
        }

        $image = $this->scaleTo($image, self::WORKING_WIDTH, PHP_INT_MAX);
        $w = imagesx($image);
        $h = imagesy($image);

        $ink = imagecreatetruecolor($w, $h);
        imagealphablending($ink, false);
        imagesavealpha($ink, true);
        $clear = imagecolorallocatealpha($ink, 0, 0, 0, 127);
        imagefill($ink, 0, 0, $clear);

        [$top, $left, $bottom, $right] = [$h, $w, -1, -1];

        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $rgba  = imagecolorat($image, $x, $y);
                $alpha = ($rgba >> 24) & 0x7F;
                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;

                if ($alpha > 100 || (0.299 * $r + 0.587 * $g + 0.114 * $b) > self::PAPER_LUMINANCE) {
                    continue;
                }

                imagesetpixel($ink, $x, $y, imagecolorallocatealpha($ink, $r, $g, $b, $alpha));

                $top = min($top, $y);
                $bottom = max($bottom, $y);
                $left = min($left, $x);
                $right = max($right, $x);
            }
        }

        if ($bottom < 0) {
            throw ValidationException::withMessages(['signature' => 'No signature could be found in that image. Sign in dark ink on white paper, or draw it here.']);
        }

        $pad = 6;
        $left = max(0, $left - $pad);
        $top = max(0, $top - $pad);
        $cropped = imagecrop($ink, [
            'x'      => $left,
            'y'      => $top,
            'width'  => min($w - $left, $right - $left + 1 + 2 * $pad),
            'height' => min($h - $top, $bottom - $top + 1 + 2 * $pad),
        ]);

        $final = $this->scaleTo($cropped, self::MAX_WIDTH, self::MAX_HEIGHT);

        ob_start();
        imagepng($final, null, 9);
        return (string) ob_get_clean();
    }

    /** `$image` shrunk to fit `$maxWidth` × `$maxHeight`, keeping transparency; never enlarged. */
    private function scaleTo(\GdImage $image, int $maxWidth, int $maxHeight): \GdImage
    {
        $w = imagesx($image);
        $h = imagesy($image);
        $scale = min(1, $maxWidth / $w, $maxHeight / $h);

        $canvas = imagecreatetruecolor(max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale)));
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, imagesx($canvas), imagesy($canvas), $w, $h);

        return $canvas;
    }
}

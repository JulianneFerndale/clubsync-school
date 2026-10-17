<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * An image the app shows on screen, made ready to print inside a dompdf PDF.
 *
 * dompdf runs with remote loading off (`enable_remote` = false), and should stay that
 * way: with it on, any URL in a rendered view is fetched by the server. So an image is
 * handed over inline, as a data: URI, read here from wherever the app keeps it.
 *
 * Every image is also scaled down first. Club logos are uploaded at up to 2 MB (the
 * largest on record is 750 KB, 788 px square), and a receipt issued per member should
 * not carry that to print a logo 18 mm wide.
 */
class PdfImage
{
    /** Longest side in pixels: about 280 dpi at the 18 mm a receipt prints a logo. */
    private const MAX_SIDE = 200;

    /** Anything bigger than the upload limit is not a logo. */
    private const MAX_BYTES = 5 * 1024 * 1024;

    private const REMOTE_TIMEOUT_SECONDS = 5;

    /**
     * A data: URI for the image at `$source`, or null when there is none to show.
     *
     * `$source` is written the way the app stores it: `/storage/...` or the public
     * disk's full URL for an upload, `/images/...` for a file under public/, or an
     * https:// URL (the older club logos kept on Firebase Storage). The image comes
     * back as a square, with the picture centred and nothing cropped.
     *
     * Never throws. A missing file, a dead link or something that is not an image
     * all come back as null, so the document still prints, just without it.
     *
     * `$maxSide` raises the size for a logo printed larger than a receipt's, such as
     * the club logo on a report's cover.
     */
    public static function dataUri(?string $source, int $maxSide = self::MAX_SIDE): ?string
    {
        if (! $source) {
            return null;
        }

        try {
            if (Str::startsWith($source, 'https://') && ! self::publicDiskPath($source)) {
                return self::remote($source, $maxSide);
            }

            $bytes = self::local($source);

            return $bytes === null ? null : self::shrink($bytes, $maxSide);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /** The bytes of an upload or a public/ file, or null if there is no such file. */
    private static function local(string $source): ?string
    {
        if (str_contains($source, '..')) {
            return null;
        }

        if (($path = self::publicDiskPath($source)) !== null) {
            $disk = Storage::disk('public');

            return $disk->exists($path) && $disk->size($path) <= self::MAX_BYTES ? $disk->get($path) : null;
        }

        if (Str::startsWith($source, '/') && ! Str::startsWith($source, '//')) {
            $file = public_path(ltrim($source, '/'));

            return is_file($file) && filesize($file) <= self::MAX_BYTES ? file_get_contents($file) : null;
        }

        return null;
    }

    /** The path on the public disk for an upload's URL, relative or absolute. */
    private static function publicDiskPath(string $source): ?string
    {
        $diskUrl = rtrim((string) config('filesystems.disks.public.url'), '/') . '/';

        foreach (['/storage/', $diskUrl] as $prefix) {
            if ($prefix !== '/' && Str::startsWith($source, $prefix)) {
                return Str::after($source, $prefix);
            }
        }

        return null;
    }

    /**
     * An image on another host. Kept for a day once it loads, so a treasurer printing
     * a receipt for every member does not fetch the same logo each time; a link that
     * fails is tried again after ten minutes rather than waited on per receipt.
     */
    private static function remote(string $url, int $maxSide): ?string
    {
        $key = 'pdf-image:' . sha1($url) . ($maxSide === self::MAX_SIDE ? '' : ":{$maxSide}");

        $cached = Cache::get($key);
        if ($cached !== null) {
            return $cached ?: null;
        }

        $uri = null;

        try {
            $response = Http::timeout(self::REMOTE_TIMEOUT_SECONDS)->get($url);

            if ($response->successful() && strlen($response->body()) <= self::MAX_BYTES) {
                $uri = self::shrink($response->body(), $maxSide);
            }
        } catch (Throwable) {
            // Offline or the host is gone: the document prints without the image.
        }

        Cache::put($key, $uri ?? '', $uri ? now()->addDay() : now()->addMinutes(10));

        return $uri;
    }

    /**
     * Scale the image into a MAX_SIDE square without cropping it. A JPEG stays a JPEG
     * with white filling the unused space; anything else becomes a PNG and keeps its
     * transparency.
     */
    private static function shrink(string $bytes, int $maxSide = self::MAX_SIDE): ?string
    {
        $info = @getimagesizefromstring($bytes);

        if (! $info || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true)) {
            return null;
        }

        $source = @imagecreatefromstring($bytes);
        if (! $source) {
            return null;
        }

        [$width, $height] = $info;
        $isJpeg = $info[2] === IMAGETYPE_JPEG;

        $side  = min($maxSide, max($width, $height));
        $scale = $side / max($width, $height);
        $w     = max(1, (int) round($width * $scale));
        $h     = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($side, $side);

        if ($isJpeg) {
            imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        } else {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        }

        imagecopyresampled($canvas, $source, intdiv($side - $w, 2), intdiv($side - $h, 2), 0, 0, $w, $h, $width, $height);

        ob_start();
        $isJpeg ? imagejpeg($canvas, null, 85) : imagepng($canvas, null, 9);
        $encoded = ob_get_clean();

        return 'data:' . ($isJpeg ? 'image/jpeg' : 'image/png') . ';base64,' . base64_encode($encoded);
    }
}

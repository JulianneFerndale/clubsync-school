<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * An upload anyone may view: a club's logo or banner, a profile photo.
 *
 * These live on the "public" disk, which is storage/app/public on a server with its
 * own disk and a bucket on Laravel Cloud (whose disk is wiped on every deploy). The
 * app stores each one by URL, so the URL has to come from that disk — `/storage/...`
 * locally, the bucket's address on Cloud — and be turned back into a path to delete it.
 */
class PublicUpload
{
    /** The URL the app stores for a file saved on the public disk. */
    public static function url(string $path): string
    {
        return Storage::disk('public')->url($path);
    }

    /**
     * The file's path on the public disk, or null when the URL points somewhere
     * else (an older Firebase Storage link, an image under public/).
     */
    public static function path(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $diskUrl = rtrim((string) config('filesystems.disks.public.url'), '/') . '/';

        foreach (['/storage/', $diskUrl] as $prefix) {
            if ($prefix !== '/' && Str::startsWith($url, $prefix)) {
                return Str::after($url, $prefix);
            }
        }

        return null;
    }

    /** Removes the file behind a stored URL, if it is one of ours. */
    public static function delete(?string $url): void
    {
        if (($path = self::path($url)) !== null) {
            Storage::disk('public')->delete($path);
        }
    }
}

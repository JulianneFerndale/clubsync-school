<?php

namespace App\Services;

use App\Models\ActivityPhoto;
use App\Models\ClubActivity;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Files officers' report photos in the reporting folder and records them.
 *
 * Every upload is re-encoded as a JPEG no larger than MAX_SIDE on its longest side
 * before it is kept. That does three things the documents need: a phone photo of
 * several megabytes becomes a few hundred kilobytes, so a report with dozens of them
 * still downloads; Word and dompdf both read the result, whatever format came in
 * (WebP included); and the camera's metadata, GPS position included, is dropped —
 * these photos go to the DSA, so they carry the picture and nothing else (RA 10173).
 */
class ActivityPhotoStorage
{
    public const DISK = 'local';

    /** The reporting folder; one sub-folder per club, then per activity. */
    public const ROOT = 'report-photos';

    /** About 190 dpi across a long bond page's 8.5 in — sharp in print, small on disk. */
    private const MAX_SIDE = 1600;

    private const JPEG_QUALITY = 82;

    /** @param  array{category: string, caption?: ?string, amount?: mixed, reference_number?: ?string, quantity?: mixed, remarks?: ?string}  $details */
    public function store(UploadedFile $file, ClubActivity $activity, array $details, int $uploadedBy): ActivityPhoto
    {
        [$bytes, $width, $height] = $this->normalise($file);

        $path = self::ROOT . "/{$activity->club_id}/{$activity->id}/" . Str::uuid() . '.jpg';

        if (! Storage::disk(self::DISK)->put($path, $bytes)) {
            throw new RuntimeException('The photo could not be saved.');
        }

        return ActivityPhoto::create([
            'club_id'          => $activity->club_id,
            'club_activity_id' => $activity->id,
            'category'         => $details['category'],
            'caption'          => $details['caption'] ?? null,
            'amount'           => $details['amount'] ?? null,
            'reference_number' => $details['reference_number'] ?? null,
            'quantity'         => $details['quantity'] ?? null,
            'remarks'          => $details['remarks'] ?? null,
            'path'             => $path,
            'original_name'    => Str::limit($file->getClientOriginalName(), 250, ''),
            'size'             => strlen($bytes),
            'width'            => $width,
            'height'           => $height,
            'uploaded_by'      => $uploadedBy,
        ]);
    }

    public function delete(ActivityPhoto $photo): void
    {
        $photo->delete();
        Storage::disk(self::DISK)->delete($photo->path);
    }

    public static function contents(ActivityPhoto $photo): ?string
    {
        $disk = Storage::disk(self::DISK);

        return $disk->exists($photo->path) ? $disk->get($photo->path) : null;
    }

    /**
     * The upload as a JPEG within MAX_SIDE, turned upright, with nothing but the picture.
     *
     * @return array{0: string, 1: int, 2: int}
     */
    private function normalise(UploadedFile $file): array
    {
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));

        if (! $source) {
            throw new RuntimeException('The file is not an image that can be read.');
        }

        return self::jpeg($this->upright($source, $file));
    }

    /**
     * Any image the documents print that did not come through store() (an approval
     * letter uploaded as a picture), brought to the same size and format.
     *
     * @return array{0: string, 1: int, 2: int}|null
     */
    public static function jpegFromBytes(string $bytes): ?array
    {
        $source = @imagecreatefromstring($bytes);

        return $source ? self::jpeg($source) : null;
    }

    /** @return array{0: string, 1: int, 2: int} */
    private static function jpeg(\GdImage $source): array
    {
        $width  = imagesx($source);
        $height = imagesy($source);
        $scale  = min(1, self::MAX_SIDE / max($width, $height));
        $w      = max(1, (int) round($width * $scale));
        $h      = max(1, (int) round($height * $scale));

        // White underneath, so a transparent PNG prints on white rather than black.
        $canvas = imagecreatetruecolor($w, $h);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $w, $h, $width, $height);

        ob_start();
        imagejpeg($canvas, null, self::JPEG_QUALITY);

        return [(string) ob_get_clean(), $w, $h];
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
}

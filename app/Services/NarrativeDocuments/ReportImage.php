<?php

namespace App\Services\NarrativeDocuments;

use Closure;

/**
 * An image a report document prints: its type, its pixel size (for fitting it to the
 * page) and its bytes, read only when a writer actually embeds it.
 */
final class ReportImage
{
    private ?string $bytes = null;

    public function __construct(
        public readonly string $mime,
        public readonly int $width,
        public readonly int $height,
        private readonly Closure $loader,
    ) {}

    public static function fromBytes(string $bytes): ?self
    {
        $info = @getimagesizefromstring($bytes);

        if (! $info || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
            return null;
        }

        return new self($info['mime'], $info[0], $info[1], fn () => $bytes);
    }

    public static function fromFile(string $path): ?self
    {
        return is_file($path) ? self::fromBytes((string) file_get_contents($path)) : null;
    }

    /** A data: URI from PdfImage, as the club logo comes back. */
    public static function fromDataUri(?string $uri): ?self
    {
        if (! $uri || ! preg_match('#^data:image/(?:png|jpeg);base64,(.+)$#', $uri, $m)) {
            return null;
        }

        return self::fromBytes((string) base64_decode($m[1]));
    }

    public function bytes(): string
    {
        return $this->bytes ??= (string) ($this->loader)();
    }

    public function extension(): string
    {
        return $this->mime === 'image/png' ? 'png' : 'jpg';
    }

    /**
     * The printed size, in points, of the image fitted inside a box without
     * changing its proportions.
     *
     * @return array{0: float, 1: float}
     */
    public function fit(float $maxWidth, float $maxHeight): array
    {
        $scale = min($maxWidth / max(1, $this->width), $maxHeight / max(1, $this->height));

        return [round($this->width * $scale, 2), round($this->height * $scale, 2)];
    }
}

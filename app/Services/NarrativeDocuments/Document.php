<?php

namespace App\Services\NarrativeDocuments;

/**
 * One of the five report documents, as a list of blocks both writers understand.
 *
 * The content is decided once, here, and DocxWriter and PdfWriter only lay it out,
 * so the Word file and the PDF of a document always say the same thing.
 *
 * Block types (all sizes in points):
 *  - cover:     title, term, year, logos (ReportImage[]), club, club_logo, lines, date
 *  - paragraph: runs [{text, bold?, italic?, underline?, sup?}], align, size, bold, italic,
 *               space_before, space_after, indent (first-line, for prose), bullet
 *  - table:     columns [{label, width (fraction), align}], rows [[cells]], total [cells]|null,
 *               empty (text shown in one row when there are no rows), size
 *  - photos:    photos [{image: ReportImage, caption}], columns (1|2), max_height, empty
 *  - pagebreak
 */
final class Document
{
    /** @param  list<array>  $blocks */
    public function __construct(
        public readonly string $key,
        public readonly string $title,
        public readonly bool $landscape,
        public readonly array $blocks,
    ) {}

    /**
     * The page, as the DSA templates set it up: long bond (8.5 x 14 in) portrait, or
     * letter landscape for the property inventory. The SCC banner runs across the top
     * of every page, so the top margin starts the text just below it.
     *
     * @return array{width: float, height: float, top: float, right: float, bottom: float, left: float, banner: string, banner_height: float}
     */
    public function page(): array
    {
        return $this->landscape
            ? ['width' => 792.0, 'height' => 612.0, 'top' => 140.0, 'right' => 72.0, 'bottom' => 54.0, 'left' => 72.0,
               'banner' => 'scc-banner-landscape.jpg', 'banner_height' => round(792 * 375 / 2441, 2)]
            : ['width' => 612.0, 'height' => 1008.0, 'top' => 108.0, 'right' => 72.0, 'bottom' => 72.0, 'left' => 72.0,
               'banner' => 'scc-banner-portrait.jpg', 'banner_height' => round(612 * 263 / 1882, 2)];
    }

    /** Text width and height inside the margins, in points. */
    public function contentSize(): array
    {
        $page = $this->page();

        return [$page['width'] - $page['left'] - $page['right'], $page['height'] - $page['top'] - $page['bottom']];
    }

    /** The same content laid out on a portrait page, for the combined PDF. */
    public function inPortrait(): self
    {
        return new self($this->key, $this->title, false, $this->blocks);
    }
}

<?php

namespace App\Services\NarrativeDocuments;

use RuntimeException;
use ZipArchive;

/**
 * Writes a Document as a Word file (.docx) the officers and the DSA can edit.
 *
 * The package is assembled directly — WordprocessingML for the text and tables,
 * DrawingML for the pictures, the same markup the DSA templates use — so it opens
 * the same in Word, Google Docs and LibreOffice. The SCC banner sits in the page
 * header, behind the text and flush with the top of the page, so it repeats on every
 * page however long the document runs, the way the templates place it page by page.
 */
class DocxWriter
{
    private const EMU_PER_POINT = 12700;
    private const TWIPS_PER_POINT = 20;

    /** @var array<string, array{id: string, file: string}> Embedded images by content hash. */
    private array $media = [];

    private int $drawingId = 0;

    private bool $breakPending = false;

    private Document $document;

    public function write(Document $document): string
    {
        $this->document     = $document;
        $this->media        = [];
        $this->drawingId    = 0;
        $this->breakPending = false;

        $page   = $document->page();
        $banner = DocumentBuilder::brand($page['banner']);

        $body = '';
        foreach ($document->blocks as $block) {
            $body .= $this->block($block);
        }

        $documentMedia = $this->media;

        // The header is its own part with its own relationships.
        $this->media = [];
        $header      = $this->header($banner, $page);
        $headerMedia = $this->media;

        $path = tempnam(sys_get_temp_dir(), 'docx');
        $zip  = new ZipArchive();

        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the Word document.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->packageRels());
        $zip->addFromString('docProps/core.xml', $this->coreProps());
        $zip->addFromString('docProps/app.xml', $this->appProps());
        $zip->addFromString('word/document.xml', $this->documentXml($body, $page));
        $zip->addFromString('word/styles.xml', $this->styles());
        $zip->addFromString('word/settings.xml', $this->settings());
        $zip->addFromString('word/header1.xml', $header);
        $zip->addFromString('word/_rels/document.xml.rels', $this->rels($documentMedia, [
            ['rIdStyles', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles', 'styles.xml'],
            ['rIdSettings', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings', 'settings.xml'],
            ['rIdHeader', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/header', 'header1.xml'],
        ]));
        $zip->addFromString('word/_rels/header1.xml.rels', $this->rels($headerMedia, []));

        foreach ([...array_values($documentMedia), ...array_values($headerMedia)] as $entry) {
            $zip->addFromString('word/media/' . $entry['file'], $entry['bytes']);
        }

        $zip->close();

        $bytes = (string) file_get_contents($path);
        @unlink($path);

        return $bytes;
    }

    // ── Blocks ───────────────────────────────────────────────────────────────

    private function block(array $block): string
    {
        return match ($block['type']) {
            'pagebreak' => $this->pageBreak(),
            'paragraph' => $this->paragraph($block),
            'table'     => $this->table($block),
            'photos'    => $this->photos($block),
            'cover'     => $this->cover($block),
            default     => '',
        };
    }

    private function pageBreak(): string
    {
        $this->breakPending = true;

        return '';
    }

    /** A table cannot carry "page break before", so it gets a breaking paragraph of its own. */
    private function flushBreak(): string
    {
        if (! $this->breakPending) {
            return '';
        }

        $this->breakPending = false;

        return '<w:p><w:pPr><w:spacing w:before="0" w:after="0"/></w:pPr><w:r><w:br w:type="page"/></w:r></w:p>';
    }

    private function paragraph(array $block, string $extraRuns = ''): string
    {
        $size   = (float) ($block['size'] ?? 12);
        $before = (int) round(($block['space_before'] ?? 0) * self::TWIPS_PER_POINT);
        $after  = (int) round(($block['space_after'] ?? 6) * self::TWIPS_PER_POINT);
        $line   = (int) round(240 * ($block['line'] ?? 1.0));

        $ppr = '';
        if ($this->breakPending) {
            $ppr .= '<w:pageBreakBefore/>';
            $this->breakPending = false;
        }
        $ppr .= "<w:spacing w:before=\"{$before}\" w:after=\"{$after}\" w:line=\"{$line}\" w:lineRule=\"auto\"/>";

        if (! empty($block['bullet'])) {
            $ppr .= '<w:ind w:left="540" w:hanging="300"/>';
        } elseif (! empty($block['indent'])) {
            $ppr .= '<w:ind w:firstLine="720"/>';
        }

        $ppr .= '<w:jc w:val="' . match ($block['align'] ?? 'left') {
            'center'  => 'center',
            'right'   => 'right',
            'justify' => 'both',
            default   => 'left',
        } . '"/>';

        $runs = ! empty($block['bullet']) ? $this->run(['text' => "•\t"], $size) : '';
        foreach ($block['runs'] ?? [] as $run) {
            $runs .= $this->run($run + [
                'bold'   => $block['bold'] ?? false,
                'italic' => $block['italic'] ?? false,
            ], $size);
        }

        return "<w:p><w:pPr>{$ppr}</w:pPr>{$runs}{$extraRuns}</w:p>";
    }

    private function run(array $run, float $size): string
    {
        $halfPoints = (int) round($size * 2);

        $rpr = (! empty($run['bold']) ? '<w:b/><w:bCs/>' : '')
            . (! empty($run['italic']) ? '<w:i/><w:iCs/>' : '')
            . "<w:sz w:val=\"{$halfPoints}\"/><w:szCs w:val=\"{$halfPoints}\"/>"
            . (! empty($run['underline']) ? '<w:u w:val="single"/>' : '')
            . (! empty($run['sup']) ? '<w:vertAlign w:val="superscript"/>' : '');

        $xml = '';
        foreach (explode("\t", (string) ($run['text'] ?? '')) as $i => $piece) {
            if ($i > 0) {
                $xml .= '<w:tab/>';
            }
            if ($piece !== '') {
                $xml .= '<w:t xml:space="preserve">' . self::escape($piece) . '</w:t>';
            }
        }

        return "<w:r><w:rPr>{$rpr}</w:rPr>{$xml}</w:r>";
    }

    private function table(array $block): string
    {
        [$width] = $this->document->contentSize();

        $total   = (int) round($width * self::TWIPS_PER_POINT);
        $columns = $block['columns'];
        $widths  = array_map(fn ($c) => (int) floor($total * $c['width']), $columns);
        $widths[count($widths) - 1] += $total - array_sum($widths);
        $size    = (float) ($block['size'] ?? 12);

        $xml = $this->flushBreak()
            . '<w:tbl><w:tblPr>'
            . "<w:tblW w:w=\"{$total}\" w:type=\"dxa\"/><w:jc w:val=\"center\"/>"
            . '<w:tblBorders>'
            . collect(['top', 'left', 'bottom', 'right', 'insideH', 'insideV'])->map(fn ($side) => "<w:{$side} w:val=\"single\" w:sz=\"6\" w:space=\"0\" w:color=\"000000\"/>")->implode('')
            . '</w:tblBorders><w:tblLayout w:type="fixed"/>'
            . '<w:tblCellMar><w:top w:w="70" w:type="dxa"/><w:left w:w="110" w:type="dxa"/><w:bottom w:w="70" w:type="dxa"/><w:right w:w="110" w:type="dxa"/></w:tblCellMar>'
            . '<w:tblLook w:val="0420" w:firstRow="1" w:lastRow="0" w:firstColumn="0" w:lastColumn="0" w:noHBand="1" w:noVBand="1"/>'
            . '</w:tblPr><w:tblGrid>'
            . implode('', array_map(fn ($w) => "<w:gridCol w:w=\"{$w}\"/>", $widths))
            . '</w:tblGrid>';

        $xml .= $this->row(array_column($columns, 'label'), $columns, $widths, $size, header: true);

        if ($block['rows'] === []) {
            $xml .= '<w:tr><w:tc><w:tcPr><w:tcW w:w="' . $total . '" w:type="dxa"/><w:gridSpan w:val="' . count($columns) . '"/></w:tcPr>'
                . $this->paragraph(['runs' => [['text' => $block['empty'], 'italic' => true]], 'size' => $size, 'align' => 'center', 'space_after' => 0])
                . '</w:tc></w:tr>';
        }

        foreach ($block['rows'] as $cells) {
            $xml .= $this->row($cells, $columns, $widths, $size);
        }

        if ($block['total'] ?? null) {
            $xml .= $this->row($block['total'], $columns, $widths, $size, bold: true);
        }

        // Word needs a paragraph between a table and whatever follows it.
        return $xml . '</w:tbl><w:p><w:pPr><w:spacing w:before="0" w:after="120"/></w:pPr></w:p>';
    }

    private function row(array $cells, array $columns, array $widths, float $size, bool $header = false, bool $bold = false): string
    {
        $xml = '<w:tr>' . ($header ? '<w:trPr><w:tblHeader/></w:trPr>' : '');

        foreach ($columns as $i => $column) {
            $xml .= '<w:tc><w:tcPr><w:tcW w:w="' . $widths[$i] . '" w:type="dxa"/>'
                . ($header ? '<w:shd w:val="clear" w:color="auto" w:fill="E3F1E6"/>' : '')
                . '<w:vAlign w:val="center"/></w:tcPr>'
                . $this->paragraph([
                    'runs'        => [['text' => (string) ($cells[$i] ?? ''), 'bold' => $header || $bold]],
                    'size'        => $size,
                    'align'       => $header ? 'center' : ($column['align'] ?? 'left'),
                    'space_after' => 0,
                ])
                . '</w:tc>';
        }

        return $xml . '</w:tr>';
    }

    private function photos(array $block): string
    {
        $photos = array_values(array_filter($block['photos'], fn ($p) => $p['image'] instanceof ReportImage && $p['image']->bytes() !== ''));

        if ($photos === []) {
            return $block['empty'] === ''
                ? ''
                : $this->paragraph(['runs' => [['text' => $block['empty'], 'italic' => true]], 'size' => 12, 'align' => 'center', 'space_before' => 24]);
        }

        [$width] = $this->document->contentSize();

        if ((int) $block['columns'] === 1) {
            $xml = '';
            foreach ($photos as $photo) {
                [$w, $h] = $photo['image']->fit($width, (float) $block['max_height']);
                $xml .= $this->paragraph(['runs' => [], 'align' => 'center', 'space_after' => 4, 'space_before' => 6], $this->inlineImage($photo['image'], $w, $h));
                if ($photo['caption'] !== '') {
                    $xml .= $this->paragraph(['runs' => [['text' => $photo['caption'], 'italic' => true]], 'size' => 10, 'align' => 'center', 'space_after' => 12]);
                }
            }

            return $xml;
        }

        // Two to a row, in a borderless table so each caption stays under its photo.
        $total = (int) round($width * self::TWIPS_PER_POINT);
        $cell  = intdiv($total, 2);
        $xml   = $this->flushBreak()
            . "<w:tbl><w:tblPr><w:tblW w:w=\"{$total}\" w:type=\"dxa\"/><w:jc w:val=\"center\"/><w:tblLayout w:type=\"fixed\"/>"
            . '<w:tblCellMar><w:top w:w="80" w:type="dxa"/><w:left w:w="80" w:type="dxa"/><w:bottom w:w="80" w:type="dxa"/><w:right w:w="80" w:type="dxa"/></w:tblCellMar>'
            . "</w:tblPr><w:tblGrid><w:gridCol w:w=\"{$cell}\"/><w:gridCol w:w=\"{$cell}\"/></w:tblGrid>";

        foreach (array_chunk($photos, 2) as $pair) {
            $xml .= '<w:tr><w:trPr><w:cantSplit/></w:trPr>';
            for ($i = 0; $i < 2; $i++) {
                $xml .= "<w:tc><w:tcPr><w:tcW w:w=\"{$cell}\" w:type=\"dxa\"/></w:tcPr>";
                if (isset($pair[$i])) {
                    [$w, $h] = $pair[$i]['image']->fit($cell / self::TWIPS_PER_POINT - 10, (float) $block['max_height']);
                    $xml .= $this->paragraph(['runs' => [], 'align' => 'center', 'space_after' => 2], $this->inlineImage($pair[$i]['image'], $w, $h));
                    $xml .= $this->paragraph(['runs' => [['text' => $pair[$i]['caption'], 'italic' => true]], 'size' => 10, 'align' => 'center', 'space_after' => 6]);
                } else {
                    $xml .= '<w:p/>';
                }
                $xml .= '</w:tc>';
            }
            $xml .= '</w:tr>';
        }

        return $xml . '</w:tbl><w:p><w:pPr><w:spacing w:before="0" w:after="120"/></w:pPr></w:p>';
    }

    private function cover(array $block): string
    {
        $landscape = $this->document->landscape;
        $big       = $landscape ? 24 : 28;

        $xml = $this->paragraph(['runs' => [['text' => $block['title'], 'bold' => true]], 'size' => $big, 'align' => 'center', 'space_before' => $landscape ? 6 : 60, 'space_after' => $landscape ? 10 : 40]);
        $xml .= $this->paragraph(['runs' => array_map(fn ($r) => $r + ['bold' => true], $block['term']), 'size' => $big - 2, 'align' => 'center', 'space_after' => 4]);

        if ($block['year']) {
            $xml .= $this->paragraph(['runs' => [['text' => $block['year'], 'bold' => true]], 'size' => $big - 2, 'align' => 'center', 'space_after' => $landscape ? 14 : 40]);
        }

        // GSC and SCC side by side; on the landscape cover the club logo joins the row.
        $logos = $block['logos'];
        if ($landscape && $block['club_logo']) {
            $logos[] = $block['club_logo'];
        }

        $box   = $landscape ? 120.0 : 175.0;
        $gap   = $this->run(['text' => str_repeat(' ', $landscape ? 10 : 14)], 12);
        $runs  = [];
        foreach ($logos as $logo) {
            [$w, $h] = $logo->fit($box, $box);
            $runs[]  = $this->inlineImage($logo, $w, $h);
        }
        $xml .= $this->paragraph(['runs' => [], 'align' => 'center', 'space_after' => $landscape ? 10 : 50], implode($gap, $runs));

        if (! $landscape) {
            if ($block['club_logo']) {
                [$w, $h] = $block['club_logo']->fit(130, 130);
                $xml .= $this->paragraph(['runs' => [], 'align' => 'center', 'space_after' => 6], $this->inlineImage($block['club_logo'], $w, $h));
            }
        }

        $xml .= $this->paragraph(['runs' => [['text' => $block['club'], 'bold' => true]], 'size' => $landscape ? 15 : 17, 'align' => 'center', 'space_after' => 24]);

        foreach ($block['lines'] as $runs) {
            $xml .= $this->paragraph(['runs' => $runs, 'size' => 14, 'align' => 'center', 'space_after' => 2]);
        }

        return $xml . $this->paragraph(['runs' => [['text' => $block['date'], 'bold' => true]], 'size' => 14, 'align' => 'center', 'space_before' => $landscape ? 10 : 50]);
    }

    // ── Pictures ─────────────────────────────────────────────────────────────

    private function embed(ReportImage $image): string
    {
        $bytes = $image->bytes();
        $hash  = sha1($bytes);

        if (! isset($this->media[$hash])) {
            $n                  = count($this->media) + 1;
            $this->media[$hash] = ['id' => "rIdImg{$n}", 'file' => 'image' . substr($hash, 0, 12) . '.' . $image->extension(), 'bytes' => $bytes];
        }

        return $this->media[$hash]['id'];
    }

    private function inlineImage(ReportImage $image, float $width, float $height): string
    {
        $rid = $this->embed($image);
        $id  = ++$this->drawingId;
        $cx  = (int) round($width * self::EMU_PER_POINT);
        $cy  = (int) round($height * self::EMU_PER_POINT);

        return '<w:r><w:drawing><wp:inline distT="0" distB="0" distL="0" distR="0">'
            . "<wp:extent cx=\"{$cx}\" cy=\"{$cy}\"/><wp:effectExtent l=\"0\" t=\"0\" r=\"0\" b=\"0\"/>"
            . "<wp:docPr id=\"{$id}\" name=\"Picture {$id}\"/>"
            . '<wp:cNvGraphicFramePr><a:graphicFrameLocks noChangeAspect="1"/></wp:cNvGraphicFramePr>'
            . $this->graphic($rid, $id, $cx, $cy)
            . '</wp:inline></w:drawing></w:r>';
    }

    private function graphic(string $rid, int $id, int $cx, int $cy): string
    {
        return '<a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture"><pic:pic>'
            . "<pic:nvPicPr><pic:cNvPr id=\"{$id}\" name=\"Picture {$id}\"/><pic:cNvPicPr><a:picLocks noChangeAspect=\"1\"/></pic:cNvPicPr></pic:nvPicPr>"
            . "<pic:blipFill><a:blip r:embed=\"{$rid}\"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>"
            . "<pic:spPr><a:xfrm><a:off x=\"0\" y=\"0\"/><a:ext cx=\"{$cx}\" cy=\"{$cy}\"/></a:xfrm><a:prstGeom prst=\"rect\"><a:avLst/></a:prstGeom></pic:spPr>"
            . '</pic:pic></a:graphicData></a:graphic>';
    }

    // ── Package parts ────────────────────────────────────────────────────────

    private function header(?ReportImage $banner, array $page): string
    {
        $drawing = '';

        if ($banner) {
            $rid = $this->embed($banner);
            $cx  = (int) round($page['width'] * self::EMU_PER_POINT);
            $cy  = (int) round($page['banner_height'] * self::EMU_PER_POINT);

            $drawing = '<w:r><w:drawing><wp:anchor distT="0" distB="0" distL="0" distR="0" simplePos="0" relativeHeight="251658240" behindDoc="1" locked="1" layoutInCell="1" allowOverlap="1">'
                . '<wp:simplePos x="0" y="0"/>'
                . '<wp:positionH relativeFrom="page"><wp:posOffset>0</wp:posOffset></wp:positionH>'
                . '<wp:positionV relativeFrom="page"><wp:posOffset>0</wp:posOffset></wp:positionV>'
                . "<wp:extent cx=\"{$cx}\" cy=\"{$cy}\"/><wp:effectExtent l=\"0\" t=\"0\" r=\"0\" b=\"0\"/><wp:wrapNone/>"
                . '<wp:docPr id="9999" name="Saint Columban College banner"/><wp:cNvGraphicFramePr/>'
                . $this->graphic($rid, 9999, $cx, $cy)
                . '</wp:anchor></w:drawing></w:r>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:hdr ' . self::NAMESPACES . '><w:p><w:pPr><w:spacing w:before="0" w:after="0"/></w:pPr>' . $drawing . '</w:p></w:hdr>';
    }

    private function documentXml(string $body, array $page): string
    {
        $t = fn (float $points) => (int) round($points * self::TWIPS_PER_POINT);

        $sect = '<w:sectPr><w:headerReference w:type="default" r:id="rIdHeader"/>'
            . '<w:pgSz w:w="' . $t($page['width']) . '" w:h="' . $t($page['height']) . '"' . ($this->document->landscape ? ' w:orient="landscape"' : '') . '/>'
            . '<w:pgMar w:top="' . $t($page['top']) . '" w:right="' . $t($page['right']) . '" w:bottom="' . $t($page['bottom']) . '" w:left="' . $t($page['left']) . '" w:header="0" w:footer="567" w:gutter="0"/>'
            . '<w:cols w:space="720"/><w:docGrid w:linePitch="360"/></w:sectPr>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document ' . self::NAMESPACES . '><w:body>' . $body . $sect . '</w:body></w:document>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Arial" w:hAnsi="Arial" w:eastAsia="Arial" w:cs="Arial"/>'
            . '<w:sz w:val="24"/><w:szCs w:val="24"/><w:lang w:val="en-PH" w:eastAsia="en-US" w:bidi="ar-SA"/></w:rPr></w:rPrDefault>'
            . '<w:pPrDefault><w:pPr><w:spacing w:after="120" w:line="240" w:lineRule="auto"/></w:pPr></w:pPrDefault></w:docDefaults>'
            . '<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/><w:qFormat/></w:style>'
            . '<w:style w:type="table" w:default="1" w:styleId="TableNormal"><w:name w:val="Normal Table"/><w:uiPriority w:val="99"/><w:semiHidden/>'
            . '<w:tblPr><w:tblInd w:w="0" w:type="dxa"/><w:tblCellMar><w:top w:w="0" w:type="dxa"/><w:left w:w="108" w:type="dxa"/>'
            . '<w:bottom w:w="0" w:type="dxa"/><w:right w:w="108" w:type="dxa"/></w:tblCellMar></w:tblPr></w:style>'
            . '</w:styles>';
    }

    private function settings(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:settings xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:defaultTabStop w:val="720"/><w:characterSpacingControl w:val="doNotCompress"/>'
            . '<w:compat><w:compatSetting w:name="compatibilityMode" w:uri="http://schemas.microsoft.com/office/word" w:val="15"/></w:compat>'
            . '</w:settings>';
    }

    private function rels(array $media, array $fixed): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';

        foreach ($fixed as [$id, $type, $target]) {
            $xml .= "<Relationship Id=\"{$id}\" Type=\"{$type}\" Target=\"{$target}\"/>";
        }

        foreach ($media as $entry) {
            $xml .= "<Relationship Id=\"{$entry['id']}\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/image\" Target=\"media/{$entry['file']}\"/>";
        }

        return $xml . '</Relationships>';
    }

    private function contentTypes(): string
    {
        $main = 'application/vnd.openxmlformats-officedocument.wordprocessingml';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Default Extension="jpg" ContentType="image/jpeg"/>'
            . '<Default Extension="png" ContentType="image/png"/>'
            . "<Override PartName=\"/word/document.xml\" ContentType=\"{$main}.document.main+xml\"/>"
            . "<Override PartName=\"/word/styles.xml\" ContentType=\"{$main}.styles+xml\"/>"
            . "<Override PartName=\"/word/settings.xml\" ContentType=\"{$main}.settings+xml\"/>"
            . "<Override PartName=\"/word/header1.xml\" ContentType=\"{$main}.header+xml\"/>"
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            . '</Types>';
    }

    private function packageRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            . '</Relationships>';
    }

    private function coreProps(): string
    {
        $now = now()->utc()->format('Y-m-d\TH:i:s\Z');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:title>' . self::escape($this->document->title) . '</dc:title><dc:creator>ClubSync</dc:creator>'
            . "<dcterms:created xsi:type=\"dcterms:W3CDTF\">{$now}</dcterms:created><dcterms:modified xsi:type=\"dcterms:W3CDTF\">{$now}</dcterms:modified>"
            . '</cp:coreProperties>';
    }

    private function appProps(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"><Application>ClubSync</Application></Properties>';
    }

    private const NAMESPACES = 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" '
        . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" '
        . 'xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" '
        . 'xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" '
        . 'xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"';

    /** Text as XML character data, without the control characters XML 1.0 forbids. */
    private static function escape(string $text): string
    {
        $text = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $text) ?? '';

        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}

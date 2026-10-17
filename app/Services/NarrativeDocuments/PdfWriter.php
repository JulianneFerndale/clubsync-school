<?php

namespace App\Services\NarrativeDocuments;

use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Writes Documents as a PDF with dompdf, block for block what DocxWriter puts in the
 * Word file. Pictures go in as data: URIs (remote loading stays off, see PdfImage),
 * and the text is set in Helvetica, a PDF core font, so nothing is embedded but the
 * pictures themselves.
 *
 * dompdf gives a file one page size, so several documents written into one PDF (the
 * complete report) all take the portrait long bond page.
 */
class PdfWriter
{
    /** @param  Document|list<Document>  $documents */
    public function write(Document|array $documents): string
    {
        $documents = is_array($documents) ? $documents : [$documents];

        if (count($documents) > 1) {
            $documents = array_map(fn (Document $document) => $document->inPortrait(), $documents);
        }

        $page = $documents[0]->page();

        return Pdf::loadView('reports.narrative-document-pdf', [
            'documents' => $documents,
            'page'      => $page,
            'banner'    => DocumentBuilder::brand($page['banner']),
        ])->setPaper([0, 0, $page['width'], $page['height']])->output();
    }

    /** The image as a data: URI for an <img> in the view. */
    public static function src(ReportImage $image): string
    {
        return 'data:' . $image->mime . ';base64,' . base64_encode($image->bytes());
    }
}

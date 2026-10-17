<?php

namespace App\Services\NarrativeDocuments;

use App\Models\SemesterNarrativeReport;
use Closure;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * The files of a narrative report: each of the five documents as Word (.docx) or PDF,
 * the complete report as one PDF, and all five Word files in one ZIP.
 *
 * While the adviser is still reviewing, every download is rendered fresh, so a
 * preview always shows the current edits and attachments. Once the report has been
 * submitted, what the adviser approved is fixed: the Word files are written at
 * submission and every file is kept after it is first rendered, so the DSA and the
 * adviser download the same bytes every time.
 */
class ReportDocuments
{
    public const FORMATS = ['docx', 'pdf'];

    private const MIME = [
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'pdf'  => 'application/pdf',
        'zip'  => 'application/zip',
    ];

    public function __construct(private DocxWriter $docx, private PdfWriter $pdf) {}

    /** Whether a download names one of the five documents in a format they come in. */
    public static function exists(string $document, string $format): bool
    {
        return isset(DocumentBuilder::DOCUMENTS[$document]) && in_array($format, self::FORMATS, true);
    }

    /** @return array{bytes: string, name: string, mime: string} */
    public function document(SemesterNarrativeReport $report, string $key, string $format): array
    {
        $bytes = $this->kept($report, "{$key}.{$format}", function () use ($report, $key, $format) {
            $document = (new DocumentBuilder($report))->build($key);

            return $format === 'docx' ? $this->docx->write($document) : $this->pdf->write($document);
        });

        return $this->file($bytes, self::fileName($report, DocumentBuilder::label($key), $format), $format);
    }

    /** All five documents, one after another, as a single PDF. */
    public function complete(SemesterNarrativeReport $report): array
    {
        $bytes = $this->kept($report, 'complete-report.pdf', function () use ($report) {
            $builder = new DocumentBuilder($report);

            return $this->pdf->write(array_map(fn (string $key) => $builder->build($key), array_keys(DocumentBuilder::DOCUMENTS)));
        });

        return $this->file($bytes, self::fileName($report, 'Semester Narrative Report', 'pdf'), 'pdf');
    }

    /** The five Word files in one ZIP. */
    public function bundle(SemesterNarrativeReport $report): array
    {
        $path = tempnam(sys_get_temp_dir(), 'zip');
        $zip  = new ZipArchive();

        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the ZIP file.');
        }

        foreach (array_keys(DocumentBuilder::DOCUMENTS) as $key) {
            $file = $this->document($report, $key, 'docx');
            $zip->addFromString($file['name'], $file['bytes']);
        }

        $zip->close();
        $bytes = (string) file_get_contents($path);
        @unlink($path);

        return $this->file($bytes, self::fileName($report, 'Semester Narrative Report (Word files)', 'zip'), 'zip');
    }

    /**
     * Write the Word files of a report that is being submitted. Anything left from an
     * earlier attempt is cleared first, so nothing rendered before the final edits
     * can be served as the approved document.
     */
    public function writeSubmitted(SemesterNarrativeReport $report): void
    {
        Storage::disk('local')->deleteDirectory($report->documentDirectory());

        foreach (array_keys(DocumentBuilder::DOCUMENTS) as $key) {
            $this->document($report, $key, 'docx');
        }
    }

    /** "Chess - Minutes of Meetings - 1st Semester, A.Y. 2026-2027.docx" */
    public static function fileName(SemesterNarrativeReport $report, string $label, string $extension): string
    {
        $club = $report->club?->acronym ?: $report->club?->name ?: 'Club';
        $name = Str::ascii(str_replace('&', 'and', "{$club} - {$label} - {$report->semester?->label}"));

        return trim(preg_replace('/[\\\\\/:*?"<>|]+/', '', $name)) . '.' . $extension;
    }

    private function kept(SemesterNarrativeReport $report, string $file, Closure $render): string
    {
        if (! $report->isSubmitted()) {
            return $render();
        }

        $disk = Storage::disk('local');
        $path = $report->documentDirectory() . '/' . $file;

        if ($disk->exists($path)) {
            return (string) $disk->get($path);
        }

        $bytes = $render();
        $disk->put($path, $bytes);

        return $bytes;
    }

    private function file(string $bytes, string $name, string $format): array
    {
        return ['bytes' => $bytes, 'name' => $name, 'mime' => self::MIME[$format]];
    }
}

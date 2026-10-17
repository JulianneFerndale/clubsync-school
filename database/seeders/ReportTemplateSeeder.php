<?php

namespace Database\Seeders;

use App\Models\ClubReportSubmission;
use App\Models\ResourceDocument;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Publishes the DSA's five semester-end report templates (docs/resources-dsa) to the
 * resource library, one per report type.
 *
 * Safe to run on every deploy: a template already published is left alone — including
 * one the admin has since replaced — unless its file has gone missing from storage,
 * in which case the bundled copy is put back so the download never 404s.
 */
class ReportTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $disk = Storage::disk('local');

        foreach (ClubReportSubmission::TYPES as $type => $spec) {
            $source = base_path('docs/resources-dsa/' . $spec['template']);

            if (! is_file($source)) {
                $this->command?->warn("Template for {$spec['label']} not found at {$source}; skipped.");
                continue;
            }

            $document = ResourceDocument::firstWhere('report_type', $type);

            if ($document && $disk->exists($document->file_path)) {
                continue;
            }

            $path = "resources/templates/{$type}.docx";
            $disk->put($path, file_get_contents($source));

            $attributes = [
                'file_path' => $path,
                'file_name' => $spec['download_as'],
                'file_size' => filesize($source),
            ];

            if ($document) {
                $document->update($attributes);
                $this->command?->info("Restored the missing file for {$spec['label']}.");
            } else {
                ResourceDocument::create($attributes + [
                    'category'    => 'template',
                    'report_type' => $type,
                    'title'       => $spec['label'] . ' Template',
                    'description' => 'Prepared by the ' . $spec['prepared_by'] . '. ' . $spec['description'],
                ]);
                $this->command?->info("Published the {$spec['label']} template.");
            }
        }
    }
}

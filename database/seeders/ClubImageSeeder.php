<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Club;
use App\Support\PublicUpload;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Club logos and banners (database/seeders/club-images), keyed by club slug like
 * ClubFacebookLinkSeeder. They are the pictures the DSA uploaded on the development
 * site by 2026-10-17, bundled so a fresh database (Laravel Cloud's) gets them too.
 *
 * A club keeps any picture it already has on the public disk, so re-running this
 * never undoes a logo the DSA has since replaced. Only a missing picture is filled:
 * none at all, a link to the old Firebase Storage bucket (which now answers 403),
 * or one whose file is gone from the disk.
 */
class ClubImageSeeder extends Seeder
{
    /** Bundled folder => [disk folder, club column]. */
    private const KINDS = [
        'logos'   => ['clubs/logos', 'profile_photo_url'],
        'banners' => ['clubs/banners', 'banner_image'],
    ];

    public function run(): void
    {
        $disk   = Storage::disk('public');
        $filled = ['logos' => 0, 'banners' => 0];
        $noClub = [];

        foreach (self::KINDS as $kind => [$folder, $column]) {
            foreach (glob(database_path("seeders/club-images/{$kind}/*.*")) as $source) {
                $slug = pathinfo($source, PATHINFO_FILENAME);
                $club = Club::firstWhere('slug', $slug);

                if (! $club) {
                    $noClub[$slug] = true;
                    continue;
                }

                $current = PublicUpload::path($club->{$column});

                if ($current !== null && $disk->exists($current)) {
                    continue;
                }

                $path = "{$folder}/" . basename($source);

                if (! $disk->put($path, file_get_contents($source))) {
                    $this->command?->error("Could not save {$path}.");
                    continue;
                }

                $club->update([$column => PublicUpload::url($path)]);
                $filled[$kind]++;
            }
        }

        if (array_sum($filled) > 0) {
            AuditLog::record('club.images.seeded', null, ['logos' => $filled['logos'], 'banners' => $filled['banners']]);
        }

        $this->command?->info("Logos added to {$filled['logos']} clubs, banners to {$filled['banners']}.");

        if ($noClub) {
            $this->command?->warn('No club found for: ' . implode(', ', array_keys($noClub)));
        }
    }
}

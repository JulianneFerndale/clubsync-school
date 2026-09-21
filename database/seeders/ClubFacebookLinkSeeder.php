<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Club;
use Illuminate\Database\Seeder;

/**
 * Official Facebook pages of the SCC clubs, keyed by club slug (names have been
 * edited in the registry over time; slugs have not).
 *
 * Only fills clubs that have no link yet, so re-running it never overwrites a link
 * the DSA has since corrected in the club form. Kickboxing Club and Taekwondo Club
 * have no page. KamaFIL, Societas Christi and Kalinaw Dance Ensemble were not on the
 * list supplied on 2026-09-14.
 */
class ClubFacebookLinkSeeder extends Seeder
{
    public const LINKS = [
        // ── Academic ────────────────────────────────────────────────────────
        'club_avant_garde' => 'https://www.facebook.com/sccavantgarde',
        'club_byte'        => 'https://www.facebook.com/byteorganization',
        'club_ewe'         => 'https://www.facebook.com/profile.php?id=61559777806801',
        'club_jma'         => 'https://www.facebook.com/SCCJMA',
        'club_jpia'        => 'https://www.facebook.com/JPIASCCChap',
        'club_jpcm'        => 'https://www.facebook.com/jpcmmakers',
        'club_jswap'       => 'https://www.facebook.com/sccpagSW',
        'club_mentors'     => 'https://www.facebook.com/thementorsclub',
        'club_psits'       => 'https://www.facebook.com/profile.php?id=100094561405868',
        'club_polisays'    => 'https://www.facebook.com/PoliSAYSofficial',
        'club_psyms'       => 'https://www.facebook.com/profile.php?id=61576462157341',
        'club_scimatrix'   => 'https://www.facebook.com/SCCSciMatrixSociety',
        'club_socialyte'   => 'https://www.facebook.com/profile.php?id=100070939238947',
        'club_societas'    => 'https://www.facebook.com/profile.php?id=100083182696467',
        'club_sohms'       => 'https://www.facebook.com/SCC.SoHMS',
        'club_syborg'      => 'https://www.facebook.com/SyBorgSCC',
        'club_tlpes'       => 'https://www.facebook.com/profile.php?id=100094936963825',

        // ── Non-academic ────────────────────────────────────────────────────
        'club_balibolistang' => 'https://www.facebook.com/profile.php?id=61565943163313',
        'club_cmf'           => 'https://www.facebook.com/profile.php?id=100084557711440',
        'club_yfc'           => 'https://www.facebook.com/profile.php?id=61563975358429',
        'club_chess'         => 'https://www.facebook.com/SCCchessclub',
        'club_debate'        => 'https://www.facebook.com/SCCDebateClub',
        'club_dreamhunters'  => 'https://www.facebook.com/profile.php?id=61577633280331',
        'club_elecom'        => 'https://www.facebook.com/sccelecom',
        'club_hiduha'        => 'https://www.facebook.com/musicOrg.scc',
        'club_interfaith'    => 'https://www.facebook.com/profile.php?id=61565551180534',
        'club_kavorg'        => 'https://www.facebook.com/profile.php?id=61578134175027',
        'club_marching_band' => 'https://www.facebook.com/profile.php?id=61550312476291',
        'club_mas_amicus'    => 'https://www.facebook.com/profile.php?id=100086489855838',
        'club_mso'           => 'https://www.facebook.com/profile.php?id=61551911889651',
        'club_obra_graphia'  => 'https://www.facebook.com/obragraphia.scc',
        'club_peer'          => 'https://www.facebook.com/profile.php?id=61550580398499',
        'club_psalm'         => 'https://www.facebook.com/profile.php?id=100086362099731',
        'club_medics'        => 'https://www.facebook.com/profile.php?id=61577128719468',
        'club_ssg'           => 'https://www.facebook.com/profile.php?id=61555140857449', // Society of Students-on-Grant
        'club_sports'        => 'https://www.facebook.com/profile.php?id=100084232971989',
        'club_tmc'           => 'https://www.facebook.com/SCC.TMC',
    ];

    public function run(): void
    {
        $filled = 0;

        foreach (self::LINKS as $slug => $url) {
            $filled += Club::where('slug', $slug)->whereNull('facebook_url')->update(['facebook_url' => $url]);
        }

        $missing = array_diff(array_keys(self::LINKS), Club::whereIn('slug', array_keys(self::LINKS))->pluck('slug')->all());

        if ($filled > 0) {
            AuditLog::record('club.facebook_links.seeded', null, ['clubs_updated' => $filled]);
        }

        $this->command?->info("Facebook links added to {$filled} clubs.");

        if ($missing) {
            $this->command?->warn('No club found for: ' . implode(', ', $missing));
        }
    }
}

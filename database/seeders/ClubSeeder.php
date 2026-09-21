<?php

namespace Database\Seeders;

use App\Models\Club;
use App\Models\Course;
use App\Models\Department;
use Illuminate\Database\Seeder;

class ClubSeeder extends Seeder
{
    public function run(): void
    {
        // ── Academic clubs ────────────────────────────────────────────────────
        // course_slugs: one or more courses that auto-enroll into this club.
        // The pivot table (club_course) is the source of truth for enrollment;
        // course_slug on clubs is kept as 'all' for academic clubs.

        $academic = [

            // ── CTEAS ──────────────────────────────────────────────────────
            [
                'slug'            => 'club_psyms',
                'name'            => 'Psychology Major Society',
                'acronym'         => 'PSYMS',
                'department_slug' => 'dept_cteas',
                'course_slugs'    => ['course_ab_psych'],
                'description'     => 'Official organization for psychology students of Saint Columban College.',
                'profile_photo_url' => 'https://storage.googleapis.com/clubsyncing.firebasestorage.app/club_images/1772883922_psyms.jpg',
            ],
            [
                'slug'            => 'club_polisays',
                'name'            => 'Political Science Students Society',
                'acronym'         => 'POLISAYS',
                'department_slug' => 'dept_cteas',
                'course_slugs'    => ['course_ab_polsci'],
                'description'     => 'Organization for political science students promoting civic awareness and leadership.',
                'profile_photo_url' => null,
            ],
            [
                'slug'            => 'club_ewe',
                'name'            => 'English World Enthusiasts',
                'acronym'         => 'EWE',
                'department_slug' => 'dept_cteas',
                // BA English Language Studies + BSEd Major in English
                'course_slugs'    => ['course_baels', 'course_bsed_eng'],
                'description'     => 'Organization for English language and education students fostering literary and communication excellence.',
                'profile_photo_url' => null,
            ],
            [
                'slug'            => 'club_kamafil',
                'name'            => 'KamaFIL Club',
                'acronym'         => 'KamaFIL',
                'department_slug' => 'dept_cteas',
                'course_slugs'    => ['course_bsed_fil'],
                'description'     => 'Organization for Filipino language education students promoting Philippine literature and culture.',
                'profile_photo_url' => null,
            ],
            [
                'slug'            => 'club_jswap',
                'name'            => 'Junior Social Workers Alliance and Practitioners',
                'acronym'         => 'JSWAP',
                'department_slug' => 'dept_cteas',
                'course_slugs'    => ['course_bssw'],
                'description'     => 'Organization for social work students dedicated to community service and professional development.',
                'profile_photo_url' => null,
            ],
            [
                'slug'            => 'club_socialyte',
                'name'            => 'SociAlyte',
                'acronym'         => 'SociAlyte',
                'department_slug' => 'dept_cteas',
                'course_slugs'    => ['course_bsed_ss'],
                'description'     => 'Organization for social studies education students committed to historical and social literacy.',
                'profile_photo_url' => null,
            ],
            [
                'slug'            => 'club_scimatrix',
                'name'            => 'SciMatrix Society',
                'acronym'         => 'SciMatrix',
                'department_slug' => 'dept_cteas',
                // BSEd Science + BSEd Mathematics
                'course_slugs'    => ['course_bsed_sci', 'course_bsed_math'],
                'description'     => 'Organization for science and mathematics education students advancing scientific inquiry and teaching.',
                'profile_photo_url' => null,
            ],
            [
                'slug'            => 'club_tlpes',
                'name'            => 'Technology Livelihood and Physical Education Students',
                'acronym'         => 'TLPES',
                'department_slug' => 'dept_cteas',
                // BTLEd + BPEd
                'course_slugs'    => ['course_btled', 'course_bped'],
                'description'     => 'Organization for BTLEd and BPEd students promoting skills, wellness, and active living.',
                'profile_photo_url' => null,
            ],
            [
                'slug'            => 'club_mentors',
                'name'            => 'Mentors Club',
                'acronym'         => 'Mentors',
                'department_slug' => 'dept_cteas',
                'course_slugs'    => ['course_beed'],
                'description'     => 'Organization for elementary education students shaping the next generation of educators.',
                'profile_photo_url' => null,
            ],
            [
                'slug'            => 'club_societas',
                'name'            => 'Societas Catechist Christi',
                'acronym'         => 'Societas',
                'department_slug' => 'dept_cteas',
                'course_slugs'    => ['course_ab_philo'],
                'description'     => 'Organization for philosophy students fostering faith, reason, and moral formation.',
                'profile_photo_url' => null,
            ],
            [
                'slug'            => 'club_societas_christi',
                'name'            => 'Societas Christi',
                'acronym'         => 'SCC',
                'department_slug' => 'dept_cteas',
                'course_slugs'    => ['course_bsed_valed'],
                'description'     => 'Organization for values education students nurturing faith formation and moral leadership.',
                'profile_photo_url' => null,
            ],

            // ── CBE ────────────────────────────────────────────────────────
            [
                'slug'            => 'club_jpia',
                'name'            => 'Junior Philippine Institute of Accountants',
                'acronym'         => 'JPIA',
                'department_slug' => 'dept_cbe',
                // BSA + BSAIS
                'course_slugs'    => ['course_bsa', 'course_bsais'],
                'description'     => 'Official student chapter of PICPA for accounting and accounting information system students.',
                'profile_photo_url' => null,
            ],
            [
                'slug'            => 'club_jma',
                'name'            => 'Junior Management Association',
                'acronym'         => 'JMA',
                'department_slug' => 'dept_cbe',
                'course_slugs'    => ['course_bsma'],
                'description'     => 'Organization for management accounting students developing future business leaders.',
                'profile_photo_url' => null,
            ],
            [
                'slug'            => 'club_jpcm',
                'name'            => 'Junior Professional Club of Marketers',
                'acronym'         => 'JPCM',
                'department_slug' => 'dept_cbe',
                // Marketing + FinMan + HRM + Operations + Internal Auditing + Office Admin + Real Estate
                'course_slugs'    => [
                    'course_bsba_market',
                    'course_bsba_finman',
                    'course_bsba_hrm',
                    'course_bsba_op',
                    'course_bsia',
                    'course_bsoa',
                    'course_bsrem',
                ],
                'description'     => 'Organization for business administration students building professional skills and industry connections.',
                'profile_photo_url' => null,
            ],
            [
                'slug'            => 'club_sohms',
                'name'            => 'Society of Hospitality Management Students',
                'acronym'         => 'SOHMS',
                'department_slug' => 'dept_cbe',
                'course_slugs'    => ['course_bshm'],
                'description'     => 'Organization for hospitality management students preparing for careers in tourism and hotel industry.',
                'profile_photo_url' => null,
            ],

            // ── CCS ────────────────────────────────────────────────────────
            [
                'slug'            => 'club_byte',
                'name'            => 'Building the Youth through Technology Education',
                'acronym'         => 'BYTE Org',
                'department_slug' => 'dept_ccs',
                // BSIT only
                'course_slugs'    => ['course_bsit'],
                'description'     => 'Official organization for information technology students of Saint Columban College.',
                'profile_photo_url' => 'https://storage.googleapis.com/clubsyncing.firebasestorage.app/club_images/1773199171_scc_byte_logo.jpg',
            ],
            [
                'slug'            => 'club_syborg',
                'name'            => 'System Builders Organization',
                'acronym'         => 'SYBORG',
                'department_slug' => 'dept_ccs',
                // BSCS + BSIS + BLIS
                'course_slugs'    => ['course_bscs', 'course_bsis', 'course_blis'],
                'description'     => 'Official organization for computer science, information systems, and library science students.',
                'profile_photo_url' => 'https://storage.googleapis.com/clubsyncing.firebasestorage.app/club_images/1772884313_scc_syborg_logo.jpg',
            ],
            [
                'slug'            => 'club_psits',
                'name'            => 'Philippine Society of Information Technology Students',
                'acronym'         => 'PSITS',
                'department_slug' => 'dept_ccs',
                // BSIT + BSCS + BSIS + BLIS
                'course_slugs'    => ['course_bsit', 'course_bscs', 'course_bsis', 'course_blis'],
                'description'     => 'Official PSITS chapter serving all computing students of Saint Columban College.',
                'profile_photo_url' => null,
            ],

            // ── COC ────────────────────────────────────────────────────────
            [
                'slug'            => 'club_avant_garde',
                'name'            => 'Avant-Garde',
                'acronym'         => 'Avant-Garde',
                'department_slug' => 'dept_coc',
                'course_slugs'    => ['course_bscrim'],
                'description'     => 'Organization for criminology students of Saint Columban College.',
                'profile_photo_url' => null,
            ],
        ];

        // ── Non-academic clubs ────────────────────────────────────────────────
        $nonAcademic = [
            [
                'slug'              => 'club_ssg',
                'name'              => 'Supreme Government Society — Saint Columban College',
                'acronym'           => 'SGS-SCC',
                'description'       => 'The supreme student government body of Saint Columban College.',
                'profile_photo_url' => null,
            ],
            [
                'slug'              => 'club_elecom',
                'name'              => 'Election Committee',
                'acronym'           => 'ELECOM',
                'description'       => 'Election Committee serving all students of Saint Columban College.',
                'profile_photo_url' => 'https://storage.googleapis.com/clubsyncing.firebasestorage.app/club_images/1773062378_scc_elecom_logo.jpeg',
            ],
            [
                'slug'              => 'club_medics',
                'name'              => 'SCC Medics',
                'acronym'           => 'Medics',
                'description'       => 'First-aid and health emergency response organization of Saint Columban College.',
                'profile_photo_url' => null,
            ],
            [
                'slug'              => 'club_dreamhunters',
                'name'              => 'Dreamhunters Esports',
                'acronym'           => 'Dreamhunters',
                'description'       => 'Competitive esports organization representing Saint Columban College in gaming tournaments.',
                'profile_photo_url' => null,
            ],
            [
                'slug'              => 'club_taekwondo',
                'name'              => 'Taekwondo Club',
                'acronym'           => 'Taekwondo',
                'description'       => 'Martial arts club promoting discipline, fitness, and sportsmanship through taekwondo.',
                'profile_photo_url' => null,
            ],
            [
                'slug'              => 'club_kickboxing',
                'name'              => 'Kickboxing Club',
                'acronym'           => 'Kickboxing',
                'description'       => 'Combat sports club developing fitness, self-defense, and competitive kickboxing skills.',
                'profile_photo_url' => null,
            ],
            [
                'slug'              => 'club_interfaith',
                'name'              => 'Interfaith Organization',
                'acronym'           => 'Interfaith',
                'description'       => 'Multi-faith organization promoting respect, dialogue, and unity among students of all beliefs.',
                'profile_photo_url' => null,
            ],
            [
                'slug'              => 'club_mas_amicus',
                'name'              => 'AMiCUS',
                'acronym'           => 'AMiCUS',
                'description'       => 'Student organization fostering friendship, service, and academic excellence.',
                'profile_photo_url' => null,
            ],
            [
                'slug'              => 'club_chess',
                'name'              => 'Chess Club',
                'acronym'           => 'Chess',
                'description'       => 'Chess club open to all Saint Columban College students.',
                'profile_photo_url' => 'https://storage.googleapis.com/clubsyncing.firebasestorage.app/club_images/1773062094_scc_chess_logo.jpg',
            ],
            [
                'slug'              => 'club_peer',
                'name'              => 'Peer Counselors',
                'acronym'           => 'Peer',
                'description'       => 'Trained student volunteers providing peer support and mental wellness advocacy.',
                'profile_photo_url' => null,
            ],
            [
                'slug'              => 'club_debate',
                'name'              => 'Debate Varsity',
                'acronym'           => 'Debate',
                'description'       => 'Competitive debate organization developing critical thinking and public speaking skills.',
                'profile_photo_url' => 'https://storage.googleapis.com/clubsyncing.firebasestorage.app/club_images/1773062194_scc_debate_club_logo.jpg',
            ],
            [
                'slug'              => 'club_psalm',
                'name'              => 'PSALM',
                'acronym'           => 'PSALM',
                'description'       => 'Organization for students with a passion for music, arts, and liturgical ministry.',
                'profile_photo_url' => null,
            ],
            [
                'slug'              => 'club_obra_graphia',
                'name'              => 'Obra Graphia',
                'acronym'           => 'Obra Graphia',
                'description'       => 'Visual arts and graphic design organization for creative students of Saint Columban College.',
                'profile_photo_url' => null,
            ],
            [
                'slug'              => 'club_cmf',
                'name'              => 'Campus Ministry Family',
                'acronym'           => 'CMF',
                'description'       => 'Faith-based organization nurturing spiritual growth and service among the student community.',
                'profile_photo_url' => null,
            ],
            [
                'slug'              => 'club_sports',
                'name'              => 'Sports Organization',
                'acronym'           => 'Sports',
                'description'       => 'Umbrella sports organization promoting athletic development and sportsmanship at SCC.',
                'profile_photo_url' => null,
            ],
            [
                'slug'              => 'club_marching_band',
                'name'              => 'Marching Band',
                'acronym'           => 'Marching Band',
                'description'       => 'School marching band representing Saint Columban College in parades and competitions.',
                'profile_photo_url' => null,
            ],
            [
                'slug'              => 'club_hiduha',
                'name'              => 'Hiduha Musica',
                'acronym'           => 'Hiduha',
                'description'       => 'Music organization celebrating Bisayan musical heritage and contemporary performance.',
                'profile_photo_url' => null,
            ],
            [
                'slug'              => 'club_kavorg',
                'name'              => 'Kadugtong Volunteers Organization',
                'acronym'           => 'KavOrg',
                'description'       => 'Community service and volunteer organization committed to outreach and social responsibility.',
                'profile_photo_url' => null,
            ],
            [
                'slug'              => 'club_mso',
                'name'              => 'Muslim Student Organization',
                'acronym'           => 'MSO',
                'description'       => 'Organization serving and unifying Muslim students of Saint Columban College.',
                'profile_photo_url' => null,
            ],
            [
                'slug'              => 'club_balibolistang',
                'name'              => 'Balibolistang Kolumbano',
                'acronym'           => 'Balibolistang',
                'description'       => 'Volleyball sports organization representing Saint Columban College.',
                'profile_photo_url' => null,
            ],
            [
                'slug'              => 'club_yfc',
                'name'              => 'CFC Youth for Christ',
                'acronym'           => 'YFC',
                'description'       => 'Youth ministry arm of Couples for Christ fostering faith formation and servant leadership.',
                'profile_photo_url' => null,
            ],
            [
                'slug'              => 'club_kde',
                'name'              => 'Kalinaw Dance Ensemble',
                'acronym'           => 'KDE',
                'description'       => 'Dance organization promoting peace and culture through folk and contemporary performance.',
                'profile_photo_url' => null,
            ],
            [
                'slug'              => 'club_tmc',
                'name'              => 'The Masters of Ceremonies',
                'acronym'           => 'TMC',
                'description'       => 'Organization for trained event emcees and hosts serving the SCC community.',
                'profile_photo_url' => null,
            ],
        ];

        // Seed academic clubs and sync their course pivots
        foreach ($academic as $data) {
            $courseSlugs = $data['course_slugs'];
            $dept = Department::where('slug', $data['department_slug'])->firstOrFail();

            $club = Club::updateOrCreate(['slug' => $data['slug']], [
                'name'              => $data['name'],
                'acronym'           => $data['acronym'],
                'adviser'           => '',
                'club_type'         => 'Academic',
                'course_slug'       => 'all',
                'department_slug'   => $data['department_slug'],
                'description'       => $data['description'],
                'is_active'         => true,
                'profile_photo_url' => $data['profile_photo_url'],
            ]);

            $courseIds = Course::whereIn('slug', $courseSlugs)
                ->where('department_id', $dept->id)
                ->pluck('id');

            $club->courses()->sync($courseIds);
        }

        // Seed non-academic clubs (no course pivot needed)
        foreach ($nonAcademic as $data) {
            Club::updateOrCreate(['slug' => $data['slug']], [
                'name'              => $data['name'],
                'acronym'           => $data['acronym'],
                'adviser'           => '',
                'club_type'         => 'Non-Academic',
                'course_slug'       => 'all',
                'department_slug'   => 'all',
                'description'       => $data['description'],
                'is_active'         => true,
                'profile_photo_url' => $data['profile_photo_url'],
            ]);
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        // Remove renamed course slugs from previous seed so updateOrCreate
        // on the new slugs does not leave orphaned records behind.
        Course::whereIn('slug', [
            'course_ab_english',
            'course_bsed_english',
            'course_bsed_filipino',
            'course_bsed_science',
            'course_bsed_socstud',
            'course_bsba_mm',
            'course_bsba_om',
        ])->delete();

        $data = [
            [
                'slug'       => 'dept_cteas',
                'name'       => 'College of Teacher Education, Arts and Sciences',
                'short_name' => 'CTEAS',
                'courses'    => [
                    ['slug' => 'course_baels',      'name' => 'Bachelor of Arts in English Language Studies'],
                    ['slug' => 'course_ab_history',  'name' => 'Bachelor of Arts Major in History'],
                    ['slug' => 'course_ab_philo',    'name' => 'Bachelor of Arts Major in Philosophy'],
                    ['slug' => 'course_ab_polsci',   'name' => 'Bachelor of Arts Major in Political Science'],
                    ['slug' => 'course_ab_psych',    'name' => 'Bachelor of Arts Major in Psychology'],
                    ['slug' => 'course_beed',        'name' => 'Bachelor of Elementary Education'],
                    ['slug' => 'course_bped',        'name' => 'Bachelor of Physical Education'],
                    ['slug' => 'course_bsed_eng',    'name' => 'Bachelor of Secondary Education Major in English'],
                    ['slug' => 'course_bsed_fil',    'name' => 'Bachelor of Secondary Education Major in Filipino'],
                    ['slug' => 'course_bsed_math',   'name' => 'Bachelor of Secondary Education Major in Mathematics'],
                    ['slug' => 'course_bsed_sci',    'name' => 'Bachelor of Secondary Education Major in Science'],
                    ['slug' => 'course_bsed_ss',     'name' => 'Bachelor of Secondary Education Major in Social Studies'],
                    ['slug' => 'course_bsed_valed',  'name' => 'Bachelor of Secondary Education Major in Values Education'],
                    ['slug' => 'course_bssw',        'name' => 'Bachelor of Science in Social Work'],
                    ['slug' => 'course_btled',       'name' => 'Bachelor of Technology and Livelihood Education'],
                ],
            ],
            [
                'slug'       => 'dept_cbe',
                'name'       => 'College of Business Education',
                'short_name' => 'CBE',
                'courses'    => [
                    ['slug' => 'course_bsa',           'name' => 'Bachelor of Science in Accountancy'],
                    ['slug' => 'course_bsais',         'name' => 'Bachelor of Science in Accounting Information System'],
                    ['slug' => 'course_bsba_finman',   'name' => 'Bachelor of Science in Business Administration Major in Financial Management'],
                    ['slug' => 'course_bsba_hrm',      'name' => 'Bachelor of Science in Business Administration Major in Human Resource Management'],
                    ['slug' => 'course_bsba_market',   'name' => 'Bachelor of Science in Business Administration Major in Marketing Management'],
                    ['slug' => 'course_bsba_op',       'name' => 'Bachelor of Science in Business Administration Major in Operations Management'],
                    ['slug' => 'course_bshm',          'name' => 'Bachelor of Science in Hospitality Management'],
                    ['slug' => 'course_bsia',          'name' => 'Bachelor of Science in Internal Auditing'],
                    ['slug' => 'course_bsma',          'name' => 'Bachelor of Science in Management Accounting'],
                    ['slug' => 'course_bsoa',          'name' => 'Bachelor of Science in Office Administration'],
                    ['slug' => 'course_bsrem',         'name' => 'Bachelor of Science in Real Estate Management'],
                ],
            ],
            [
                'slug'       => 'dept_coc',
                'name'       => 'College of Criminology',
                'short_name' => 'COC',
                'courses'    => [
                    ['slug' => 'course_bscrim', 'name' => 'Bachelor of Science in Criminology'],
                ],
            ],
            [
                'slug'       => 'dept_ccs',
                'name'       => 'College of Computing Studies',
                'short_name' => 'CCS',
                'courses'    => [
                    ['slug' => 'course_blis', 'name' => 'Bachelor in Library and Information Science'],
                    ['slug' => 'course_bscs', 'name' => 'Bachelor of Science in Computer Science'],
                    ['slug' => 'course_bsis', 'name' => 'Bachelor of Science in Information Systems'],
                    ['slug' => 'course_bsit', 'name' => 'Bachelor of Science in Information Technology'],
                ],
            ],
        ];

        foreach ($data as $deptData) {
            $courses = $deptData['courses'];
            unset($deptData['courses']);
            $dept = Department::updateOrCreate(['slug' => $deptData['slug']], $deptData);
            foreach ($courses as $course) {
                $dept->courses()->updateOrCreate(['slug' => $course['slug']], $course);
            }
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\Club;
use App\Models\ClubActivity;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Fake students and a season of club life, for the load-test rehearsal only.
 *
 * Sized so every page carries a realistic amount of data: a bulletin whose posts have
 * hundreds of likes, fee pages with dozens of payments, attendance histories. No real
 * student's data is copied (RA 10173), and no Firebase account is made — the load test
 * signs these users in with `php artisan loadtest:sessions`.
 *
 *   php artisan db:seed --force                       # reference data: courses, clubs
 *   php artisan db:seed --class=LoadTestSeeder --force
 *
 * Runs only with LOADTEST=true (config/loadtest.php). Rows are bulk-inserted, and a
 * fixed random seed makes every rehearsal build the same data set.
 */
class LoadTestSeeder extends Seeder
{
    private const STUDENTS = 1000;
    private const ANNOUNCEMENTS_PER_CLUB = 12;
    private const PAST_ACTIVITIES_PER_CLUB = 6;
    private const UPCOMING_ACTIVITIES_PER_CLUB = 4;
    private const NOTIFICATIONS_PER_STUDENT = 15;

    private const FIRST = ['Angela', 'Mark', 'Jessa', 'John Paul', 'Kristine', 'Carlo', 'Mae', 'Rhea', 'Joshua', 'Aileen',
        'Nico', 'Princess', 'Jericho', 'Lovely', 'Christian', 'Shaira', 'Kenneth', 'Janelle', 'Ramil', 'Bea'];
    private const LAST = ['Dela Cruz', 'Santos', 'Reyes', 'Bautista', 'Garcia', 'Mendoza', 'Villanueva', 'Ramos', 'Aquino',
        'Castillo', 'Navarro', 'Torres', 'Flores', 'Lopez', 'Gonzales', 'Rivera', 'Sarmiento', 'Pascual', 'Soriano', 'Salazar'];
    // Venues come from ClubActivity::VENUES, so load-test data uses the real main-campus rooms.

    private const BODY = 'Good day, members! This is a reminder about our upcoming club activities for this semester. '
        . 'Please check the schedule, confirm your attendance with your year-level representative, and settle any '
        . 'pending club fees with the treasurer before the due date. Bring your school ID and wear the prescribed '
        . 'uniform. For questions, message any of the officers through ClubSync. Thank you and God bless!';

    public function run(): void
    {
        if (! config('loadtest.enabled')) {
            $this->command?->error('LoadTestSeeder only runs with LOADTEST=true (the load-test rehearsal).');

            return;
        }

        $domain = config('loadtest.email_domain');

        if (User::where('email', 'like', "%@{$domain}")->exists()) {
            $this->command?->warn('Fake students already exist. Start over with: php artisan migrate:fresh --seed --force');

            return;
        }

        $courses = Course::all(['id', 'slug', 'department_id']);
        $clubs   = Club::where('is_active', true)->get(['id', 'type', 'club_type', 'course_slug', 'name']);

        if ($courses->isEmpty() || $clubs->isEmpty()) {
            $this->command?->error('Run the reference seeders first: php artisan db:seed --force');

            return;
        }

        mt_srand(2026);
        $now = Carbon::now();

        $academic    = $clubs->filter(fn (Club $c) => $c->isAcademicType())->values();
        $nonAcademic = $clubs->reject(fn (Club $c) => $c->isAcademicType())->values();

        // ── Students ────────────────────────────────────────────────────────────
        // One hash for everyone: bcrypt per row would take minutes, and these
        // accounts are never signed into with a password.
        $password = Hash::make(bin2hex(random_bytes(16)));
        $rows = [];

        for ($i = 1; $i <= self::STUDENTS; $i++) {
            $course = $courses[mt_rand(0, $courses->count() - 1)];
            $rows[] = [
                'first_name'              => self::FIRST[mt_rand(0, count(self::FIRST) - 1)],
                'last_name'               => self::LAST[mt_rand(0, count(self::LAST) - 1)],
                'edp_number'              => 'LT' . str_pad((string) $i, 6, '0', STR_PAD_LEFT),
                'email'                   => sprintf('student%04d@%s', $i, $domain),
                'password'                => $password,
                'firebase_uid'            => "loadtest-{$i}",
                'role'                    => 'member',
                'course_id'               => $course->id,
                'department_id'           => $course->department_id,
                'year_level'              => mt_rand(1, 4),
                'is_admin'                => false,
                'is_suspended'            => false,
                'pending_club_assignment' => false,
                'created_at'              => $now,
                'updated_at'              => $now,
            ];
        }
        $this->insert('users', $rows);

        $students = User::where('email', 'like', "%@{$domain}")->orderBy('id')->get(['id', 'course_id']);
        $courseSlug = $courses->pluck('slug', 'id');

        // ── Memberships: one academic club (by course where one exists) + 1–2 others ──
        $membersOf = [];   // club id => [user ids]
        $rows = [];

        foreach ($students as $student) {
            $own = $academic->firstWhere('course_slug', $courseSlug[$student->course_id] ?? null)
                ?? $academic[mt_rand(0, $academic->count() - 1)];
            $joined = [$own->id];

            for ($k = mt_rand(1, 2); $k > 0; $k--) {
                $joined[] = $nonAcademic[mt_rand(0, $nonAcademic->count() - 1)]->id;
            }

            foreach (array_unique($joined) as $clubId) {
                $membersOf[$clubId][] = $student->id;
                $at = $now->copy()->subDays(mt_rand(30, 200));
                $rows[] = [
                    'club_id' => $clubId, 'user_id' => $student->id, 'role' => 'Member',
                    'date_joined' => $at, 'joined_at' => $at, 'status' => 'active',
                    'registration_status' => 'approved', 'created_at' => $at, 'updated_at' => $at,
                ];
            }
        }
        $this->insert('club_members', $rows);

        // ── A president per club, from its own members ─────────────────────────
        $president = [];
        $taken = [];
        $rows = [];

        foreach ($membersOf as $clubId => $memberIds) {
            foreach ($memberIds as $userId) {
                if (! isset($taken[$userId])) {
                    $president[$clubId] = $taken[$userId] = $userId;
                    $rows[] = ['club_id' => $clubId, 'user_id' => $userId, 'position' => 'president',
                        'assigned_at' => $now->copy()->subDays(100), 'created_at' => $now, 'updated_at' => $now];
                    break;
                }
            }
        }
        $this->insert('club_officers', $rows);
        User::whereIn('id', array_values($president))->update(['role' => 'president']);

        // ── Announcements, with likes and comments from the club's members ─────
        $clubName = $clubs->pluck('name', 'id');
        $rows = [];

        foreach ($president as $clubId => $authorId) {
            for ($n = 1; $n <= self::ANNOUNCEMENTS_PER_CLUB; $n++) {
                $at = $now->copy()->subDays(mt_rand(0, 90))->subMinutes(mt_rand(0, 1440));
                $rows[] = [
                    'club_id' => $clubId, 'author_id' => $authorId, 'type' => 'announcement',
                    'title' => "{$clubName[$clubId]} — Update #{$n}", 'content' => self::BODY,
                    'status' => 'published', 'ai_assisted' => false,
                    'published_at' => $at, 'created_at' => $at, 'updated_at' => $at,
                ];
            }
        }
        $this->insert('announcements', $rows);

        $likes = $comments = [];
        foreach (DB::table('announcements')->whereIn('club_id', array_keys($president))->get(['id', 'club_id', 'published_at']) as $post) {
            foreach ($membersOf[$post->club_id] as $userId) {
                if (mt_rand(1, 100) <= 35) {
                    $likes[] = ['announcement_id' => $post->id, 'user_id' => $userId, 'created_at' => $post->published_at, 'updated_at' => $post->published_at];
                }
            }
            for ($c = mt_rand(0, 4); $c > 0; $c--) {
                $userId = $membersOf[$post->club_id][mt_rand(0, count($membersOf[$post->club_id]) - 1)];
                $comments[] = ['announcement_id' => $post->id, 'user_id' => $userId, 'content' => 'Noted, thank you!',
                    'created_at' => $post->published_at, 'updated_at' => $post->published_at];
            }
        }
        $this->insert('announcement_likes', $likes);
        $this->insert('announcement_comments', $comments);

        // ── Activities: past ones with attendance, upcoming ones scheduled ─────
        $rows = [];
        foreach ($president as $clubId => $creatorId) {
            $total = self::PAST_ACTIVITIES_PER_CLUB + self::UPCOMING_ACTIVITIES_PER_CLUB;
            for ($n = 1; $n <= $total; $n++) {
                $past = $n <= self::PAST_ACTIVITIES_PER_CLUB;
                $date = $past ? $now->copy()->subDays(mt_rand(3, 120)) : $now->copy()->addDays(mt_rand(3, 60));
                $rows[] = [
                    'club_id' => $clubId, 'title' => ($past ? 'General Assembly ' : 'Planning Meeting ') . $n,
                    'description' => 'Regular club gathering for all members.', 'purpose' => 'Club planning and updates.',
                    'date' => $date->toDateString(), 'time_start' => '13:00:00', 'time_end' => '15:00:00',
                    'venue' => ClubActivity::VENUES[mt_rand(0, count(ClubActivity::VENUES) - 1)],
                    'expected_participants' => count($membersOf[$clubId]),
                    'status' => $past ? 'completed' : 'scheduled', 'completed_at' => $past ? $date : null,
                    'event_type' => 'standard', 'activity_type' => 'internal_meeting', 'approval_status' => 'no_approval_needed',
                    'created_by' => $creatorId, 'created_at' => $date->copy()->subDays(7), 'updated_at' => $date,
                ];
            }
        }
        $this->insert('club_activities', $rows);

        $rows = [];
        foreach (DB::table('club_activities')->where('status', 'completed')->whereIn('club_id', array_keys($president))->get(['id', 'club_id', 'date']) as $activity) {
            $in = Carbon::parse($activity->date)->setTime(13, 5);
            foreach ($membersOf[$activity->club_id] as $userId) {
                if (mt_rand(1, 100) <= 50) {
                    $rows[] = ['event_id' => $activity->id, 'user_id' => $userId, 'time_in' => $in, 'time_out' => $in->copy()->addHours(2),
                        'recorded_by' => $president[$activity->club_id], 'created_at' => $in, 'updated_at' => $in];
                }
            }
        }
        $this->insert('attendance', $rows);

        // ── Fees: membership mostly paid (with receipts), event fund pending ──
        $rows = [];
        foreach (array_keys($president) as $clubId) {
            $rows[] = ['club_id' => $clubId, 'title' => 'Membership Fee', 'amount' => 150, 'due_date' => $now->copy()->subDays(20)->toDateString(),
                'academic_period' => '1st Semester 2026-2027', 'created_at' => $now->copy()->subDays(60), 'updated_at' => $now];
            $rows[] = ['club_id' => $clubId, 'title' => 'Event Fund', 'amount' => 100, 'due_date' => $now->copy()->addDays(20)->toDateString(),
                'academic_period' => '1st Semester 2026-2027', 'created_at' => $now->copy()->subDays(10), 'updated_at' => $now];
        }
        $this->insert('fees', $rows);

        $rows = [];
        $receipt = 0;
        foreach (DB::table('fees')->whereIn('club_id', array_keys($president))->orderBy('id')->get(['id', 'club_id', 'title', 'created_at']) as $fee) {
            foreach ($membersOf[$fee->club_id] as $userId) {
                $paid = $fee->title === 'Membership Fee' && mt_rand(1, 100) <= 70;
                $rows[] = [
                    'fee_id' => $fee->id, 'user_id' => $userId, 'status' => $paid ? 'paid' : 'pending',
                    'receipt_number' => $paid ? sprintf('OR-%d-%05d', $now->year, ++$receipt) : null,
                    'confirmed_by' => $paid ? $president[$fee->club_id] : null, 'confirmed_at' => $paid ? $now->copy()->subDays(mt_rand(1, 40)) : null,
                    'created_at' => $fee->created_at, 'updated_at' => $now,
                ];
            }
        }
        $this->insert('fee_payments', $rows);

        // ── Notifications: the recent club news each student was sent ───────────
        $rows = [];
        foreach ($membersOf as $clubId => $memberIds) {
            foreach ($memberIds as $userId) {
                for ($n = 0; $n < intdiv(self::NOTIFICATIONS_PER_STUDENT, 2); $n++) {
                    $at = $now->copy()->subDays(mt_rand(0, 60));
                    $rows[] = ['recipient_id' => $userId, 'sender_type' => 'system', 'club_id' => $clubId,
                        'title' => "New announcement from {$clubName[$clubId]}", 'body' => 'Tap to read the latest update.',
                        'is_read' => mt_rand(1, 100) <= 70, 'action_url' => '/member/bulletin', 'created_at' => $at, 'updated_at' => $at];
                }
            }
        }
        $this->insert('notifications', $rows);

        $this->command?->info(sprintf(
            'Seeded %d students (%d presidents), %d memberships, %d announcements, %d likes, %d activities, %d attendance, %d fee payments, %d notifications.',
            $students->count(), count($president), DB::table('club_members')->whereIn('user_id', $students->pluck('id'))->count(),
            DB::table('announcements')->count(), count($likes), DB::table('club_activities')->count(),
            DB::table('attendance')->count(), DB::table('fee_payments')->count(), DB::table('notifications')->count(),
        ));
    }

    /** Bulk insert in chunks that stay under PostgreSQL's 65,535-parameter limit. */
    private function insert(string $table, array $rows): void
    {
        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }
}

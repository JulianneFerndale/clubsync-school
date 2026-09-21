<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Give a semester a term and a school year of its own.
 *
 * A semester was only a label and two dates, so nothing could ask which term it was
 * or which school year it belonged to — and "the summer semester ends, so the school
 * year turns over" had no way to be expressed except by matching words in the label,
 * which stops working the moment somebody types it differently.
 *
 * Existing rows are filled in from their label where it says so, and from their start
 * date otherwise. A school year is named for the calendar year it starts in: August
 * 2026 through the summer that follows is `2026-2027`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('semesters', function (Blueprint $table) {
            $table->string('term', 10)->nullable()->after('label');
            $table->string('academic_year', 9)->nullable()->after('term');
        });

        foreach (DB::table('semesters')->get() as $semester) {
            DB::table('semesters')->where('id', $semester->id)->update([
                'term'          => $this->term($semester->label),
                'academic_year' => $this->academicYear($semester->start_date),
            ]);
        }

        // Semester-scoped features fail closed when nothing is flagged current, which
        // is safe but silently switches them off. If none is flagged and one covers
        // today, flag it; otherwise leave the decision to an administrator.
        if (! DB::table('semesters')->where('is_current', true)->exists()) {
            $today = now()->toDateString();

            DB::table('semesters')
                ->where('start_date', '<=', $today)
                ->where('end_date', '>=', $today)
                ->limit(1)
                ->update(['is_current' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('semesters', function (Blueprint $table) {
            $table->dropColumn(['term', 'academic_year']);
        });
    }

    private function term(?string $label): string
    {
        $label = strtolower((string) $label);

        return match (true) {
            str_contains($label, 'summer')                              => 'summer',
            str_contains($label, '2nd') || str_contains($label, 'second') => 'second',
            default                                                     => 'first',
        };
    }

    /** August onwards starts a school year; anything earlier belongs to the one before. */
    private function academicYear(string $startDate): string
    {
        $start = \Carbon\Carbon::parse($startDate);
        $first = $start->month >= 8 ? $start->year : $start->year - 1;

        return $first . '-' . ($first + 1);
    }
};

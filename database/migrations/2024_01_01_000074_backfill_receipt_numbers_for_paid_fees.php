<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Give the payments that were confirmed before receipts existed their numbers.
 *
 * Without this a treasurer sees a paid member with no receipt to hand over and no way
 * to produce one, which reads as the feature being broken rather than as the payment
 * predating it. Numbers are handed out in the order the money was confirmed, so the
 * series still reads chronologically, and each is stamped with the year of its own
 * payment rather than the year this migration happens to run in.
 */
return new class extends Migration
{
    public function up(): void
    {
        $paid = DB::table('fee_payments')
            ->where('status', 'paid')
            ->whereNull('receipt_number')
            ->whereNotNull('confirmed_at')
            ->orderBy('confirmed_at')
            ->orderBy('id')
            ->get(['id', 'confirmed_at']);

        // Continue each year's series from wherever it already stands.
        $next = [];

        foreach ($paid as $payment) {
            $year = \Carbon\Carbon::parse($payment->confirmed_at)->year;

            if (! isset($next[$year])) {
                $prefix = "OR-{$year}-";

                $last = DB::table('fee_payments')
                    ->where('receipt_number', 'like', $prefix . '%')
                    ->orderByDesc('receipt_number')
                    ->value('receipt_number');

                $next[$year] = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;
            }

            DB::table('fee_payments')->where('id', $payment->id)->update([
                'receipt_number' => "OR-{$year}-" . str_pad((string) $next[$year]++, 5, '0', STR_PAD_LEFT),
            ]);
        }
    }

    public function down(): void
    {
        // The column itself is dropped by the migration that added it; there is no
        // meaningful way to un-issue a receipt number beyond that.
    }
};

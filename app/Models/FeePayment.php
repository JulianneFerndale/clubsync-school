<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class FeePayment extends Model
{
    /** Official receipt, the year it was issued in, then its place in that year's series. */
    private const RECEIPT_PREFIX = 'OR';

    protected $fillable = ['fee_id', 'user_id', 'status', 'receipt_number', 'confirmed_by', 'confirmed_at'];

    protected $casts = [
        'confirmed_at' => 'datetime',
    ];

    public function fee(): BelongsTo
    {
        return $this->belongsTo(Fee::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /** A receipt can be handed over only for a payment that was actually confirmed. */
    public function hasReceipt(): bool
    {
        return $this->isPaid() && $this->receipt_number !== null;
    }

    /**
     * Give this payment its receipt number, once.
     *
     * Numbers run in one series per calendar year — OR-2026-00001, OR-2026-00002 —
     * so a receipt can be found by its number alone and the series reads in the order
     * the money came in.
     *
     * The read and the write are one locked transaction: two treasurers confirming
     * payments at the same moment would otherwise both read the same last number and
     * issue the same receipt twice. Same reason AuditLog::record() locks.
     */
    public function issueReceiptNumber(): string
    {
        if ($this->receipt_number) {
            return $this->receipt_number;   // already issued — never renumber a receipt
        }

        $year = now()->year;

        $number = DB::transaction(function () use ($year) {
            $prefix = self::RECEIPT_PREFIX . '-' . $year . '-';

            $last = static::query()
                ->where('receipt_number', 'like', $prefix . '%')
                ->lockForUpdate()
                ->orderByDesc('receipt_number')
                ->value('receipt_number');

            $sequence = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

            return $prefix . str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
        });

        $this->forceFill(['receipt_number' => $number])->save();

        return $number;
    }
}

<?php

namespace App\Jobs;

use App\Models\ClubNotification;
use App\Models\Fee;
use App\Models\FeePayment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Tell the members a fee concerns that it was added, changed, postponed or cancelled,
 * or tell one member that their payment was recorded or undone.
 *
 * Each notification is persisted and, through ClubNotification's created hook, pushed
 * to the member over Reverb — so a member with My Activity open sees the fee appear,
 * move or drop out of what they owe without refreshing the page.
 *
 * Only the members holding a payment row for the fee are told: those are the ones it
 * was billed to. A change to a fee goes only to those who still owe it; a cancellation
 * goes to everyone billed, since someone who already paid needs to hear it most.
 */
class NotifyMembersOfFee implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const CREATED   = 'created';
    public const UPDATED   = 'updated';
    public const POSTPONED = 'postponed';
    public const CANCELLED = 'cancelled';
    public const PAID      = 'paid';
    public const UNPAID    = 'unpaid';

    /**
     * @param  string    $reason         One of the constants above.
     * @param  int|null  $excludeUserId  The officer who made the change — they do not
     *                                   need telling about their own action.
     * @param  int|null  $onlyUserId     For PAID and UNPAID: the member whose payment it is.
     */
    public function __construct(
        private int $feeId,
        private string $reason = self::CREATED,
        private ?int $excludeUserId = null,
        private ?int $onlyUserId = null,
    ) {}

    public function handle(): void
    {
        $fee = Fee::with('club')->find($this->feeId);

        if (! $fee?->club) {
            return;
        }

        // Anything but a cancellation is news only about a fee still being collected.
        if ($fee->isCancelled() && $this->reason !== self::CANCELLED) {
            return;
        }

        $payments = FeePayment::where('fee_id', $fee->id)
            ->when($this->onlyUserId, fn ($q) => $q->where('user_id', $this->onlyUserId))
            ->when(in_array($this->reason, [self::CREATED, self::UPDATED, self::POSTPONED], true),
                fn ($q) => $q->where('status', 'pending'))
            ->when($this->excludeUserId, fn ($q) => $q->where('user_id', '!=', $this->excludeUserId));

        $url = route('member.activity', ['tab' => 'fees']);

        $payments->chunkById(200, function ($chunk) use ($fee, $url) {
            foreach ($chunk as $payment) {
                [$title, $body] = $this->message($fee, $payment);

                ClubNotification::create([
                    'recipient_id' => $payment->user_id,
                    'sender_type'  => 'system',
                    'club_id'      => $fee->club_id,
                    'title'        => $title,
                    'body'         => $body,
                    'action_url'   => $url,
                ]);
            }
        });
    }

    /** @return array{0: string, 1: string} */
    private function message(Fee $fee, FeePayment $payment): array
    {
        $club   = $fee->club->acronym ?: $fee->club->name;
        $amount = '₱' . number_format((float) $fee->amount, 2);
        $due    = $fee->due_date->format('M j, Y');
        $why    = filled($fee->status_reason) ? ' Reason: ' . $fee->status_reason : '';

        return match ($this->reason) {
            self::UPDATED => [
                "{$club} updated a fee",
                "\"{$fee->title}\" is now {$amount}, due {$due}.",
            ],
            self::POSTPONED => [
                "{$club} postponed a fee",
                "\"{$fee->title}\" ({$amount}) is now due {$due}"
                    . ($fee->previous_due_date ? ' instead of ' . $fee->previous_due_date->format('M j, Y') : '') . ".{$why}",
            ],
            self::CANCELLED => [
                "{$club} cancelled a fee",
                $payment->isPaid()
                    ? "\"{$fee->title}\" was cancelled. You had already paid {$amount}; ask your club treasurer about a refund.{$why}"
                    : "\"{$fee->title}\" ({$amount}) was cancelled. You no longer need to pay it.{$why}",
            ],
            self::PAID => [
                'Payment confirmed',
                "Your {$amount} payment for \"{$fee->title}\" ({$club}) was recorded"
                    . ($payment->receipt_number ? ". Receipt {$payment->receipt_number} is ready." : '.'),
            ],
            self::UNPAID => [
                'Payment marked unpaid',
                "Your payment for \"{$fee->title}\" ({$club}) was marked unpaid. {$amount} is due {$due}.",
            ],
            default => [
                "{$club} added a new fee",
                "\"{$fee->title}\" — {$amount}, due {$due}.",
            ],
        };
    }
}

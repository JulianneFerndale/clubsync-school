<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * Append-only, tamper-evident audit log (POLICY.md §Data Privacy).
 *
 * Records every data modification, approval action, and AI content generation
 * event with a timestamp, the actor's UID, and the affected resource. Each entry
 * is chained to the previous one via a SHA-256 hash, so tampering with any past
 * row invalidates every hash after it.
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null; // append-only — no updates

    protected $fillable = [
        'actor_id', 'action', 'resource_type', 'resource_id',
        'metadata', 'previous_hash', 'hash',
        // Fillable because record() hashes the timestamp it generates and must store
        // that exact value. Left out, mass assignment drops it and Eloquent stamps its
        // own — a second later across a tick boundary, and the row never verifies again.
        'created_at',
    ];

    protected $casts = [
        'metadata'   => 'array',
        'created_at' => 'datetime',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Write an audit entry. Safe to call from controllers (auth context) or queued
     * jobs (pass $actorId, or leave null for system-generated events).
     *
     * @param  string       $action     Dotted event name, e.g. 'ai.narrative.generated'
     * @param  Model|null   $resource   The affected model (its type + id are recorded)
     * @param  array        $metadata   Extra context to persist
     * @param  int|null     $actorId    Acting user id; defaults to the session user when available
     */
    public static function record(string $action, ?Model $resource = null, array $metadata = [], ?int $actorId = null): self
    {
        // Resolve the acting user, treating "no session" (0/null) as a system event.
        $actorId = $actorId ?: (function_exists('auth_user_id') ? auth_user_id() : null);
        $actorId = $actorId ?: null;

        return DB::transaction(function () use ($action, $resource, $metadata, $actorId) {
            $previous = static::query()->orderByDesc('id')->lockForUpdate()->first();
            $previousHash = $previous?->hash;

            $createdAt = now();

            $payload = self::payload(
                $actorId,
                $action,
                $resource ? $resource::class : null,
                $resource?->getKey(),
                $metadata,
                $createdAt->toIso8601String(),
            );

            $hash = hash('sha256', ($previousHash ?? '') . $payload);

            return static::create([
                'actor_id'      => $actorId,
                'action'        => $action,
                'resource_type' => $resource ? $resource::class : null,
                'resource_id'   => $resource?->getKey(),
                'metadata'      => $metadata,
                'previous_hash' => $previousHash,
                'hash'          => $hash,
                'created_at'    => $createdAt,
            ]);
        });
    }

    /**
     * The exact bytes an entry's hash is taken over.
     *
     * Written once and used by both the writer and the verifier: if the two ever
     * disagree about field order or formatting, every hash stops matching, and the
     * log would read as tampered when nothing had been touched.
     */
    private static function payload(
        ?int $actorId,
        string $action,
        ?string $resourceType,
        mixed $resourceId,
        ?array $metadata,
        string $createdAt,
    ): string {
        return json_encode([
            'actor_id'      => $actorId,
            'action'        => $action,
            'resource_type' => $resourceType,
            'resource_id'   => $resourceId,
            'metadata'      => $metadata,
            'created_at'    => $createdAt,
        ]);
    }

    /**
     * Walk the chain and report whether it is still intact.
     *
     * Two things are checked per row: that its `previous_hash` is the row before it,
     * and that its own hash still covers its contents.
     *
     * One wrinkle comes from the move to Philippine time. The hash covers `created_at`
     * as an ISO-8601 string, offset included, so a row written while the application
     * ran on UTC hashed "...+00:00" and now reads back "...+08:00". Those rows were
     * never touched — same instant, different label — so the walk also tries the UTC
     * reading for them, and counts them separately so the cutover stays visible rather
     * than hidden.
     *
     * That leniency ends at the cutover: the move happened once, so once a row has
     * verified as Philippine time, every row after it must too. A later row that only
     * verifies as UTC has had its timestamp moved, and is reported as broken.
     *
     * @return array{total: int, intact: int, legacy_utc: int, broken: array<int, int>, unlinked: array<int, int>}
     */
    public static function verifyChain(): array
    {
        $total = 0;
        $intact = 0;
        $legacyUtc = 0;
        $broken = [];
        $unlinked = [];
        $switched = false;
        $expectedPrevious = null;

        static::orderBy('id')->chunk(500, function ($rows) use (
            &$total, &$intact, &$legacyUtc, &$broken, &$unlinked, &$switched, &$expectedPrevious
        ) {
            foreach ($rows as $row) {
                $total++;

                if ($row->previous_hash !== $expectedPrevious) {
                    $unlinked[] = $row->id;
                }

                $expectedPrevious = $row->hash;

                $hashFor = fn (string $createdAt) => hash(
                    'sha256',
                    ($row->previous_hash ?? '') . self::payload(
                        $row->actor_id,
                        $row->action,
                        $row->resource_type,
                        $row->resource_id,
                        $row->metadata,
                        $createdAt,
                    ),
                );

                if ($hashFor($row->created_at->toIso8601String()) === $row->hash) {
                    $intact++;
                    $switched = true;

                    continue;
                }

                // Pre-cutover row: same wall clock, read as the UTC it was written in.
                if (! $switched && $hashFor($row->created_at->copy()->shiftTimezone('UTC')->toIso8601String()) === $row->hash) {
                    $intact++;
                    $legacyUtc++;

                    continue;
                }

                $broken[] = $row->id;
            }
        });

        return [
            'total'      => $total,
            'intact'     => $intact,
            'legacy_utc' => $legacyUtc,
            'broken'     => $broken,
            'unlinked'   => $unlinked,
        ];
    }
}

<?php

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('chains audit log hashes tamper-evidently', function () {
    $first  = AuditLog::record('test.one', null, ['a' => 1]);
    $second = AuditLog::record('test.two', null, ['b' => 2]);

    // First entry starts the chain.
    expect($first->previous_hash)->toBeNull();
    expect($first->hash)->not->toBeNull();

    // Second entry links to the first.
    expect($second->previous_hash)->toBe($first->hash);

    // Hash actually covers the payload (recomputing matches what was stored).
    $recomputed = hash('sha256', $first->hash . json_encode([
        'actor_id'      => null,
        'action'        => 'test.two',
        'resource_type' => null,
        'resource_id'   => null,
        'metadata'      => ['b' => 2],
        'created_at'    => $second->created_at->toIso8601String(),
    ]));
    expect($second->hash)->toBe($recomputed);
});

// ── Chain verification ──────────────────────────────────────────────────────

it('verifies an untouched chain', function () {
    AuditLog::record('test.one', null, ['a' => 1]);
    AuditLog::record('test.two', null, ['b' => 2]);
    AuditLog::record('test.three');

    $result = AuditLog::verifyChain();

    expect($result['total'])->toBe(3)
        ->and($result['intact'])->toBe(3)
        ->and($result['broken'])->toBe([])
        ->and($result['unlinked'])->toBe([])
        ->and($result['legacy_utc'])->toBe(0);
});

it('reports an entry whose contents were altered', function () {
    AuditLog::record('test.one');
    $second = AuditLog::record('violation.resolved', null, ['club' => 'A']);
    AuditLog::record('test.three');

    // Rewrite history without touching the hash.
    DB::table('audit_logs')->where('id', $second->id)->update(['action' => 'violation.dismissed']);

    $result = AuditLog::verifyChain();

    expect($result['broken'])->toBe([$second->id])
        ->and($result['intact'])->toBe(2);
});

it('reports an entry unlinked from the one before it', function () {
    AuditLog::record('test.one');
    $second = AuditLog::record('test.two');

    // Snap the link to the previous entry.
    DB::table('audit_logs')->where('id', $second->id)->update(['previous_hash' => str_repeat('0', 64)]);

    expect(AuditLog::verifyChain()['unlinked'])->toBe([$second->id]);
});

it('accepts entries hashed before the move to Philippine time', function () {
    // POLICY keeps audit rows for a year, so the log spans the timezone change. A row
    // written under UTC hashed "+00:00" and now reads back "+08:00" — same instant,
    // different label. It must not be reported as tampered.
    $legacy = auditLegacyUtcEntry('ai.narrative.generated', now()->subMonths(2));

    AuditLog::record('test.after', null, [], null);

    $result = AuditLog::verifyChain();

    expect($result['total'])->toBe(2)
        ->and($result['intact'])->toBe(2)
        ->and($result['legacy_utc'])->toBe(1)
        ->and($result['broken'])->toBe([])
        ->and($result['unlinked'])->toBe([]);

    expect($legacy->fresh()->created_at->toDateString())->toBe(now()->subMonths(2)->toDateString());
});

it('refuses a UTC-hashed entry that appears after the cutover', function () {
    // The move happened once. A row that only verifies as UTC *after* a Philippine-time
    // row has had its timestamp moved, which is exactly what the chain must catch.
    AuditLog::record('test.before');
    $tampered = auditLegacyUtcEntry('fees.waived', now());

    $result = AuditLog::verifyChain();

    expect($result['broken'])->toBe([$tampered->id])
        ->and($result['legacy_utc'])->toBe(0);
});

/**
 * Append an entry hashed the way the pre-Philippine-time application hashed it:
 * the same stored wall clock, but signed as UTC.
 */
function auditLegacyUtcEntry(string $action, \Carbon\Carbon $createdAt): AuditLog
{
    $previous = AuditLog::orderByDesc('id')->first();

    $payload = json_encode([
        'actor_id'      => null,
        'action'        => $action,
        'resource_type' => null,
        'resource_id'   => null,
        'metadata'      => [],
        'created_at'    => $createdAt->copy()->shiftTimezone('UTC')->toIso8601String(),
    ]);

    $entry = new AuditLog();

    // forceFill so the back-dated timestamp survives regardless of the guard.
    $entry->forceFill([
        'actor_id'      => null,
        'action'        => $action,
        'resource_type' => null,
        'resource_id'   => null,
        'metadata'      => [],
        'previous_hash' => $previous?->hash,
        'hash'          => hash('sha256', ($previous?->hash ?? '') . $payload),
        'created_at'    => $createdAt,
    ])->save();

    return $entry;
}

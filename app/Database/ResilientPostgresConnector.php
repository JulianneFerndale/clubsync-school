<?php

namespace App\Database;

use Illuminate\Database\Connectors\PostgresConnector;
use PDOException;

/**
 * Postgres connector that fails fast when the database (Supabase) is
 * unreachable — e.g. when there is no internet — instead of hanging on the
 * OS-default TCP timeout (~20s+ on Windows) and freezing every page.
 *
 * Two layers:
 *  1. A pre-flight TCP reachability probe (fsockopen) whose timeout is honoured
 *     on every platform, including Windows where libpq's own connect_timeout is
 *     unreliable. This bounds the worst case to a few seconds.
 *  2. connect_timeout in the DSN as a belt-and-braces fallback for platforms
 *     where libpq does respect it.
 *
 * A failed probe throws a PDOException whose message App\Support\Connectivity
 * recognises as a connectivity loss, so the offline page is served.
 */
class ResilientPostgresConnector extends PostgresConnector
{
    public function connect(array $config)
    {
        $this->ensureReachable($config);

        return parent::connect($config);
    }

    protected function getDsn(array $config)
    {
        $dsn = parent::getDsn($config);

        $timeout = (int) ($config['connect_timeout'] ?? 5);

        if ($timeout > 0 && ! str_contains($dsn, 'connect_timeout=')) {
            $dsn .= ";connect_timeout={$timeout}";
        }

        return $dsn;
    }

    /**
     * Quick TCP check that the database host accepts connections, with a
     * timeout we fully control. Throws on failure so the connection attempt
     * never blocks for the OS-default duration.
     */
    protected function ensureReachable(array $config): void
    {
        if (! function_exists('fsockopen')) {
            return; // can't probe — let the normal connect proceed
        }

        $timeout = (float) ($config['connect_timeout'] ?? 5);

        if ($timeout <= 0) {
            return;
        }

        // The host may be a comma-separated failover list; the first entry is
        // what libpq tries first, so probing it is representative.
        $host = explode(',', (string) ($config['host'] ?? '127.0.0.1'))[0];
        $port = (int) ($config['port'] ?? 5432);

        $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);

        if ($socket === false) {
            throw new PDOException(
                "SQLSTATE[08006] could not connect to server: {$errstr} (host {$host}:{$port})"
            );
        }

        fclose($socket);
    }
}

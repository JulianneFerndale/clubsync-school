<?php

namespace App\Support;

use GuzzleHttp\Exception\ConnectException as GuzzleConnectException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\ConnectionException as HttpConnectionException;
use PDOException;
use Throwable;

/**
 * Detects whether a thrown exception means the server lost its connection to a
 * required external service (the Supabase Postgres database or Firebase). When
 * the internet is down these are the failures that would otherwise surface as a
 * fatal 500 — instead we want to serve the offline page.
 */
class Connectivity
{
    /**
     * SQLSTATE codes that specifically mean the database server is unreachable
     * (connection-exception class "08" and server-unavailable class "57"), as
     * opposed to ordinary query errors like a missing table or unique violation.
     */
    private const CONNECTION_SQLSTATES = [
        '08000', '08001', '08003', '08004', '08006', '08007', '08P01',
        '57P01', '57P02', '57P03',
    ];

    /**
     * Substrings (lower-cased) found in connection-loss error messages across
     * PDO/libpq, cURL/Guzzle and the OS network layer.
     */
    private const CONNECTION_MESSAGES = [
        'could not connect',
        'connection refused',
        'could not translate host name',
        'connection timed out',
        'timeout expired',
        'no connection to the server',
        'server closed the connection',
        'could not receive data from server',
        'network is unreachable',
        'name or service not known',
        'temporary failure in name resolution',
        'getaddrinfo',
        'php_network_getaddresses',
        'ssl connection has been closed unexpectedly',
        'connection reset by peer',
        'operation timed out',
        'failed to connect',
        'could not find driver', // pgsql/pdo unavailable — treat as "cannot reach DB"
        'curl error 6',  // couldn't resolve host
        'curl error 7',  // failed to connect
        'curl error 28', // operation timed out
    ];

    /**
     * Walk the full exception chain looking for a connectivity failure.
     */
    public static function isConnectivityFailure(?Throwable $e): bool
    {
        for ($current = $e; $current !== null; $current = $current->getPrevious()) {
            if ($current instanceof HttpConnectionException
                || $current instanceof GuzzleConnectException) {
                return true;
            }

            if (($current instanceof PDOException || $current instanceof QueryException)
                && in_array((string) $current->getCode(), self::CONNECTION_SQLSTATES, true)) {
                return true;
            }

            if (self::messageIndicatesConnectionLoss($current->getMessage())) {
                return true;
            }
        }

        return false;
    }

    private static function messageIndicatesConnectionLoss(string $message): bool
    {
        $message = strtolower($message);

        foreach (self::CONNECTION_MESSAGES as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }
}

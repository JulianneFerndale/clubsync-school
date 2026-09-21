<?php

use App\Support\Connectivity;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\ConnectionException as HttpConnectionException;
use Illuminate\Support\Facades\Route;

/*
 * The "no internet connection" feature must turn a lost database/Firebase
 * connection into the offline page instead of a fatal 500.
 */

function fakeDbConnectionException(): QueryException
{
    // Mirrors what pdo_pgsql throws when Supabase is unreachable.
    $previous = new PDOException('SQLSTATE[08006] [7] could not connect to server: Network is unreachable');

    return new QueryException('pgsql', 'select * from "clubs"', [], $previous);
}

it('detects database connection-loss exceptions', function () {
    expect(Connectivity::isConnectivityFailure(fakeDbConnectionException()))->toBeTrue();
});

it('detects outbound HTTP (Firebase) connection-loss exceptions', function () {
    expect(Connectivity::isConnectivityFailure(
        new HttpConnectionException('cURL error 7: Failed to connect to identitytoolkit.googleapis.com')
    ))->toBeTrue();
});

it('does NOT treat ordinary query errors as connectivity failures', function () {
    $previous = new PDOException('SQLSTATE[42P01]: Undefined table: relation "widgets" does not exist');
    $ordinary = new QueryException('pgsql', 'select * from "widgets"', [], $previous);

    expect(Connectivity::isConnectivityFailure($ordinary))->toBeFalse();
});

it('renders the offline page (503) when a request hits a dead database', function () {
    Route::get('/__offline_probe', fn () => throw fakeDbConnectionException());

    $response = $this->get('/__offline_probe');

    $response->assertStatus(503);
    $response->assertSee('No internet connection', escape: false);
    $response->assertHeader('Retry-After', 10);
});

it('returns a 503 JSON body for API/JSON requests when offline', function () {
    Route::get('/__offline_probe_json', fn () => throw fakeDbConnectionException());

    $response = $this->getJson('/__offline_probe_json');

    $response->assertStatus(503);
    $response->assertJson(['message' => 'No internet connection. Please check your network and try again.']);
});

it('lets non-connectivity exceptions fail normally', function () {
    Route::get('/__boom', fn () => throw new RuntimeException('something else'));

    $this->get('/__boom')->assertStatus(500);
});

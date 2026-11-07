<?php

/**
 * Opt-in local HTTP load measurement: 1,000 catalog rows, 20 concurrent clients,
 * five waves (100 measured requests). Only an explicitly selected local
 * keycraft_test database is allowed. Existing tables are never reset.
 *
 * Migrate keycraft_test; export its DB_* environment variables; run:
 * php tests/catalog-load.php
 */

use App\Models\Component;
use Database\Factories\ComponentFactory;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

require __DIR__.'/../vendor/autoload.php';

final class CatalogLoadFailure extends RuntimeException {}

function requireCatalogLoad(bool $condition, string $message): void
{
    if (! $condition) {
        throw new CatalogLoadFailure($message);
    }
}

function catalogLoadRequest(string $url): array
{
    $handle = curl_init($url);
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_CONNECTTIMEOUT_MS => 1000,
        CURLOPT_TIMEOUT_MS => 5000,
        CURLOPT_PROXY => '',
    ]);
    $body = curl_exec($handle);
    $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    curl_close($handle);

    return [$status, is_string($body) ? json_decode($body, true) : null];
}

function catalogLoadWave(string $base, int $wave, int $clients): array
{
    $multi = curl_multi_init();
    curl_multi_setopt($multi, CURLMOPT_MAX_HOST_CONNECTIONS, $clients);
    $handles = [];
    $results = [];
    try {
        for ($client = 0; $client < $clients; $client++) {
            $page = (($wave * $clients + $client) % 50) + 1;
            $query = http_build_query(['per_page' => 20, 'page' => $page, 'sort' => $client % 2 === 0 ? 'name' : 'price', 'order' => $client % 3 === 0 ? 'desc' : 'asc']);
            $handle = curl_init($base.'/api/v1/components?'.$query);
            curl_setopt_array($handle, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
                CURLOPT_CONNECTTIMEOUT_MS => 2000,
                CURLOPT_TIMEOUT_MS => 10000,
                CURLOPT_FRESH_CONNECT => true,
                CURLOPT_PROXY => '',
            ]);
            $handles[] = $handle;
            curl_multi_add_handle($multi, $handle);
        }
        do {
            $result = curl_multi_exec($multi, $running);
            requireCatalogLoad($result === CURLM_OK, 'The local HTTP load client failed.');
            if ($running > 0) {
                if (curl_multi_select($multi, 1.0) === -1) {
                    usleep(1000);
                }
            }
        } while ($running > 0);

        foreach ($handles as $handle) {
            $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            $payload = json_decode(curl_multi_getcontent($handle), true);
            $valid = curl_errno($handle) === 0
                && $status === 200
                && is_array($payload)
                && count($payload['data'] ?? []) === 20
                && ($payload['meta']['total'] ?? null) === 1000;
            $results[] = [
                'status' => $status,
                'valid_catalog_page' => $valid,
                'latency_ms' => round(curl_getinfo($handle, CURLINFO_TOTAL_TIME) * 1000, 3),
            ];
        }
    } finally {
        foreach ($handles as $handle) {
            curl_multi_remove_handle($multi, $handle);
            curl_close($handle);
        }
        curl_multi_close($multi);
    }

    return $results;
}

$server = null;
$exitCode = 0;
try {
    requireCatalogLoad(getenv('DB_CONNECTION') === 'mysql', 'Set DB_CONNECTION=mysql explicitly.');
    requireCatalogLoad(getenv('DB_DATABASE') === 'keycraft_test', 'Only the isolated keycraft_test database is allowed.');
    requireCatalogLoad(in_array(getenv('DB_HOST'), ['127.0.0.1', 'localhost', '::1'], true), 'Only a local database host is allowed.');
    $app = require __DIR__.'/../bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    requireCatalogLoad(config('database.default') === 'mysql', 'The effective default connection must be MySQL.');
    requireCatalogLoad(DB::selectOne('SELECT DATABASE() AS db')->db === 'keycraft_test', 'The active database must be keycraft_test.');

    $count = Component::count();
    requireCatalogLoad($count <= 1000, 'The isolated test catalog already exceeds 1,000 rows; use another clean test database.');
    if ($count < 1000) {
        ComponentFactory::new()->count(1000 - $count)->create();
    }
    requireCatalogLoad(Component::count() === 1000, 'Could not prepare exactly 1,000 catalog rows.');

    $host = '127.0.0.1';
    $port = 8121;
    $base = 'http://'.$host.':'.$port;
    $occupied = @stream_socket_client('tcp://'.$host.':'.$port, $errorCode, $errorMessage, 0.2);
    if (is_resource($occupied)) {
        fclose($occupied);
        throw new CatalogLoadFailure('Port 8121 is already occupied; the existing process was not modified.');
    }
    $server = new Process([
        PHP_BINARY,
        '-S',
        $host.':'.$port,
        base_path('vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'),
    ], public_path(), [
        'APP_ENV' => 'testing',
        'APP_DEBUG' => 'false',
        'APP_URL' => $base,
        'CACHE_STORE' => 'array',
        'SESSION_DRIVER' => 'array',
        'LOG_LEVEL' => 'error',
    ], null, 120);
    $server->start();
    $deadline = microtime(true) + 15;
    do {
        requireCatalogLoad($server->isRunning(), 'The temporary catalog test server failed to start.');
        [$status, $payload] = catalogLoadRequest($base.'/api/v1/components?per_page=20&page=1');
        if ($status === 200 && ($payload['meta']['total'] ?? null) === 1000) {
            break;
        }
        requireCatalogLoad(microtime(true) < $deadline, 'The temporary server did not return a valid catalog page.');
        usleep(100000);
    } while (true);

    $started = microtime(true);
    $results = [];
    for ($wave = 0; $wave < 5; $wave++) {
        $results = [...$results, ...catalogLoadWave($base, $wave, 20)];
        $server->clearOutput();
        $server->clearErrorOutput();
    }
    $latencies = array_column($results, 'latency_ms');
    sort($latencies, SORT_NUMERIC);
    $validCount = count(array_filter($results, fn (array $result) => $result['valid_catalog_page']));
    $p95 = $latencies[(int) ceil(count($latencies) * 0.95) - 1];
    $report = [
        'verified_on' => (new DateTimeImmutable('now', new DateTimeZone('Asia/Qyzylorda')))->format('Y-m-d'),
        'result' => $validCount === 100 && $p95 <= 2000 ? 'passed' : 'failed',
        'database' => 'keycraft_test',
        'catalog_rows' => 1000,
        'concurrent_clients' => 20,
        'waves' => 5,
        'measured_requests' => count($results),
        'valid_200_responses' => $validCount,
        'page_size' => 20,
        'pages_exercised' => 50,
        'latency_ms' => [
            'min' => $latencies[0],
            'median' => $latencies[49],
            'p95' => $p95,
            'max' => $latencies[99],
        ],
        'observed_p95_within_2_seconds' => $p95 <= 2000,
        'total_duration_ms' => (int) round((microtime(true) - $started) * 1000),
        'environment' => [
            'php' => PHP_VERSION,
            'os' => PHP_OS_FAMILY,
            'http_server' => 'Temporary PHP built-in server on local port 8121',
            'measurement_scope' => 'Observed local result. CPU and RAM were not constrained to 2 CPU / 4 GB; formal acceptance on that hardware profile remains unverified.',
        ],
        'ai_requests_included' => false,
        'fixtures_retained' => true,
    ];
    file_put_contents(base_path('docs/catalog-load-result.json'), json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);
    echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
    requireCatalogLoad($report['result'] === 'passed', 'The observed local catalog load measurement did not meet its checks.');
} catch (Throwable $error) {
    echo json_encode([
        'result' => 'failed',
        'error' => $error instanceof CatalogLoadFailure ? $error->getMessage() : $error::class,
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
    $exitCode = 1;
} finally {
    if ($server instanceof Process && $server->isRunning()) {
        $server->stop(2);
    }
}

exit($exitCode);

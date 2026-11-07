<?php

/**
 * Opt-in integration test of real InnoDB locks on an isolated local database.
 *
 * Migrate keycraft_test first, then set DB_CONNECTION=mysql,
 * DB_DATABASE=keycraft_test and the local DB_HOST/DB_PORT/DB_USERNAME/DB_PASSWORD.
 * Run: php tests/mysql-concurrency.php
 *
 * No tables are reset or truncated. Uniquely named test fixtures are retained.
 * Worker processes use Symfony Process argument arrays, never a shell command.
 */

use App\Enums\OrderStatus;
use App\Exceptions\BusinessException;
use App\Models\Build;
use App\Models\Component;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Process\Process;

require __DIR__.'/../vendor/autoload.php';

final class ConcurrencyFailure extends RuntimeException {}

function requireConcurrency(bool $condition, string $message): void
{
    if (! $condition) {
        throw new ConcurrencyFailure($message);
    }
}

function bootConcurrencyApp(): void
{
    // Explicit environment variables are required. A normal application's .env
    // alone cannot accidentally opt a developer into writing test fixtures.
    requireConcurrency(getenv('DB_CONNECTION') === 'mysql', 'Set DB_CONNECTION=mysql explicitly.');
    requireConcurrency(getenv('DB_DATABASE') === 'keycraft_test', 'Only the isolated keycraft_test database is allowed.');
    requireConcurrency(in_array(getenv('DB_HOST'), ['127.0.0.1', 'localhost', '::1'], true), 'Only a local database host is allowed.');
    $app = require __DIR__.'/../bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    requireConcurrency(config('database.default') === 'mysql', 'The effective default connection must be MySQL.');
    requireConcurrency(DB::connection()->getDatabaseName() === 'keycraft_test', 'The effective database must be keycraft_test.');
    requireConcurrency(DB::selectOne('SELECT DATABASE() AS db')->db === 'keycraft_test', 'The active database must be keycraft_test.');
    $engines = DB::select("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('users','builds','build_items','components','orders','order_items')");
    requireConcurrency(count($engines) === 6, 'Migrate the test database before running this script.');
    foreach ($engines as $engine) {
        requireConcurrency(strcasecmp($engine->ENGINE, 'InnoDB') === 0, 'All checkout tables must use InnoDB.');
    }
}

function emitConcurrency(array $data): void
{
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
    flush();
}

function concurrencyWorker(array $job): void
{
    emitConcurrency(['ready' => true]);
    $deadline = microtime(true) + 25;
    while (! is_file($job['gate'])) {
        if (microtime(true) > $deadline) {
            throw new ConcurrencyFailure('The worker release gate timed out.');
        }
        usleep(10000);
        clearstatcache(true, $job['gate']);
    }
    try {
        if ($job['action'] === 'create') {
            $order = app(OrderService::class)->create(
                User::findOrFail($job['user_id']),
                ['build_id' => $job['build_id'], 'expected_total' => $job['expected_total']],
                $job['key'],
            );
            $status = $order->wasRecentlyCreated ? 201 : 200;
        } elseif ($job['action'] === 'cancel') {
            $order = app(OrderService::class)->updateStatus(Order::findOrFail($job['order_id']), OrderStatus::Cancelled);
            $status = 200;
        } else {
            throw new ConcurrencyFailure('Unknown worker action.');
        }
        emitConcurrency(['status' => $status, 'order_id' => $order->id, 'total' => $order->total]);
    } catch (BusinessException $error) {
        emitConcurrency(['status' => $error->status, 'error' => $error->getMessage()]);
    } catch (HttpExceptionInterface $error) {
        emitConcurrency(['status' => $error->getStatusCode(), 'error' => 'HTTP exception']);
    } catch (Throwable $error) {
        // Never print a database connection string, credentials or a stack trace.
        emitConcurrency(['status' => 500, 'exception' => $error::class]);
    }
}

function makeConcurrencyFixture(string $label, int $buyers): array
{
    return DB::transaction(function () use ($label, $buyers): array {
        $prefix = 'concurrency-'.$label.'-'.Str::uuid();
        $definitions = [
            'case' => [10000, '65', ['mount' => 'gasket'], 1],
            'pcb' => [7000, '65', ['mount' => 'gasket', 'switch_type' => 'mx', 'layout' => 'ansi', 'key_count' => 67, 'stabilizers' => ['2u' => 3, '6.25u' => 1]], 1],
            'plate' => [4000, '65', ['mount' => 'gasket', 'switch_type' => 'mx'], 1],
            'stabilizer' => [2000, null, ['stabilizers' => ['2u' => 4, '6.25u' => 1]], 1],
            'switch' => [100, null, ['switch_type' => 'mx', 'stem' => 'mx'], 67],
            'keycap' => [5000, null, ['stem' => 'mx', 'layout' => 'ansi', 'key_count' => 104], 1],
        ];
        $components = [];
        foreach ($definitions as $type => [$price, $format, $specs, $quantity]) {
            $components[$type] = Component::create([
                'name' => $prefix.'-'.$type,
                'type' => $type,
                'price' => $price,
                'stock' => $quantity,
                'format' => $format,
                'specs' => $specs,
            ]);
        }
        $jobs = [];
        for ($index = 0; $index < $buyers; $index++) {
            $user = User::create([
                'name' => $prefix.'-'.$index,
                'email' => $prefix.'-'.$index.'@example.test',
                'password' => Str::random(32),
                'role' => 'buyer',
            ]);
            $build = Build::create(['user_id' => $user->id, 'name' => $prefix, 'budget' => 50000]);
            foreach ($components as $type => $component) {
                $build->items()->create(['component_id' => $component->id, 'quantity' => $definitions[$type][3]]);
            }
            $jobs[] = ['action' => 'create', 'user_id' => $user->id, 'build_id' => $build->id, 'expected_total' => 34700, 'key' => $prefix.'-'.$index];
        }

        return ['prefix' => $prefix, 'jobs' => $jobs, 'components' => $components];
    });
}

function runConcurrencyPair(array $jobs): array
{
    $directory = storage_path('framework/testing/concurrency');
    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }
    $gate = $directory.DIRECTORY_SEPARATOR.Str::uuid().'.gate';
    $workers = [];
    try {
        foreach ($jobs as $job) {
            $job['gate'] = $gate;
            $process = new Process([
                PHP_BINARY,
                __FILE__,
                '--worker',
                base64_encode(json_encode($job, JSON_THROW_ON_ERROR)),
            ], base_path(), ['APP_ENV' => 'testing', 'APP_DEBUG' => 'false'], null, 40);
            $process->start();
            $workers[] = $process;
        }
        $deadline = microtime(true) + 20;
        do {
            $allReady = true;
            foreach ($workers as $process) {
                $process->checkTimeout();
                if (! str_contains($process->getOutput(), '"ready":true')) {
                    requireConcurrency($process->isRunning(), 'A worker exited before becoming ready.');
                    $allReady = false;
                }
            }
            requireConcurrency(microtime(true) < $deadline, 'Worker startup timed out.');
            if (! $allReady) {
                usleep(10000);
            }
        } while (! $allReady);
        file_put_contents($gate, 'release');
        $results = [];
        foreach ($workers as $process) {
            $process->wait();
            requireConcurrency($process->isSuccessful(), 'A worker failed before producing its result.');
            $result = null;
            // Match physical CR/LF only: byte-oriented \R also matches 0x85,
            // which can be a continuation byte inside UTF-8 Cyrillic messages.
            foreach (preg_split('/\r\n|\n|\r/', trim($process->getOutput())) as $line) {
                $decoded = json_decode($line, true);
                if (is_array($decoded) && isset($decoded['status'])) {
                    $result = $decoded;
                }
            }
            requireConcurrency($result !== null, 'A worker did not return a structured result.');
            $results[] = $result;
        }

        return $results;
    } finally {
        foreach ($workers as $process) {
            if ($process->isRunning()) {
                $process->stop(1);
            }
        }
        if (is_file($gate)) {
            unlink($gate);
        }
    }
}

function expectConcurrencyStatuses(array $results, array $expected, string $label): void
{
    $statuses = array_column($results, 'status');
    sort($statuses);
    sort($expected);
    requireConcurrency($statuses === $expected, $label.': unexpected worker results '.json_encode($results, JSON_UNESCAPED_UNICODE));
}

try {
    bootConcurrencyApp();
    if (($argv[1] ?? '') === '--worker') {
        concurrencyWorker(json_decode(base64_decode($argv[2], true), true, flags: JSON_THROW_ON_ERROR));
        exit(0);
    }

    $started = microtime(true);
    $race = makeConcurrencyFixture('stock', 2);
    $raceResults = runConcurrencyPair($race['jobs']);
    expectConcurrencyStatuses($raceResults, [201, 409], 'Last-stock race');
    $raceUsers = array_column($race['jobs'], 'user_id');
    requireConcurrency(Order::whereIn('user_id', $raceUsers)->count() === 1, 'The last-stock race created more than one order.');
    foreach ($race['components'] as $component) {
        requireConcurrency($component->fresh()->stock === 0, 'The last-stock race left incorrect inventory.');
    }

    $idempotency = makeConcurrencyFixture('idempotency', 1);
    $sameJob = $idempotency['jobs'][0];
    $idempotencyResults = runConcurrencyPair([$sameJob, $sameJob]);
    expectConcurrencyStatuses($idempotencyResults, [201, 200], 'Concurrent idempotency');
    requireConcurrency($idempotencyResults[0]['order_id'] === $idempotencyResults[1]['order_id'], 'Concurrent retries returned different orders.');
    requireConcurrency(Order::where('user_id', $sameJob['user_id'])->count() === 1, 'Concurrent retries created duplicate orders.');
    foreach ($idempotency['components'] as $component) {
        requireConcurrency($component->fresh()->stock === 0, 'Concurrent retries changed inventory more than once.');
    }

    $cancel = ['action' => 'cancel', 'order_id' => $idempotencyResults[0]['order_id']];
    $cancellationResults = runConcurrencyPair([$cancel, $cancel]);
    expectConcurrencyStatuses($cancellationResults, [200, 409], 'Concurrent cancellation');
    requireConcurrency(Order::findOrFail($cancel['order_id'])->status === OrderStatus::Cancelled, 'The order was not cancelled.');
    foreach ($idempotency['components'] as $type => $component) {
        requireConcurrency($component->fresh()->stock === ($type === 'switch' ? 67 : 1), 'Concurrent cancellation returned inventory more than once.');
    }

    emitConcurrency([
        'result' => 'passed',
        'database' => 'keycraft_test',
        'engine' => 'InnoDB',
        'scenarios' => [
            'last_stock' => $raceResults,
            'idempotency' => $idempotencyResults,
            'cancel_once' => $cancellationResults,
        ],
        'duration_ms' => (int) round((microtime(true) - $started) * 1000),
        'fixtures_retained' => [$race['prefix'], $idempotency['prefix']],
    ]);
} catch (Throwable $error) {
    emitConcurrency([
        'result' => 'failed',
        'error' => $error instanceof ConcurrencyFailure ? $error->getMessage() : $error::class,
    ]);
    exit(1);
}

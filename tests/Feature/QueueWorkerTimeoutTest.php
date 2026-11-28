<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\Process;

test('a queue worker stops a job that exceeds its timeout', function () {
    $directory = sys_get_temp_dir().'/farmwell-queue-timeout-'.bin2hex(random_bytes(8));
    mkdir($directory);
    $database = $directory.'/queue.sqlite';
    touch($database);
    $started = $directory.'/started';
    $finished = $directory.'/finished';

    try {
        config([
            'database.connections.queue_timeout_test' => ['driver' => 'sqlite', 'database' => $database, 'prefix' => ''],
            'queue.connections.database.connection' => 'queue_timeout_test',
        ]);
        Schema::connection('queue_timeout_test')->create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        $job = static function () use ($started, $finished) {
            file_put_contents($started, 'started');
            sleep(5);
            file_put_contents($finished, 'finished');
        };
        dispatch($job->bindTo(null, null))->onConnection('database');

        $worker = new Process([PHP_BINARY, 'artisan', 'queue:work', 'database', '--stop-when-empty', '--timeout=1', '--tries=1', '--no-interaction'], base_path(), [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => $database,
            'DB_URL' => '',
            'QUEUE_FAILED_DRIVER' => 'null',
            'CACHE_STORE' => 'array',
            'LOG_CHANNEL' => 'stderr',
        ], timeout: 8);
        try {
            $worker->run();
        } catch (ProcessSignaledException $exception) {
            expect($worker->getTermSignal())->toBe(SIGKILL);
        }

        expect(file_exists($started))->toBeTrue()
            ->and(file_exists($finished))->toBeFalse()
            ->and($worker->isSuccessful())->toBeFalse();
    } finally {
        DB::disconnect('queue_timeout_test');
        foreach (glob($directory.'/*') as $file) {
            unlink($file);
        }
        rmdir($directory);
    }
});

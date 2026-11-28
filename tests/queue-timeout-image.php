<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

putenv('APP_ENV=testing');
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=/tmp/queue-timeout.sqlite');
putenv('DB_URL=');
putenv('QUEUE_CONNECTION=database');
putenv('QUEUE_FAILED_DRIVER=null');
putenv('CACHE_STORE=array');
putenv('LOG_CHANNEL=stderr');

touch('/tmp/queue-timeout.sqlite');
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
set_exception_handler(static function (Throwable $exception): void {
    fwrite(STDERR, $exception."\n");
    exit(1);
});

Schema::create('jobs', function (Blueprint $table) {
    $table->id();
    $table->string('queue')->index();
    $table->longText('payload');
    $table->unsignedTinyInteger('attempts');
    $table->unsignedInteger('reserved_at')->nullable();
    $table->unsignedInteger('available_at');
    $table->unsignedInteger('created_at');
});

dispatch(static function () {
    file_put_contents('/tmp/queue-timeout-started', 'started');
    sleep(5);
    file_put_contents('/tmp/queue-timeout-finished', 'finished');
})->onConnection('database');

$worker = new Process([
    PHP_BINARY, 'artisan', 'queue:work', 'database', '--stop-when-empty', '--timeout=1', '--tries=1', '--no-interaction',
], '/var/www/html', timeout: 8);

try {
    $worker->run();
} catch (ProcessSignaledException) {
    if ($worker->getTermSignal() !== SIGKILL) {
        fwrite(STDERR, "Queue worker terminated with an unexpected signal.\n");
        exit(1);
    }
} catch (ProcessTimedOutException) {
    fwrite(STDERR, "Queue worker did not stop within the test deadline.\n".$worker->getOutput().$worker->getErrorOutput());
    exit(1);
}

if (! extension_loaded('pcntl') || ! file_exists('/tmp/queue-timeout-started') || file_exists('/tmp/queue-timeout-finished') || $worker->isSuccessful()) {
    fwrite(STDERR, "Queue timeout verification failed.\n".$worker->getOutput().$worker->getErrorOutput());
    exit(1);
}

fwrite(STDOUT, "Queue worker terminated the overlong job before completion.\n");

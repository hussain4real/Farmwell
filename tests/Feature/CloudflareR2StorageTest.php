<?php

use App\Providers\AppServiceProvider;
use Aws\MockHandler;
use Aws\Result;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

test('private R2 uploads and copies omit unsupported ACL operations', function () {
    $handler = new MockHandler;
    $handler->append(new Result(['ObjectURL' => 'https://example.test/file']));
    $handler->append(new Result(['ContentLength' => 12]));
    $handler->append(new Result(['CopyObjectResult' => ['ETag' => 'test']]));
    $disk = Storage::build([
        'driver' => 'r2',
        'key' => 'test',
        'secret' => 'test',
        'region' => 'auto',
        'bucket' => 'private-test',
        'endpoint' => 'https://example.test',
        'use_path_style_endpoint' => true,
        'retain_visibility' => false,
        'throw' => true,
        'handler' => $handler,
    ]);

    expect($disk->put('source.txt', 'private test'))->toBeTrue();
    $upload = $handler->getLastCommand();
    expect($upload->getName())->toBe('PutObject')->and(isset($upload['ACL']))->toBeFalse();

    expect($disk->copy('source.txt', 'copy.txt'))->toBeTrue();
    $copy = $handler->getLastCommand();
    expect($copy->getName())->toBe('CopyObject')->and(isset($copy['ACL']))->toBeFalse();
});

test('R2 rejects an incompatible filesystem adapter', function () {
    $manager = Mockery::mock(FilesystemManager::class, [app()])->makePartial();
    $manager->shouldReceive('build')->once()->andReturn(Mockery::mock(FilesystemAdapter::class));
    app()->instance('filesystem', $manager);
    Facade::clearResolvedInstance('filesystem');
    (new AppServiceProvider(app()))->boot();
    config(['filesystems.disks.invalid-r2' => ['driver' => 'r2']]);

    expect(fn () => Storage::disk('invalid-r2'))->toThrow(LogicException::class);
});

test('a configured proxy preserves the HTTPS staging host', function () {
    config(['trustedproxy.proxies' => '*']);
    Route::get('/_proxy-check', fn () => response()->json(['url' => url('/')]));

    $this->withHeaders([
        'X-Forwarded-Host' => 'staging.example.test',
        'X-Forwarded-Proto' => 'https',
    ])->get('/_proxy-check')->assertOk()->assertJson(['url' => 'https://staging.example.test']);
});

<?php

namespace App\Providers;

use Aws\CommandInterface;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use LogicException;
use Psr\Http\Message\RequestInterface;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach (['invitations' => 10, 'approval-decisions' => 20, 'report-generation' => 10, 'report-downloads' => 60, 'ai-generation' => 10] as $name => $attempts) {
            RateLimiter::for($name, fn (Request $request) => Limit::perMinute($attempts)->by((string) $request->user()->getAuthIdentifier()));
        }

        Storage::extend('r2', function ($app, array $config) {
            $disk = Storage::build([...$config, 'driver' => 's3']);
            if (! $disk instanceof AwsS3V3Adapter) {
                throw new LogicException('The R2 filesystem requires an S3 adapter.');
            }
            $disk->getClient()->getHandlerList()->appendInit(
                fn (callable $handler) => function (CommandInterface $command, ?RequestInterface $request = null) use ($handler) {
                    unset($command['ACL']);

                    return $handler($command, $request);
                },
                'r2-no-acl',
            );

            return $disk;
        });
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}

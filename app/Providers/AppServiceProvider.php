<?php

namespace App\Providers;

use App\Enums\PlatformRole;
use App\Http\Middleware\SignalQueuedWork;
use App\Listeners\RecordAiUsage;
use App\Models\User;
use App\Support\AuthenticatedDatabaseSessionHandler;
use Aws\CommandInterface;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Ai\Events\AgentFailed;
use Laravel\Ai\Events\AgentPrompted;
use Laravel\Ai\Events\AgentStreamed;
use Laravel\Ai\Events\PromptingAgent;
use Laravel\Ai\Events\StreamingAgent;
use LogicException;
use Psr\Http\Message\RequestInterface;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(RecordAiUsage::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('managePlatform', fn (User $user): bool => $user->platform_role === PlatformRole::SuperAdmin);

        Event::listen([PromptingAgent::class, StreamingAgent::class], [RecordAiUsage::class, 'handleStarting']);
        Event::listen([AgentPrompted::class, AgentStreamed::class], [RecordAiUsage::class, 'handleCompleted']);
        Event::listen(AgentFailed::class, [RecordAiUsage::class, 'handleFailed']);

        Event::listen(function (JobQueued $event): void {
            if ($event->connectionName === 'database' && $this->app->bound('request')) {
                $request = request();
                DB::connection(config('queue.connections.database.connection'))->afterCommit(
                    fn () => $request->attributes->set(SignalQueuedWork::Attribute, true),
                );
            }
        });

        Session::extend('database', fn ($app) => new AuthenticatedDatabaseSessionHandler(
            DB::connection(config('session.connection')),
            (string) config('session.table'),
            (int) config('session.lifetime'),
            $app,
        ));

        foreach (['platform-admin' => 20, 'invitations' => 10, 'approval-decisions' => 20, 'report-generation' => 10, 'report-downloads' => 60, 'ai-generation' => 10, 'ai-assistant' => 20] as $name => $attempts) {
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

<?php

use App\Actions\AiSummaries\InvestorSummaryEligibility;
use App\Ai\Agents\InternalFarmSummary;
use App\Ai\Agents\InvestorProgressSummary;
use App\Jobs\GenerateAiSummary;
use App\Models\AiSummary;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\InvestorAgreement;
use App\Models\Team;
use App\Models\User;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Queue\Jobs\FakeJob;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;

function reliabilitySummary(): AiSummary
{
    $team = Team::factory()->create();
    $farm = Farm::factory()->create(['team_id' => $team->id]);
    FarmActivity::factory()->create(['team_id' => $team->id, 'farm_id' => $farm->id, 'activity_date' => now(), 'investor_safe_summary' => 'Synthetic field activity']);

    return AiSummary::factory()->create(['team_id' => $team->id, 'investor_agreement_id' => null, 'farm_id' => $farm->id, 'type' => 'weekly']);
}

function providerRequestFailure(int $status, string $code = ''): RequestException
{
    return new RequestException(new Response(new PsrResponse($status, ['Content-Type' => 'application/json'], json_encode(['error' => ['code' => $code, 'type' => $code]], JSON_THROW_ON_ERROR))));
}

test('transient provider failures are returned to the queue for retry', function (Throwable $exception) {
    $summary = reliabilitySummary();
    $job = (new GenerateAiSummary($summary->id))->withFakeQueueInteractions();
    InternalFarmSummary::fake(fn () => throw $exception);

    expect(fn () => $job->handle())->toThrow($exception::class);
    $job->assertNotFailed();
    expect($summary->fresh()->status)->toBe('queued');
})->with([
    fn () => ProviderConnectionException::forProvider('openai'),
    fn () => ProviderOverloadedException::forProvider('openai'),
    fn () => RateLimitedException::forProvider('openai', 429, providerRequestFailure(429, 'rate_limit_exceeded')),
    fn () => providerRequestFailure(500),
    fn () => providerRequestFailure(408),
]);

test('permanent provider failures fail the job without retry', function (Throwable $exception) {
    $summary = reliabilitySummary();
    $job = (new GenerateAiSummary($summary->id))->withFakeQueueInteractions();
    InternalFarmSummary::fake(fn () => throw $exception);

    $job->handle();
    $job->assertFailedWith($exception::class);
})->with([
    fn () => providerRequestFailure(401, 'invalid_api_key'),
    fn () => providerRequestFailure(403, 'permission_denied'),
    fn () => RateLimitedException::forProvider('openai', 429, providerRequestFailure(429, 'insufficient_quota')),
    fn () => RateLimitedException::forProvider('openai', 429, providerRequestFailure(429, 'billing_hard_limit_reached')),
]);

test('a transient failure can be retried to produce a draft', function () {
    $summary = reliabilitySummary();
    $job = (new GenerateAiSummary($summary->id))->withFakeQueueInteractions();
    $attempt = 0;
    InternalFarmSummary::fake(function () use (&$attempt): string {
        if (++$attempt === 1) {
            throw ProviderConnectionException::forProvider('openai');
        }

        return 'Recovered draft';
    });

    expect(fn () => $job->handle())->toThrow(ProviderConnectionException::class);
    $job->handle();
    expect($summary->fresh()->status)->toBe('draft')
        ->and($summary->fresh()->generated_text)->toBe('Recovered draft')
        ->and($attempt)->toBe(2);
});

test('overlapping generations preserve the winning draft and its source snapshot', function (bool $oldAttemptFinishesFirst) {
    $team = Team::factory()->create();
    $farm = Farm::factory()->create(['team_id' => $team->id]);
    $agreement = InvestorAgreement::factory()->create(['team_id' => $team->id, 'farm_id' => $farm->id, 'investor_id' => User::factory()->create()->id]);
    $activity = FarmActivity::factory()->create(['team_id' => $team->id, 'farm_id' => $farm->id, 'investor_safe_summary' => 'Old source']);
    $summary = AiSummary::factory()->create(['team_id' => $team->id, 'investor_agreement_id' => $agreement->id]);
    InvestorProgressSummary::fake(fn () => Fiber::suspend());

    $oldAttempt = new Fiber(fn () => (new GenerateAiSummary($summary->id))->handle());
    $newAttempt = new Fiber(fn () => (new GenerateAiSummary($summary->id))->handle());
    $oldAttempt->start();
    $activity->update(['investor_safe_summary' => 'New source']);
    $newAttempt->start();

    if ($oldAttemptFinishesFirst) {
        $oldAttempt->resume('Draft based on old source');
        $newAttempt->resume('Draft based on new source');
    } else {
        $newAttempt->resume('Draft based on new source');
        $oldAttempt->resume('Draft based on old source');
    }

    $summary->refresh();
    $expectedSource = $oldAttemptFinishesFirst ? 'Old source' : 'New source';
    expect($summary->status)->toBe('draft')
        ->and($summary->generated_text)->toBe($oldAttemptFinishesFirst ? 'Draft based on old source' : 'Draft based on new source')
        ->and($summary->source_snapshot[0]['summary'])->toBe($expectedSource)
        ->and($summary->prompt)->toContain($expectedSource)
        ->and(app(InvestorSummaryEligibility::class)->isCurrent($summary, $agreement))->toBe(! $oldAttemptFinishesFirst);
})->with([true, false]);

test('queue reservation must outlast a summary job', function () {
    $summary = reliabilitySummary();
    $job = (new GenerateAiSummary($summary->id))->withFakeQueueInteractions();
    $fakeJob = Mockery::mock(FakeJob::class)->makePartial();
    $fakeJob->shouldReceive('getConnectionName')->andReturn('database');
    $job->job = $fakeJob;
    config(['queue.connections.database.retry_after' => $job->timeout]);
    InternalFarmSummary::fake();

    $job->handle();
    $job->assertFailedWith(RuntimeException::class);
    InternalFarmSummary::assertNeverPrompted();
});

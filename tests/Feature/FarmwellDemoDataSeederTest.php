<?php

use App\Actions\AiSummaries\InvestorSummaryEligibility;
use App\Actions\Finance\ResolveTeamFinanceCurrency;
use App\Models\AiSummary;
use App\Models\Farm;
use App\Models\Team;
use Database\Seeders\FarmwellDemoDataSeeder;
use Database\Seeders\FarmwellTestingUsersSeeder;

test('local demo data is representative, scoped and idempotent', function () {
    $this->seed(FarmwellTestingUsersSeeder::class);
    $this->seed(FarmwellDemoDataSeeder::class);
    $this->seed(FarmwellDemoDataSeeder::class);
    $team = Team::query()->where('slug', FarmwellTestingUsersSeeder::TeamSlug)->sole();
    $farm = Farm::query()->where('team_id', $team->id)->where('name', FarmwellDemoDataSeeder::FarmName)->sole();
    $summary = AiSummary::query()->where('team_id', $team->id)->where('status', 'published')->sole();

    expect($farm->productionCycles()->count())->toBe(1)
        ->and($farm->productionUnits()->count())->toBe(1)
        ->and($team->farmActivities()->count())->toBe(1)
        ->and($team->farmTasks()->count())->toBe(1)
        ->and($team->whatsappIntakes()->count())->toBe(1)
        ->and($team->budgets()->count())->toBe(1)
        ->and($team->expenses()->count())->toBe(2)
        ->and($team->fundingPhases()->count())->toBe(1)
        ->and($team->externalTransfers()->count())->toBe(1)
        ->and($team->harvestRecords()->count())->toBe(1)
        ->and($team->saleRecords()->count())->toBe(1)
        ->and($team->distributionRecords()->count())->toBe(1)
        ->and(app(InvestorSummaryEligibility::class)->isCurrent($summary, $summary->investorAgreement))->toBeTrue();
});

test('demo data seeder does nothing outside local and test environments', function () {
    $this->app->detectEnvironment(fn () => 'production');
    try {
        app(FarmwellDemoDataSeeder::class)->run(app(ResolveTeamFinanceCurrency::class));
        expect(Farm::query()->count())->toBe(0);
    } finally {
        $this->app->detectEnvironment(fn () => 'testing');
    }
});

<?php

use App\Actions\Investors\BuildInvestorCycleSnapshot;
use App\Enums\ApprovalRequestStatus;
use App\Enums\CropGrowthStage;
use App\Enums\FarmType;
use App\Enums\InvestorAgreementStatus;
use App\Enums\InvestorVisibilityStatus;
use App\Enums\PestPressure;
use App\Enums\TeamRole;
use App\Models\ApprovalRequest;
use App\Models\AuditEvent;
use App\Models\Commodity;
use App\Models\DistributionRecord;
use App\Models\Expense;
use App\Models\Farm;
use App\Models\FundingPhase;
use App\Models\HarvestRecord;
use App\Models\InvestorAgreement;
use App\Models\ProductionCycle;
use App\Models\ProductionLog;
use App\Models\SaleRecord;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

test('farm owners can update agreement lifecycle terms and cross-farm references are blocked', function () {
    $owner = User::factory()->create();
    $investor = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($investor, ['role' => TeamRole::Investor->value]);
    $owner->switchTeam($team);
    $investor->switchTeam($team);

    $farm = Farm::factory()->create(['team_id' => $team->id, 'farm_type' => FarmType::Aquaculture]);
    $otherFarm = Farm::factory()->create(['team_id' => $team->id, 'farm_type' => FarmType::Crop]);
    $cycle = ProductionCycle::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'farm_type' => FarmType::Aquaculture,
        'name' => 'Catfish Batch 2',
    ]);
    $otherFarmCycle = ProductionCycle::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $otherFarm->id,
        'farm_type' => FarmType::Crop,
    ]);

    $agreement = InvestorAgreement::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'investor_id' => $investor->id,
        'production_cycle_id' => null,
        'status' => InvestorAgreementStatus::Draft,
        'title' => 'Draft Fish Agreement',
        'currency' => 'NGN',
        'amount_committed_minor' => 100_000,
        'amount_funded_minor' => 0,
        'investor_profit_share_percentage' => 40,
        'farm_profit_share_percentage' => 60,
    ]);

    $updateUrl = route('investors.agreements.update', [
        'current_team' => $team,
        'investor_agreement' => $agreement,
    ]);

    $this->actingAs($investor)
        ->patch($updateUrl, [
            'title' => 'Investor cannot edit',
            'status' => InvestorAgreementStatus::Active->value,
            'amount_committed' => '1500.00',
            'capital_recovery_rule' => 'capital_first',
            'investor_profit_share_percentage' => 50,
            'farm_profit_share_percentage' => 50,
        ])
        ->assertForbidden();

    $this->actingAs($owner)
        ->patch($updateUrl, [
            'title' => 'Invalid cycle scope',
            'status' => InvestorAgreementStatus::Active->value,
            'production_cycle_id' => $otherFarmCycle->id,
            'amount_committed' => '1500.00',
            'capital_recovery_rule' => 'capital_first',
            'investor_profit_share_percentage' => 50,
            'farm_profit_share_percentage' => 50,
        ])
        ->assertSessionHasErrors('production_cycle_id');

    $this->actingAs($owner)
        ->patch($updateUrl, [
            'title' => 'Invalid profit sum',
            'status' => InvestorAgreementStatus::Active->value,
            'amount_committed' => '1500.00',
            'capital_recovery_rule' => 'capital_first',
            'investor_profit_share_percentage' => 55,
            'farm_profit_share_percentage' => 40,
        ])
        ->assertSessionHasErrors('investor_profit_share_percentage');

    $this->actingAs($owner)
        ->patch($updateUrl, [
            'title' => 'Active Catfish Agreement',
            'status' => InvestorAgreementStatus::Active->value,
            'production_cycle_id' => $cycle->id,
            'currency' => 'ngn',
            'amount_committed' => '2500.00',
            'amount_funded' => '1250.00',
            'capital_recovery_rule' => 'capital_first',
            'investor_profit_share_percentage' => 45,
            'farm_profit_share_percentage' => 55,
            'funding_model' => 'Tranche-based releases',
            'role_responsibilities' => 'Operator runs ponds; investor approves milestones.',
            'public_notes' => 'Updated public scope.',
            'internal_notes' => 'Updated private admin memo.',
            'starts_on' => '2026-10-01',
            'ends_on' => '2026-12-31',
        ])
        ->assertRedirect(route('investors.index', ['current_team' => $team]));

    $agreement->refresh();

    expect($agreement->title)->toBe('Active Catfish Agreement')
        ->and($agreement->status)->toBe(InvestorAgreementStatus::Active)
        ->and($agreement->production_cycle_id)->toBe($cycle->id)
        ->and($agreement->currency)->toBe('NGN')
        ->and($agreement->amount_committed_minor)->toBe(250_000)
        ->and($agreement->amount_funded_minor)->toBe(125_000)
        ->and($agreement->investor_profit_share_percentage)->toBe(45)
        ->and($agreement->farm_profit_share_percentage)->toBe(55)
        ->and($agreement->funding_model)->toBe('Tranche-based releases')
        ->and($agreement->public_notes)->toBe('Updated public scope.');

    expect(AuditEvent::query()->where('team_id', $team->id)->where('action', 'investor_agreement.updated')->exists())->toBeTrue();
});

test('investor portal exposes investor-safe cycle telemetry across poultry aquaculture and crop without leaking internal notes', function () {
    $team = Team::factory()->create();
    $investor = User::factory()->create();
    $team->members()->attach($investor, ['role' => TeamRole::Investor->value]);
    $investor->switchTeam($team);

    $poultryFarm = Farm::factory()->create(['team_id' => $team->id, 'farm_type' => FarmType::Poultry, 'name' => 'Layer Farm']);
    $poultryCycle = ProductionCycle::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $poultryFarm->id,
        'farm_type' => FarmType::Poultry,
        'name' => 'Layers Flock A',
        'planned_start_on' => now()->subDays(20),
        'planned_end_on' => now()->addDays(20),
        'initial_stock_count' => 1000,
        'current_stock_count' => 980,
        'initial_average_weight_g' => '1200.00',
    ]);
    ProductionLog::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $poultryFarm->id,
        'production_cycle_id' => $poultryCycle->id,
        'log_date' => now()->subDay(),
        'mortality_count' => 5,
        'feed_used_kg' => '80.00',
        'average_weight_g' => '1800.00',
        'eggs_collected' => 900,
        'disease_observed' => 'CONFIDENTIAL_DISEASE_STRING',
        'notes' => 'CONFIDENTIAL_OPERATOR_NOTE',
    ]);
    InvestorAgreement::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $poultryFarm->id,
        'production_cycle_id' => $poultryCycle->id,
        'investor_id' => $investor->id,
        'status' => InvestorAgreementStatus::Active,
        'title' => 'Poultry Agreement',
    ]);

    $fishFarm = Farm::factory()->create(['team_id' => $team->id, 'farm_type' => FarmType::Aquaculture, 'name' => 'Catfish Farm']);
    $fishCycle = ProductionCycle::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $fishFarm->id,
        'farm_type' => FarmType::Aquaculture,
        'name' => 'Pond 1',
        'planned_start_on' => now()->subDays(10),
        'planned_end_on' => null,
        'initial_stock_count' => 500,
        'current_stock_count' => 490,
    ]);
    ProductionLog::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $fishFarm->id,
        'production_cycle_id' => $fishCycle->id,
        'log_date' => now()->subDays(2),
        'water_ph' => '7.20',
        'water_temp_c' => '28.00',
        'dissolved_oxygen_mg_l' => '5.50',
        'ammonia_mg_l' => '0.100',
        'water_exchanged' => true,
        'feed_used_kg' => '15.00',
        'average_weight_g' => '300.00',
    ]);
    ProductionLog::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $fishFarm->id,
        'production_cycle_id' => $fishCycle->id,
        'log_date' => now()->subDay(),
        'water_ph' => '5.80',
        'water_temp_c' => '22.00',
        'dissolved_oxygen_mg_l' => '2.50',
        'ammonia_mg_l' => '1.500',
    ]);
    InvestorAgreement::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $fishFarm->id,
        'production_cycle_id' => $fishCycle->id,
        'investor_id' => $investor->id,
        'status' => InvestorAgreementStatus::Active,
        'title' => 'Fish Agreement',
    ]);

    $cropFarm = Farm::factory()->create(['team_id' => $team->id, 'farm_type' => FarmType::Crop, 'name' => 'Pepper Farm']);
    $cropCycle = ProductionCycle::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $cropFarm->id,
        'farm_type' => FarmType::Crop,
        'name' => 'Pepper Season',
        'current_growth_stage' => CropGrowthStage::Flowering,
    ]);
    ProductionLog::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $cropFarm->id,
        'production_cycle_id' => $cropCycle->id,
        'log_date' => now()->subDay(),
        'rainfall_mm' => '14.50',
        'irrigation_applied' => true,
        'pest_pressure' => PestPressure::Low,
    ]);
    HarvestRecord::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $cropFarm->id,
        'production_cycle_id' => $cropCycle->id,
        'investor_visibility_status' => InvestorVisibilityStatus::Private,
        'quantity' => '9999.00',
    ]);
    InvestorAgreement::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $cropFarm->id,
        'production_cycle_id' => $cropCycle->id,
        'investor_id' => $investor->id,
        'status' => InvestorAgreementStatus::Active,
        'title' => 'Crop Agreement',
    ]);

    $response = $this->actingAs($investor)->get(route('investor-portal.index', $team))
        ->assertOk()
        ->assertDontSee('CONFIDENTIAL_DISEASE_STRING')
        ->assertDontSee('CONFIDENTIAL_OPERATOR_NOTE')
        ->assertDontSee('9999.00');

    $response->assertInertia(function (Assert $page): void {
        $agreements = collect($page->toArray()['props']['agreements'])->keyBy('title');

        expect($agreements['Poultry Agreement']['cycleSnapshot']['domain'])->toBe('poultry')
            ->and($agreements['Poultry Agreement']['cycleSnapshot']['poultry']['totalEggs'])->toBe(900)
            ->and($agreements['Poultry Agreement']['cycleSnapshot']['poultry']['totalCrates'])->toBe(30)
            ->and($agreements['Poultry Agreement']['cycleSnapshot']['feeding']['fcr'])->toBeGreaterThan(0)
            ->and($agreements['Fish Agreement']['cycleSnapshot']['domain'])->toBe('aquaculture')
            ->and($agreements['Fish Agreement']['cycleSnapshot']['progressPercent'])->toBe(0)
            ->and($agreements['Fish Agreement']['cycleSnapshot']['aquaculture']['waterStatus'])->toBe('critical')
            ->and($agreements['Fish Agreement']['cycleSnapshot']['aquaculture']['waterExchangeCount'])->toBe(1)
            ->and($agreements['Crop Agreement']['cycleSnapshot']['domain'])->toBe('crop')
            ->and($agreements['Crop Agreement']['cycleSnapshot']['crop']['stageProgressPercent'])->toBe(70)
            ->and($agreements['Crop Agreement']['cycleSnapshot']['crop']['irrigationCount'])->toBe(1)
            ->and($agreements['Crop Agreement']['cycleSnapshot']['crop']['latestPestPressure'])->toBe('low');
    });

    // Cover warning and unknown water statuses on aquaculture snapshots directly
    $optimalCycle = ProductionCycle::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $fishFarm->id,
        'farm_type' => FarmType::Aquaculture,
        'planned_end_on' => null,
    ]);
    $snapshotAction = app(BuildInvestorCycleSnapshot::class);
    expect($snapshotAction->handle($optimalCycle)['aquaculture']['waterStatus'])->toBe('unknown');

    ProductionLog::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $fishFarm->id,
        'production_cycle_id' => $optimalCycle->id,
        'water_ph' => '7.00',
        'water_temp_c' => '28.00',
        'dissolved_oxygen_mg_l' => '5.00',
        'ammonia_mg_l' => '0.200',
    ]);
    expect($snapshotAction->handle($optimalCycle)['aquaculture']['waterStatus'])->toBe('optimal');

    ProductionLog::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $fishFarm->id,
        'production_cycle_id' => $optimalCycle->id,
        'log_date' => now()->addDay(),
        'water_ph' => '8.90',
        'dissolved_oxygen_mg_l' => '3.50',
        'ammonia_mg_l' => '0.700',
    ]);
    expect($snapshotAction->handle($optimalCycle)['aquaculture']['waterStatus'])->toBe('warning');
});

test('two-way Q&A exposes investor comments to farm managers, allows replies, and supports harvest sale and distribution tagging', function () {
    $owner = User::factory()->create();
    $investor = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($investor, ['role' => TeamRole::Investor->value]);
    $owner->switchTeam($team);
    $investor->switchTeam($team);

    TeamInvitation::create([
        'team_id' => $team->id,
        'email' => 'pending-investor@example.com',
        'role' => TeamRole::Investor,
        'invited_by' => $owner->id,
        'expires_at' => now()->addDays(2),
    ]);

    $farm = Farm::factory()->create(['team_id' => $team->id, 'farm_type' => FarmType::Crop]);
    $commodity = Commodity::factory()->create(['team_id' => $team->id, 'name' => 'Catfish']);
    $agreement = InvestorAgreement::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'investor_id' => $investor->id,
        'status' => InvestorAgreementStatus::Active,
        'currency' => 'NGN',
        'amount_committed_minor' => 500_000,
        'amount_funded_minor' => 250_000,
    ]);

    $harvest = HarvestRecord::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'commodity_id' => $commodity->id,
        'investor_agreement_id' => $agreement->id,
        'investor_visibility_status' => InvestorVisibilityStatus::Approved,
    ]);
    $sale = SaleRecord::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'commodity_id' => $commodity->id,
        'investor_agreement_id' => $agreement->id,
        'investor_visibility_status' => InvestorVisibilityStatus::Approved,
        'buyer_name' => 'Lagos Market Co',
    ]);
    $distribution = DistributionRecord::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'sale_record_id' => $sale->id,
        'investor_agreement_id' => $agreement->id,
        'investor_visibility_status' => InvestorVisibilityStatus::Approved,
    ]);
    $privateHarvest = HarvestRecord::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'commodity_id' => $commodity->id,
        'investor_agreement_id' => $agreement->id,
        'investor_visibility_status' => InvestorVisibilityStatus::Private,
    ]);

    $commentRoute = route('investor-comments.store', ['current_team' => $team, 'investor_agreement' => $agreement]);

    foreach ([
        ['harvest', $harvest->id],
        ['sale', $sale->id],
        ['distribution', $distribution->id],
    ] as [$type, $id]) {
        $this->actingAs($investor)
            ->post($commentRoute, ['subject_type' => $type, 'subject_id' => $id, 'body' => "Question on {$type}"])
            ->assertRedirect();
    }

    $this->actingAs($investor)
        ->post($commentRoute, ['subject_type' => 'harvest', 'subject_id' => $privateHarvest->id, 'body' => 'Blocked'])
        ->assertNotFound();

    $this->actingAs($owner)
        ->post($commentRoute, ['body' => 'Reply from farm manager'])
        ->assertRedirect();

    $this->actingAs($owner)->get(route('investors.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.questionCount', 4)
            ->has('inviteContext.pendingInvestorInvitations', 1)
            ->has('agreements.0.comments', 4)
            ->where('agreements.0.comments.0.isInvestor', false)
            ->where('agreements.0.comments.0.subjectLabel', 'General agreement'));
});

test('enriched portfolio and approvals include summary financials, cycle progress, and status counts', function () {
    $owner = User::factory()->create();
    $investor = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($investor, ['role' => TeamRole::Investor->value]);
    $owner->switchTeam($team);
    $investor->switchTeam($team);

    $farm = Farm::factory()->create(['team_id' => $team->id, 'farm_type' => FarmType::Crop]);
    $cycle = ProductionCycle::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'farm_type' => FarmType::Crop,
        'planned_start_on' => now()->subDays(10),
        'planned_end_on' => now()->addDays(10),
    ]);
    $agreement = InvestorAgreement::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'production_cycle_id' => $cycle->id,
        'investor_id' => $investor->id,
        'status' => InvestorAgreementStatus::Active,
        'currency' => 'NGN',
        'amount_committed_minor' => 200_000,
        'amount_funded_minor' => 100_000,
    ]);

    FundingPhase::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'investor_agreement_id' => $agreement->id,
        'investor_visibility_status' => InvestorVisibilityStatus::Approved,
        'currency' => 'NGN',
        'externally_released_amount_minor' => 80_000,
    ]);
    Expense::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'investor_agreement_id' => $agreement->id,
        'investor_visibility_status' => InvestorVisibilityStatus::Approved,
        'currency' => 'NGN',
        'amount_minor' => 30_000,
    ]);
    $sale = SaleRecord::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'investor_agreement_id' => $agreement->id,
        'currency' => 'NGN',
    ]);
    DistributionRecord::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'sale_record_id' => $sale->id,
        'investor_agreement_id' => $agreement->id,
        'investor_visibility_status' => InvestorVisibilityStatus::Approved,
        'currency' => 'NGN',
        'capital_recovered_minor' => 25_000,
        'investor_share_minor' => 12_000,
    ]);
    ApprovalRequest::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'investor_agreement_id' => $agreement->id,
        'status' => ApprovalRequestStatus::ClarificationRequested,
    ]);

    $this->actingAs($investor)->get(route('my-investments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.farmCount', 1)
            ->where('summary.agreementCount', 1)
            ->where('summary.totalsByCurrency.0.released', '800.00')
            ->where('summary.totalsByCurrency.0.spent', '300.00')
            ->where('summary.totalsByCurrency.0.capitalRecovered', '250.00')
            ->where('summary.totalsByCurrency.0.investorShare', '120.00')
            ->where('farms.0.farmType', 'crop')
            ->where('farms.0.agreements.0.fundingPercent', 50));

    $this->actingAs($owner)->get(route('investors.approvals.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('counts.total', 1)
            ->where('counts.clarificationRequested', 1)
            ->where('approvalRequests.0.farmName', $farm->name));

    $this->actingAs($investor)->get(route('investor-statements.index', [$team, $agreement]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('agreement.farmName', $farm->name)
            ->where('agreement.productionCycleName', $cycle->name));
});

<?php

use App\Actions\Harvests\BuildHarvestPageData;
use App\Actions\Harvests\CalculateCapitalRecoveryDistribution;
use App\Actions\Harvests\RecalculateDistribution;
use App\Actions\Harvests\RecordHarvest;
use App\Actions\Harvests\RecordSale;
use App\Actions\Investors\BuildInvestorPageData;
use App\Actions\Investors\DecideApprovalRequest;
use App\Enums\ApprovalRequestStatus;
use App\Enums\DistributionStatus;
use App\Enums\HarvestRecordStatus;
use App\Enums\HarvestStage;
use App\Enums\InvestorVisibilityStatus;
use App\Enums\SalePaymentStatus;
use App\Models\ApprovalRequest;
use App\Models\Commodity;
use App\Models\DistributionRecord;
use App\Models\Farm;
use App\Models\HarvestRecord;
use App\Models\InvestorAgreement;
use App\Models\ProductionCycle;
use App\Models\ProductionUnit;
use App\Models\SaleRecord;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('harvest phase enums expose labels for forms', function () {
    expect(HarvestStage::options())->toContain(['value' => 'recurring', 'label' => 'Recurring'])
        ->and(HarvestRecordStatus::options())->toContain(['value' => 'partially_sold', 'label' => 'Partially sold'])
        ->and(SalePaymentStatus::options())->toContain(['value' => 'reconciled', 'label' => 'Reconciled'])
        ->and(DistributionStatus::options())->toContain(['value' => 'pending_acknowledgement', 'label' => 'Pending acknowledgement']);
});

test('capital-first recovery handles partial recovery without profit share', function () {
    $team = Team::factory()->create();
    $farm = Farm::factory()->create(['team_id' => $team->id]);
    $agreement = InvestorAgreement::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'amount_funded_minor' => 100_000,
        'investor_profit_share_percentage' => 40,
        'farm_profit_share_percentage' => 60,
    ]);
    $harvest = HarvestRecord::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'investor_agreement_id' => $agreement->id,
    ]);
    $sale = SaleRecord::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'harvest_record_id' => $harvest->id,
        'commodity_id' => $harvest->commodity_id,
        'investor_agreement_id' => $agreement->id,
        'gross_amount_minor' => 60_000,
        'deduction_amount_minor' => 0,
        'net_amount_minor' => 60_000,
    ]);

    $calculation = app(CalculateCapitalRecoveryDistribution::class)->handle($agreement, $sale);

    expect($calculation['previous_capital_recovered_minor'])->toBe(0)
        ->and($calculation['capital_recovered_minor'])->toBe(60_000)
        ->and($calculation['unrecovered_capital_minor'])->toBe(40_000)
        ->and($calculation['net_profit_minor'])->toBe(0)
        ->and($calculation['investor_share_minor'])->toBe(0)
        ->and($calculation['farm_share_minor'])->toBe(0);

    $sale->forceFill(['currency' => 'USD'])->save();

    expect(fn () => app(CalculateCapitalRecoveryDistribution::class)->handle($agreement, $sale))
        ->toThrow(InvalidArgumentException::class);
});

test('capital-first recovery handles multiple sales and configurable splits', function () {
    $team = Team::factory()->create();
    $farm = Farm::factory()->create(['team_id' => $team->id]);
    $agreement = InvestorAgreement::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'amount_funded_minor' => 100_000,
        'investor_profit_share_percentage' => 30,
        'farm_profit_share_percentage' => 70,
    ]);
    $firstHarvest = HarvestRecord::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'investor_agreement_id' => $agreement->id,
    ]);
    $firstSale = SaleRecord::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'harvest_record_id' => $firstHarvest->id,
        'commodity_id' => $firstHarvest->commodity_id,
        'investor_agreement_id' => $agreement->id,
        'net_amount_minor' => 60_000,
    ]);
    DistributionRecord::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'investor_agreement_id' => $agreement->id,
        'sale_record_id' => $firstSale->id,
        'capital_recovered_minor' => 60_000,
        'unrecovered_capital_minor' => 40_000,
    ]);

    $secondHarvest = HarvestRecord::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'investor_agreement_id' => $agreement->id,
    ]);
    $secondSale = SaleRecord::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'harvest_record_id' => $secondHarvest->id,
        'commodity_id' => $secondHarvest->commodity_id,
        'investor_agreement_id' => $agreement->id,
        'gross_amount_minor' => 80_000,
        'deduction_amount_minor' => 10_000,
        'net_amount_minor' => 70_000,
    ]);

    $calculation = app(CalculateCapitalRecoveryDistribution::class)->handle($agreement, $secondSale);

    expect($calculation['previous_capital_recovered_minor'])->toBe(60_000)
        ->and($calculation['capital_recovered_minor'])->toBe(40_000)
        ->and($calculation['unrecovered_capital_minor'])->toBe(0)
        ->and($calculation['gross_profit_minor'])->toBe(40_000)
        ->and($calculation['net_profit_minor'])->toBe(30_000)
        ->and($calculation['investor_share_minor'])->toBe(9_000)
        ->and($calculation['farm_share_minor'])->toBe(21_000);
});

test('current unrecovered capital summaries do not aggregate historical balance snapshots', function () {
    $investor = User::factory()->create();
    $team = Team::factory()->create();
    $farm = Farm::factory()->create(['team_id' => $team->id]);
    $agreement = InvestorAgreement::factory()->create([
        'team_id' => $team->id,
        'investor_id' => $investor->id,
        'farm_id' => $farm->id,
        'amount_funded_minor' => 100_000,
    ]);
    $firstHarvest = HarvestRecord::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'investor_agreement_id' => $agreement->id,
    ]);
    $firstSale = SaleRecord::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'harvest_record_id' => $firstHarvest->id,
        'commodity_id' => $firstHarvest->commodity_id,
        'investor_agreement_id' => $agreement->id,
        'investor_visibility_status' => InvestorVisibilityStatus::Approved,
    ]);
    DistributionRecord::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'investor_agreement_id' => $agreement->id,
        'sale_record_id' => $firstSale->id,
        'capital_recovered_minor' => 60_000,
        'unrecovered_capital_minor' => 40_000,
        'investor_visibility_status' => InvestorVisibilityStatus::Approved,
    ]);
    $secondHarvest = HarvestRecord::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'investor_agreement_id' => $agreement->id,
    ]);
    $secondSale = SaleRecord::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'harvest_record_id' => $secondHarvest->id,
        'commodity_id' => $secondHarvest->commodity_id,
        'investor_agreement_id' => $agreement->id,
        'investor_visibility_status' => InvestorVisibilityStatus::Approved,
    ]);
    DistributionRecord::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'investor_agreement_id' => $agreement->id,
        'sale_record_id' => $secondSale->id,
        'previous_capital_recovered_minor' => 60_000,
        'capital_recovered_minor' => 20_000,
        'unrecovered_capital_minor' => 20_000,
        'investor_visibility_status' => InvestorVisibilityStatus::Approved,
    ]);

    $teamSummary = app(BuildHarvestPageData::class)->dashboardSummary($team);
    $portal = app(BuildInvestorPageData::class)->portal($team, $investor);
    $agreementSummary = $portal['agreements']->first()['summary'];

    expect($teamSummary['unrecoveredCapitalMinor'])->toBe(20_000)
        ->and($teamSummary['unrecoveredCapital'])->toBe('200.00')
        ->and($agreementSummary['unrecoveredCapitalMinor'])->toBe(20_000)
        ->and($agreementSummary['unrecoveredCapital'])->toBe('200.00');
});

test('record sale enforces harvest availability and agreement scope inside the transaction', function () {
    $actor = User::factory()->create();
    $team = Team::factory()->create();
    $farm = Farm::factory()->create(['team_id' => $team->id]);
    $agreement = InvestorAgreement::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
    ]);
    $harvest = HarvestRecord::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'investor_agreement_id' => $agreement->id,
        'quantity' => '10.00',
        'quantity_unit' => 'kg',
    ]);
    SaleRecord::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'harvest_record_id' => $harvest->id,
        'commodity_id' => $harvest->commodity_id,
        'investor_agreement_id' => $agreement->id,
        'quantity' => '8.00',
        'quantity_unit' => 'kg',
    ]);
    $attributes = [
        'harvest_record_id' => $harvest->id,
        'investor_agreement_id' => $agreement->id,
        'sold_on' => '2026-06-12',
        'buyer_name' => 'Buyer',
        'quantity' => '3.00',
        'quantity_unit' => 'kg',
        'unit_price_minor' => 1_000,
        'gross_amount_minor' => 3_000,
        'deduction_amount_minor' => 0,
        'net_amount_minor' => 3_000,
        'payment_status' => SalePaymentStatus::Paid,
        'investor_visibility_status' => InvestorVisibilityStatus::Private,
    ];

    expect(fn () => app(RecordSale::class)->handle($team, $actor, $attributes))
        ->toThrow(ValidationException::class);

    expect(fn () => app(RecordSale::class)->handle($team, $actor, [
        ...$attributes,
        'quantity' => '1.00',
        'gross_amount_minor' => 999,
    ]))->toThrow(ValidationException::class);

    $differentCurrencyAgreement = InvestorAgreement::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'currency' => 'USD',
    ]);

    expect(fn () => app(RecordSale::class)->handle($team, $actor, [
        ...$attributes,
        'investor_agreement_id' => $differentCurrencyAgreement->id,
        'quantity' => '1.00',
        'gross_amount_minor' => 1_000,
    ]))->toThrow(ValidationException::class);

    expect(fn () => app(RecordSale::class)->handle($team, $actor, [
        ...$attributes,
        'quantity' => '1.00',
        'quantity_unit' => 'tonnes',
    ]))->toThrow(ValidationException::class);

    $otherFarm = Farm::factory()->create(['team_id' => $team->id]);
    $unrelatedAgreement = InvestorAgreement::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $otherFarm->id,
    ]);

    expect(fn () => app(RecordSale::class)->handle($team, $actor, [
        ...$attributes,
        'investor_agreement_id' => $unrelatedAgreement->id,
        'quantity' => '1.00',
        'gross_amount_minor' => 1_000,
    ]))->toThrow(ValidationException::class);

    expect(SaleRecord::query()->where('harvest_record_id', $harvest->id)->count())->toBe(1);
});

test('record harvest enforces investor agreement cycle scope inside the transaction', function () {
    $actor = User::factory()->create();
    $team = Team::factory()->create();
    $farm = Farm::factory()->create(['team_id' => $team->id]);
    $agreementCycle = ProductionCycle::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
    ]);
    $harvestCycle = ProductionCycle::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
    ]);
    $agreement = InvestorAgreement::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'production_cycle_id' => $agreementCycle->id,
    ]);
    $commodity = Commodity::factory()->create([
        'team_id' => $team->id,
        'name' => 'Pepper',
        'measurement_unit' => 'kg',
    ]);

    expect(fn () => app(RecordHarvest::class)->handle($team, $actor, [
        'farm_id' => $farm->id,
        'production_cycle_id' => $harvestCycle->id,
        'commodity_id' => $commodity->id,
        'investor_agreement_id' => $agreement->id,
        'harvested_on' => '2026-06-11',
        'stage' => HarvestStage::Single,
        'sequence_number' => 1,
        'quantity' => '10.00',
        'quantity_unit' => 'kg',
        'labour_cost_minor' => 0,
        'status' => HarvestRecordStatus::Recorded,
        'investor_visibility_status' => InvestorVisibilityStatus::Private,
    ]))->toThrow(ValidationException::class);

    expect(HarvestRecord::query()->where('team_id', $team->id)->count())->toBe(0);
});

test('distribution recalculation distinguishes partial recovery from an explicit harvest loss', function () {
    $actor = User::factory()->create();
    $team = Team::factory()->create();
    $farm = Farm::factory()->create(['team_id' => $team->id]);
    $harvestWithoutAgreement = HarvestRecord::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
    ]);
    $saleWithoutAgreement = SaleRecord::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'harvest_record_id' => $harvestWithoutAgreement->id,
        'commodity_id' => $harvestWithoutAgreement->commodity_id,
        'investor_agreement_id' => null,
    ]);

    $missingDistribution = app(RecalculateDistribution::class)->handle($team, $saleWithoutAgreement, $actor);

    expect($missingDistribution)->toBeNull()
        ->and(DistributionRecord::query()->count())->toBe(0);

    $agreement = InvestorAgreement::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'amount_funded_minor' => 100_000,
    ]);
    $harvest = HarvestRecord::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'investor_agreement_id' => $agreement->id,
    ]);
    $sale = SaleRecord::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'harvest_record_id' => $harvest->id,
        'commodity_id' => $harvest->commodity_id,
        'investor_agreement_id' => $agreement->id,
        'gross_amount_minor' => 60_000,
        'deduction_amount_minor' => 0,
        'net_amount_minor' => 60_000,
    ]);

    $distribution = app(RecalculateDistribution::class)->handle($team, $sale, $actor);

    expect($distribution)->not->toBeNull()
        ->and($distribution?->status)->toBe(DistributionStatus::PendingAcknowledgement)
        ->and($distribution?->capital_recovered_minor)->toBe(60_000)
        ->and($distribution?->unrecovered_capital_minor)->toBe(40_000)
        ->and($distribution?->approvalRequest)->not->toBeNull();

    $lossHarvest = HarvestRecord::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'investor_agreement_id' => $agreement->id,
        'status' => HarvestRecordStatus::Loss,
    ]);
    $lossSale = SaleRecord::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'harvest_record_id' => $lossHarvest->id,
        'commodity_id' => $lossHarvest->commodity_id,
        'investor_agreement_id' => $agreement->id,
        'gross_amount_minor' => 10_000,
        'deduction_amount_minor' => 0,
        'net_amount_minor' => 10_000,
    ]);

    $lossDistribution = app(RecalculateDistribution::class)->handle($team, $lossSale, $actor);

    expect($lossDistribution?->status)->toBe(DistributionStatus::LossRecorded)
        ->and($lossDistribution?->unrecovered_capital_minor)->toBe(30_000);

    assert($lossDistribution instanceof DistributionRecord);

    app(DecideApprovalRequest::class)->handle(
        $team,
        $lossDistribution->approvalRequest,
        $actor,
        ApprovalRequestStatus::Approved,
    );

    expect($lossDistribution->fresh()->status)->toBe(DistributionStatus::LossRecorded)
        ->and($lossDistribution->fresh()->acknowledged_at)->not->toBeNull();
});

test('harvest sale and distribution models expose casts and relationships', function () {
    $actor = User::factory()->create();
    $team = Team::factory()->create();
    $farm = Farm::factory()->create(['team_id' => $team->id]);
    $unit = ProductionUnit::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
    ]);
    $cycle = ProductionCycle::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
    ]);
    $agreement = InvestorAgreement::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'production_cycle_id' => $cycle->id,
    ]);
    $harvest = HarvestRecord::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'production_unit_id' => $unit->id,
        'production_cycle_id' => $cycle->id,
        'investor_agreement_id' => $agreement->id,
        'recorded_by_id' => $actor->id,
        'stage' => HarvestStage::Cutting,
        'status' => HarvestRecordStatus::Stored,
        'investor_visibility_status' => InvestorVisibilityStatus::Approved,
    ]);
    $sale = SaleRecord::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'production_cycle_id' => $cycle->id,
        'harvest_record_id' => $harvest->id,
        'commodity_id' => $harvest->commodity_id,
        'investor_agreement_id' => $agreement->id,
        'recorded_by_id' => $actor->id,
        'payment_status' => SalePaymentStatus::Reconciled,
    ]);
    $approvalRequest = ApprovalRequest::factory()->create([
        'team_id' => $team->id,
        'investor_agreement_id' => $agreement->id,
        'farm_id' => $farm->id,
        'production_cycle_id' => $cycle->id,
    ]);
    $distribution = DistributionRecord::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'production_cycle_id' => $cycle->id,
        'investor_agreement_id' => $agreement->id,
        'sale_record_id' => $sale->id,
        'approval_request_id' => $approvalRequest->id,
        'status' => DistributionStatus::PendingAcknowledgement,
    ]);

    expect($harvest->stage)->toBe(HarvestStage::Cutting)
        ->and($harvest->status)->toBe(HarvestRecordStatus::Stored)
        ->and($harvest->team->is($team))->toBeTrue()
        ->and($harvest->farm->is($farm))->toBeTrue()
        ->and($harvest->productionUnit->is($unit))->toBeTrue()
        ->and($harvest->productionCycle->is($cycle))->toBeTrue()
        ->and($harvest->commodity->is($sale->commodity))->toBeTrue()
        ->and($harvest->investorAgreement->is($agreement))->toBeTrue()
        ->and($harvest->recordedBy->is($actor))->toBeTrue()
        ->and($harvest->sales()->count())->toBe(1)
        ->and($sale->payment_status)->toBe(SalePaymentStatus::Reconciled)
        ->and($sale->team->is($team))->toBeTrue()
        ->and($sale->farm->is($farm))->toBeTrue()
        ->and($sale->productionCycle->is($cycle))->toBeTrue()
        ->and($sale->harvestRecord->is($harvest))->toBeTrue()
        ->and($sale->investorAgreement->is($agreement))->toBeTrue()
        ->and($sale->recordedBy->is($actor))->toBeTrue()
        ->and($sale->distributionRecord->is($distribution))->toBeTrue()
        ->and($distribution->status)->toBe(DistributionStatus::PendingAcknowledgement)
        ->and($distribution->team->is($team))->toBeTrue()
        ->and($distribution->farm->is($farm))->toBeTrue()
        ->and($distribution->productionCycle->is($cycle))->toBeTrue()
        ->and($distribution->investorAgreement->is($agreement))->toBeTrue()
        ->and($distribution->saleRecord->is($sale))->toBeTrue()
        ->and($distribution->approvalRequest->is($approvalRequest))->toBeTrue()
        ->and($team->harvestRecords()->count())->toBe(1)
        ->and($team->saleRecords()->count())->toBe(1)
        ->and($team->distributionRecords()->count())->toBe(1)
        ->and($farm->harvestRecords()->count())->toBe(1)
        ->and($farm->saleRecords()->count())->toBe(1)
        ->and($farm->distributionRecords()->count())->toBe(1)
        ->and($cycle->harvestRecords()->count())->toBe(1)
        ->and($cycle->saleRecords()->count())->toBe(1)
        ->and($cycle->distributionRecords()->count())->toBe(1)
        ->and($unit->harvestRecords()->count())->toBe(1)
        ->and($sale->commodity->harvestRecords()->count())->toBe(1)
        ->and($sale->commodity->saleRecords()->count())->toBe(1)
        ->and($actor->recordedHarvests()->count())->toBe(1)
        ->and($actor->recordedSales()->count())->toBe(1)
        ->and($agreement->distributionRecords()->count())->toBe(1);
});

<?php

use App\Enums\ApprovalRequestStatus;
use App\Enums\ApprovalRequestType;
use App\Enums\DistributionStatus;
use App\Enums\HarvestRecordStatus;
use App\Enums\InvestorAgreementStatus;
use App\Enums\InvestorVisibilityStatus;
use App\Enums\TeamRole;
use App\Models\ApprovalRequest;
use App\Models\AuditEvent;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

beforeEach(function () {
    $this->withoutVite();
});

function farmwellHarvestContext(TeamRole $actorRole = TeamRole::Owner): array
{
    $actor = User::factory()->create();
    $investor = User::factory()->create();
    $team = Team::factory()->create(['name' => fake()->unique()->company().' Harvest']);

    $team->members()->attach($actor, ['role' => $actorRole->value]);
    $team->members()->attach($investor, ['role' => TeamRole::Investor->value]);
    $actor->switchTeam($team);
    $investor->switchTeam($team);

    $farm = Farm::factory()->create(['team_id' => $team->id, 'name' => 'Harvest Farm']);
    $unit = ProductionUnit::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'name' => 'Plot A',
    ]);
    $cycle = ProductionCycle::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'name' => 'Dry Season',
    ]);
    $commodity = Commodity::factory()->create([
        'team_id' => $team->id,
        'name' => 'Pepper',
        'measurement_unit' => 'kg',
    ]);
    $agreement = InvestorAgreement::factory()->create([
        'team_id' => $team->id,
        'investor_id' => $investor->id,
        'farm_id' => $farm->id,
        'production_cycle_id' => $cycle->id,
        'status' => InvestorAgreementStatus::Active,
        'amount_funded_minor' => 100_000,
        'investor_profit_share_percentage' => 40,
        'farm_profit_share_percentage' => 60,
    ]);

    return [$actor, $investor, $team, $farm, $unit, $cycle, $commodity, $agreement];
}

function farmwellHarvestRoute(string $name, Team $team, array $parameters = []): string
{
    return route($name, ['current_team' => $team, ...$parameters]);
}

test('harvest workspace is visible to operations and finance users while write actions stay role scoped', function () {
    [$owner, $investor, $team] = farmwellHarvestContext();
    [$member, , $memberTeam, $memberFarm, , $memberCycle, $memberCommodity, $memberAgreement] = farmwellHarvestContext(TeamRole::Member);
    $memberHarvest = HarvestRecord::factory()->create([
        'team_id' => $memberTeam->id,
        'farm_id' => $memberFarm->id,
        'production_cycle_id' => $memberCycle->id,
        'commodity_id' => $memberCommodity->id,
        'investor_agreement_id' => $memberAgreement->id,
        'quantity' => '10.00',
        'quantity_unit' => 'kg',
    ]);
    $memberSale = SaleRecord::factory()->create([
        'team_id' => $memberTeam->id,
        'farm_id' => $memberFarm->id,
        'production_cycle_id' => $memberCycle->id,
        'harvest_record_id' => $memberHarvest->id,
        'commodity_id' => $memberCommodity->id,
        'investor_agreement_id' => $memberAgreement->id,
        'quantity' => '5.00',
        'quantity_unit' => 'kg',
        'net_amount_minor' => 50_000,
    ]);
    DistributionRecord::factory()->create([
        'team_id' => $memberTeam->id,
        'farm_id' => $memberFarm->id,
        'production_cycle_id' => $memberCycle->id,
        'investor_agreement_id' => $memberAgreement->id,
        'sale_record_id' => $memberSale->id,
        'capital_recovered_minor' => 50_000,
        'unrecovered_capital_minor' => 50_000,
    ]);

    $this
        ->actingAs($owner)
        ->get(farmwellHarvestRoute('harvests.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('harvests/Index')
            ->where('permissions.canManageFinance', true)
            ->where('summary.harvestRecords', 0)
            ->where('currency', 'NGN')
        );

    $this
        ->actingAs($member)
        ->get(farmwellHarvestRoute('harvests.index', $memberTeam))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('harvests/Index')
            ->where('permissions.canViewFarmOperations', true)
            ->where('permissions.canManageFarmOperations', false)
            ->where('summary.harvestRecords', 1)
            ->where('summary.saleNet', null)
            ->where('harvestRecords.0.investorAgreementId', null)
            ->has('investorAgreements', 0)
            ->has('saleRecords', 0)
            ->has('distributionRecords', 0)
        );

    $this
        ->actingAs($member)
        ->get(farmwellHarvestRoute('dashboard', $memberTeam))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('harvestSummary', null)
        );

    $this
        ->actingAs($member)
        ->post(farmwellHarvestRoute('harvests.store', $memberTeam), [
            'farm_id' => $memberFarm->id,
            'commodity_id' => $memberCommodity->id,
            'harvested_on' => '2026-06-11',
            'stage' => 'single',
            'sequence_number' => 1,
            'quantity' => '10',
            'quantity_unit' => 'kg',
        ])
        ->assertForbidden();

    $this
        ->actingAs($investor)
        ->get(farmwellHarvestRoute('harvests.index', $team))
        ->assertForbidden();
});

test('sale validation rejects unrelated agreements, mismatched units, and oversold quantities', function () {
    [$owner, , $team, $farm, , $cycle, $commodity, $agreement] = farmwellHarvestContext();
    $harvest = HarvestRecord::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'production_cycle_id' => $cycle->id,
        'commodity_id' => $commodity->id,
        'investor_agreement_id' => $agreement->id,
        'quantity' => '10.00',
        'quantity_unit' => 'kg',
    ]);
    $otherFarm = Farm::factory()->create(['team_id' => $team->id]);
    $unrelatedAgreement = InvestorAgreement::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $otherFarm->id,
    ]);
    $payload = [
        'harvest_record_id' => $harvest->id,
        'investor_agreement_id' => $agreement->id,
        'sold_on' => '2026-06-12',
        'buyer_name' => 'Farm Gate Buyer',
        'quantity' => '2.00',
        'quantity_unit' => 'kg',
        'unit_price' => '10.00',
        'gross_amount' => '20.00',
        'deduction_amount' => '0.00',
        'payment_status' => 'paid',
    ];

    $this
        ->actingAs($owner)
        ->post(farmwellHarvestRoute('harvests.sales.store', $team), [
            ...$payload,
            'harvest_record_id' => PHP_INT_MAX,
        ])
        ->assertSessionHasErrors('harvest_record_id');

    $this
        ->actingAs($owner)
        ->post(farmwellHarvestRoute('harvests.sales.store', $team), [
            ...$payload,
            'investor_agreement_id' => $unrelatedAgreement->id,
        ])
        ->assertSessionHasErrors('investor_agreement_id');

    $this
        ->actingAs($owner)
        ->post(farmwellHarvestRoute('harvests.sales.store', $team), [
            ...$payload,
            'gross_amount' => '19.99',
        ])
        ->assertSessionHasErrors('gross_amount');

    $this
        ->actingAs($owner)
        ->post(farmwellHarvestRoute('harvests.sales.store', $team), [
            ...$payload,
            'quantity_unit' => 'tonnes',
        ])
        ->assertSessionHasErrors('quantity_unit');

    SaleRecord::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'production_cycle_id' => $cycle->id,
        'harvest_record_id' => $harvest->id,
        'commodity_id' => $commodity->id,
        'investor_agreement_id' => $agreement->id,
        'quantity' => '8.00',
        'quantity_unit' => 'kg',
    ]);

    $this
        ->actingAs($owner)
        ->post(farmwellHarvestRoute('harvests.sales.store', $team), [
            ...$payload,
            'quantity' => '3.00',
        ])
        ->assertSessionHasErrors('quantity');

    expect(SaleRecord::query()->where('harvest_record_id', $harvest->id)->count())->toBe(1);

    $unfundedHarvest = HarvestRecord::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'production_cycle_id' => $cycle->id,
        'commodity_id' => $commodity->id,
        'investor_agreement_id' => null,
        'quantity' => '10.00',
        'quantity_unit' => 'kg',
    ]);

    $this
        ->actingAs($owner)
        ->post(farmwellHarvestRoute('harvests.sales.store', $team), [
            ...$payload,
            'harvest_record_id' => $unfundedHarvest->id,
            'investor_agreement_id' => null,
        ])
        ->assertRedirect(farmwellHarvestRoute('harvests.index', $team));

    expect(SaleRecord::query()->where('harvest_record_id', $unfundedHarvest->id)->count())->toBe(1);
});

test('sale validation rejects investor agreements with a different currency', function () {
    [$owner, , $team, $farm, , $cycle, $commodity] = farmwellHarvestContext();
    $agreement = InvestorAgreement::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'production_cycle_id' => $cycle->id,
        'currency' => 'USD',
    ]);
    $harvest = HarvestRecord::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'production_cycle_id' => $cycle->id,
        'commodity_id' => $commodity->id,
        'investor_agreement_id' => $agreement->id,
        'quantity' => '10.00',
        'quantity_unit' => 'kg',
    ]);

    $this
        ->actingAs($owner)
        ->post(farmwellHarvestRoute('harvests.sales.store', $team), [
            'harvest_record_id' => $harvest->id,
            'investor_agreement_id' => $agreement->id,
            'sold_on' => '2026-06-12',
            'buyer_name' => 'Farm Gate Buyer',
            'quantity' => '2.00',
            'quantity_unit' => 'kg',
            'unit_price' => '10.00',
            'gross_amount' => '20.00',
            'deduction_amount' => '0.00',
            'payment_status' => 'paid',
        ])
        ->assertSessionHasErrors('investor_agreement_id');

    expect(SaleRecord::query()->where('harvest_record_id', $harvest->id)->count())->toBe(0);
});

test('harvest validation enforces agreement cycle scope while allowing global and unfunded harvests', function () {
    [$owner, , $team, $farm, , $agreementCycle, $commodity, $agreement] = farmwellHarvestContext();
    $harvestCycle = ProductionCycle::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
    ]);

    expect($agreement->production_cycle_id)->toBe($agreementCycle->id);

    $this
        ->actingAs($owner)
        ->post(farmwellHarvestRoute('harvests.store', $team), [
            'farm_id' => $farm->id,
            'production_cycle_id' => $harvestCycle->id,
            'commodity_id' => $commodity->id,
            'investor_agreement_id' => $agreement->id,
            'harvested_on' => '2026-06-11',
            'stage' => 'single',
            'sequence_number' => 1,
            'quantity' => '10',
            'quantity_unit' => 'kg',
        ])
        ->assertSessionHasErrors('investor_agreement_id');

    expect(HarvestRecord::query()->where('team_id', $team->id)->count())->toBe(0);

    $globalAgreement = InvestorAgreement::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'production_cycle_id' => null,
    ]);
    $payload = [
        'farm_id' => $farm->id,
        'commodity_id' => $commodity->id,
        'harvested_on' => '2026-06-11',
        'stage' => 'single',
        'sequence_number' => 1,
        'quantity' => '10',
        'quantity_unit' => 'kg',
    ];

    $this
        ->actingAs($owner)
        ->post(farmwellHarvestRoute('harvests.store', $team), [
            ...$payload,
            'investor_agreement_id' => $globalAgreement->id,
        ])
        ->assertRedirect(farmwellHarvestRoute('harvests.index', $team));

    $this
        ->actingAs($owner)
        ->post(farmwellHarvestRoute('harvests.store', $team), [
            ...$payload,
            'production_cycle_id' => $harvestCycle->id,
            'investor_agreement_id' => null,
        ])
        ->assertRedirect(farmwellHarvestRoute('harvests.index', $team));

    expect(HarvestRecord::query()->where('team_id', $team->id)->count())->toBe(2);
});

test('recording the full harvest quantity marks the harvest sold', function () {
    [$owner, , $team, $farm, , $cycle, $commodity, $agreement] = farmwellHarvestContext();
    $harvest = HarvestRecord::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'production_cycle_id' => $cycle->id,
        'commodity_id' => $commodity->id,
        'investor_agreement_id' => $agreement->id,
        'quantity' => '10.00',
        'quantity_unit' => 'kg',
        'status' => HarvestRecordStatus::Recorded,
    ]);

    $this
        ->actingAs($owner)
        ->post(farmwellHarvestRoute('harvests.sales.store', $team), [
            'harvest_record_id' => $harvest->id,
            'investor_agreement_id' => $agreement->id,
            'sold_on' => '2026-06-12',
            'buyer_name' => 'Farm Gate Buyer',
            'quantity' => '10',
            'quantity_unit' => 'kg',
            'unit_price' => '10.00',
            'gross_amount' => '100.00',
            'deduction_amount' => '0.00',
            'payment_status' => 'paid',
        ])
        ->assertRedirect(farmwellHarvestRoute('harvests.index', $team));

    expect($harvest->fresh()->status)->toBe(HarvestRecordStatus::Sold)
        ->and(SaleRecord::query()->where('harvest_record_id', $harvest->id)->count())->toBe(1)
        ->and(DistributionRecord::query()->count())->toBe(1);
});

test('harvest sale recovery distribution and investor acknowledgement workflow is complete', function () {
    Storage::fake('farmwell_private');

    [$owner, $investor, $team, $farm, $unit, $cycle, $commodity, $agreement] = farmwellHarvestContext();

    $this
        ->actingAs($owner)
        ->post(farmwellHarvestRoute('harvests.store', $team), [
            'farm_id' => $farm->id,
            'production_unit_id' => $unit->id,
            'production_cycle_id' => $cycle->id,
            'commodity_id' => $commodity->id,
            'investor_agreement_id' => $agreement->id,
            'harvested_on' => '2026-06-11',
            'stage' => 'recurring',
            'sequence_number' => 2,
            'quantity' => '100.50',
            'quantity_unit' => 'kg',
            'quality_notes' => 'Clean and sorted crates.',
            'labour_cost' => '125.25',
            'status' => 'stored',
            'investor_visibility_status' => 'approved',
            'evidence' => [
                UploadedFile::fake()->image('harvest.jpg'),
            ],
        ])
        ->assertRedirect(farmwellHarvestRoute('harvests.index', $team));

    $harvest = HarvestRecord::query()->firstOrFail();
    $harvestEvidence = $harvest->getFirstMedia(HarvestRecord::EvidenceCollection);

    expect($harvest->team_id)->toBe($team->id)
        ->and($harvest->production_unit_id)->toBe($unit->id)
        ->and($harvest->production_cycle_id)->toBe($cycle->id)
        ->and($harvest->quantity)->toBe('100.50')
        ->and($harvest->labour_cost_minor)->toBe(12_525)
        ->and($harvest->status)->toBe(HarvestRecordStatus::Stored)
        ->and($harvestEvidence)->not->toBeNull()
        ->and($harvestEvidence?->disk)->toBe('farmwell_private');

    $this
        ->actingAs($owner)
        ->get(farmwellHarvestRoute('farm-evidence.show', $team, ['media' => $harvestEvidence]))
        ->assertDownload($harvestEvidence?->file_name);

    $this
        ->actingAs($owner)
        ->post(farmwellHarvestRoute('harvests.sales.store', $team), [
            'harvest_record_id' => $harvest->id,
            'investor_agreement_id' => $agreement->id,
            'sold_on' => '2026-06-12',
            'buyer_name' => 'Bello Foods',
            'quantity' => '60',
            'quantity_unit' => 'kg',
            'unit_price' => '25.00',
            'gross_amount' => '1500.00',
            'deduction_amount' => '100.00',
            'payment_status' => 'paid',
            'reference' => 'SALE-PEPPER-1',
            'evidence' => [
                UploadedFile::fake()->create('sale-receipt.pdf', 64, 'application/pdf'),
            ],
        ])
        ->assertRedirect(farmwellHarvestRoute('harvests.index', $team));

    $sale = SaleRecord::query()->firstOrFail();
    $saleEvidence = $sale->getFirstMedia(SaleRecord::EvidenceCollection);
    $distribution = DistributionRecord::query()->firstOrFail();
    $approvalRequest = ApprovalRequest::query()->firstOrFail();

    expect($sale->farm_id)->toBe($farm->id)
        ->and($sale->commodity_id)->toBe($commodity->id)
        ->and($sale->net_amount_minor)->toBe(140_000)
        ->and($harvest->fresh()->status)->toBe(HarvestRecordStatus::PartiallySold)
        ->and($distribution->sale_record_id)->toBe($sale->id)
        ->and($distribution->capital_recovered_minor)->toBe(100_000)
        ->and($distribution->unrecovered_capital_minor)->toBe(0)
        ->and($distribution->net_profit_minor)->toBe(40_000)
        ->and($distribution->investor_share_minor)->toBe(16_000)
        ->and($distribution->farm_share_minor)->toBe(24_000)
        ->and($distribution->status)->toBe(DistributionStatus::PendingAcknowledgement)
        ->and($approvalRequest->request_type)->toBe(ApprovalRequestType::DistributionAcknowledgement)
        ->and($approvalRequest->requested_amount_minor)->toBe(116_000)
        ->and($saleEvidence)->not->toBeNull();

    $this
        ->actingAs($owner)
        ->get(farmwellHarvestRoute('harvests.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('harvests/Index')
            ->where('summary.saleNet', '1400.00')
            ->where('summary.capitalRecovered', '1000.00')
            ->where('summary.investorShare', '160.00')
            ->where('harvestRecords.0.statusLabel', 'Partially sold')
            ->where('saleRecords.0.buyerName', 'Bello Foods')
            ->where('distributionRecords.0.investorShare', '160.00')
        );

    $this
        ->actingAs($investor)
        ->post(farmwellHarvestRoute('investors.approvals.decisions.store', $team, ['approval_request' => $approvalRequest]), [
            'status' => ApprovalRequestStatus::Approved->value,
            'approved_amount' => '1160.00',
            'decision_comment' => 'Acknowledged for payout.',
        ])
        ->assertRedirect(farmwellHarvestRoute('investor-portal.index', $team));

    expect($distribution->fresh()->investor_visibility_status)->toBe(InvestorVisibilityStatus::Approved)
        ->and($distribution->fresh()->status)->toBe(DistributionStatus::Acknowledged)
        ->and($distribution->fresh()->acknowledged_at)->not->toBeNull();

    $distribution = $distribution->fresh();
    $distributionEvidence = Media::create([
        'model_type' => $distribution->getMorphClass(),
        'model_id' => $distribution->id,
        'collection_name' => 'distribution_evidence',
        'name' => 'distribution-summary',
        'file_name' => 'distribution-summary.txt',
        'mime_type' => 'text/plain',
        'disk' => 'farmwell_private',
        'conversions_disk' => 'farmwell_private',
        'size' => strlen('distribution approved'),
        'manipulations' => [],
        'custom_properties' => [],
        'generated_conversions' => [],
        'responsive_images' => [],
    ]);

    Storage::disk('farmwell_private')->put($distributionEvidence->getPathRelativeToRoot(), 'distribution approved');

    $this
        ->actingAs($owner)
        ->get(farmwellHarvestRoute('investor-evidence.show', $team, ['media' => $saleEvidence]))
        ->assertDownload($saleEvidence?->file_name);

    $this
        ->actingAs($investor)
        ->get(farmwellHarvestRoute('investor-evidence.show', $team, ['media' => $saleEvidence]))
        ->assertDownload($saleEvidence?->file_name);

    $this
        ->actingAs($investor)
        ->get(farmwellHarvestRoute('investor-evidence.show', $team, ['media' => $distributionEvidence]))
        ->assertDownload($distributionEvidence->file_name);

    $this
        ->actingAs($investor)
        ->get(farmwellHarvestRoute('investor-portal.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('investor-portal/Index')
            ->where('agreements.0.summary.saleNet', '1400.00')
            ->where('agreements.0.summary.capitalRecovered', '1000.00')
            ->where('agreements.0.summary.investorShare', '160.00')
            ->where('agreements.0.harvestRecords.0.commodityName', 'Pepper')
            ->where('agreements.0.saleRecords.0.buyerName', 'Bello Foods')
            ->where('agreements.0.distributionRecords.0.investorShare', '160.00')
        );

    expect(AuditEvent::query()->where('team_id', $team->id)->pluck('action')->all())
        ->toContain(
            'harvest.recorded',
            'harvest_evidence.attached',
            'sale.recorded',
            'sale_evidence.attached',
            'distribution.calculated',
            'approval_request.created',
            'approval_request.decided',
        );
});

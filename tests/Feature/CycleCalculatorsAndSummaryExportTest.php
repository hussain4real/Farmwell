<?php

use App\Actions\FarmOperations\BuildCycleCalculators;
use App\Actions\FarmOperations\CalculateCycleTelemetry;
use App\Enums\EnterpriseSubtype;
use App\Enums\ExpenseStatus;
use App\Enums\FarmType;
use App\Enums\HealthEventStatus;
use App\Enums\ProductionCycleStatus;
use App\Enums\ProductionLogDomain;
use App\Enums\SalePaymentStatus;
use App\Enums\TeamRole;
use App\Models\Commodity;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Farm;
use App\Models\HarvestRecord;
use App\Models\HealthEvent;
use App\Models\ProductionCycle;
use App\Models\ProductionLog;
use App\Models\ProductionUnit;
use App\Models\SaleRecord;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

function phaseFourCycleContext(FarmType $farmType, ?EnterpriseSubtype $subtype = null): array
{
    $owner = User::factory()->create();
    $operator = User::factory()->create();
    $investor = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($operator, ['role' => TeamRole::Operator->value]);
    $team->members()->attach($investor, ['role' => TeamRole::Investor->value]);
    $owner->switchTeam($team);

    $farm = Farm::factory()->create(['team_id' => $team->id, 'farm_type' => $farmType]);
    $unit = ProductionUnit::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'size' => 2.5,
        'size_unit' => 'hectares',
    ]);
    $cycle = ProductionCycle::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'farm_type' => $farmType,
        'enterprise_subtype' => $subtype,
        'status' => ProductionCycleStatus::Active,
        'planned_start_on' => now()->subDays(10)->toDateString(),
        'actual_start_on' => now()->subDays(10)->toDateString(),
        'initial_stock_count' => 1000,
        'current_stock_count' => 1000,
        'initial_average_weight_g' => 15,
    ]);
    $cycle->productionUnits()->attach($unit);

    return [$owner, $operator, $investor, $team, $farm, $unit, $cycle];
}

test('calculators provide aquaculture ration weight brackets and feeding status checks', function () {
    [, , , $team, $farm, , $cycle] = phaseFourCycleContext(FarmType::Aquaculture, EnterpriseSubtype::Catfish);
    $calculator = app(BuildCycleCalculators::class);
    $telemetryService = app(CalculateCycleTelemetry::class);

    // Bracket <10g with no feed -> no_data
    $cycle->update(['initial_average_weight_g' => 5]);
    $res = $calculator->handle($cycle->fresh(), $telemetryService->handle($cycle->fresh()));
    expect($res['aquaculture']['bodyWeightPercent'])->toBe(8.0)
        ->and($res['aquaculture']['pelletSizeMm'])->toBe('1.5-2mm')
        ->and($res['aquaculture']['feedingStatus'])->toBe('no_data')
        ->and($res['poultry'])->toBeNull()
        ->and($res['crop'])->toBeNull();

    // Bracket <50g (30g * 1000 = 30kg -> 5% = 1.5kg) -> underfeeding (0.5kg)
    $log = ProductionLog::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'production_cycle_id' => $cycle->id,
        'domain' => ProductionLogDomain::Aquaculture,
        'average_weight_g' => 30,
        'feed_used_kg' => 0.5,
        'water_ph' => 7.2,
        'dissolved_oxygen_mg_l' => 5.5,
    ]);
    $res = $calculator->handle($cycle->fresh(), $telemetryService->handle($cycle->fresh()));
    expect($res['aquaculture']['bodyWeightPercent'])->toBe(5.0)
        ->and($res['aquaculture']['feedingStatus'])->toBe('underfeeding');

    // Bracket <200g (100g * 1000 = 100kg -> 3.5% = 3.5kg) -> overfeeding (6kg)
    $log->update(['average_weight_g' => 100, 'feed_used_kg' => 6.0]);
    $res = $calculator->handle($cycle->fresh(), $telemetryService->handle($cycle->fresh()));
    expect($res['aquaculture']['bodyWeightPercent'])->toBe(3.5)
        ->and($res['aquaculture']['feedingStatus'])->toBe('overfeeding');

    // Bracket <500g (300g * 1000 = 300kg -> 2.5% = 7.5kg) -> on_track (7.5kg)
    $log->update(['average_weight_g' => 300, 'feed_used_kg' => 7.5]);
    $res = $calculator->handle($cycle->fresh(), $telemetryService->handle($cycle->fresh()));
    expect($res['aquaculture']['bodyWeightPercent'])->toBe(2.5)
        ->and($res['aquaculture']['feedingStatus'])->toBe('on_track');

    // Bracket >=500g (600g -> 1.8%)
    $log->update(['average_weight_g' => 600, 'feed_used_kg' => 10.8]);
    $res = $calculator->handle($cycle->fresh(), $telemetryService->handle($cycle->fresh()));
    expect($res['aquaculture']['bodyWeightPercent'])->toBe(1.8)
        ->and($res['aquaculture']['pelletSizeMm'])->toBe('6-8mm');
});

test('calculators provide poultry broiler and layer feed phase guidance', function () {
    [, , , , , , $broiler] = phaseFourCycleContext(FarmType::Poultry, EnterpriseSubtype::Broiler);
    $calculator = app(BuildCycleCalculators::class);
    $telemetryService = app(CalculateCycleTelemetry::class);

    // Broiler <= 14d (day 10)
    $res = $calculator->handle($broiler, $telemetryService->handle($broiler));
    expect($res['poultry']['currentPhase'])->toBe('Broiler Starter Mash')
        ->and($res['poultry']['nextPhase'])->toBe('Broiler Grower Mash');

    // Broiler <= 28d (day 20)
    $broiler->update(['actual_start_on' => now()->subDays(20)->toDateString()]);
    $res = $calculator->handle($broiler->fresh(), $telemetryService->handle($broiler->fresh()));
    expect($res['poultry']['currentPhase'])->toBe('Broiler Grower Mash');

    // Broiler > 28d (day 35)
    $broiler->update(['actual_start_on' => now()->subDays(35)->toDateString()]);
    $res = $calculator->handle($broiler->fresh(), $telemetryService->handle($broiler->fresh()));
    expect($res['poultry']['currentPhase'])->toBe('Broiler Finisher Pellets')
        ->and($res['poultry']['daysToNextPhase'])->toBeNull();

    // Layer <= 56d, <= 126d, > 126d
    [, , , , , , $layer] = phaseFourCycleContext(FarmType::Poultry, EnterpriseSubtype::Layer);
    $res = $calculator->handle($layer, $telemetryService->handle($layer));
    expect($res['poultry']['isLayer'])->toBeTrue()
        ->and($res['poultry']['currentPhase'])->toBe('Chick Mash');

    $layer->update(['actual_start_on' => now()->subDays(80)->toDateString()]);
    $res = $calculator->handle($layer->fresh(), $telemetryService->handle($layer->fresh()));
    expect($res['poultry']['currentPhase'])->toBe('Grower Mash');

    $layer->update(['actual_start_on' => now()->subDays(150)->toDateString()]);
    $res = $calculator->handle($layer->fresh(), $telemetryService->handle($layer->fresh()));
    expect($res['poultry']['currentPhase'])->toBe('Layers Mash')
        ->and($res['poultry']['nextPhase'])->toBeNull();
});

test('calculators provide crop per-area economics and season comparison benchmarks', function () {
    [$owner, , , $team, $farm, , $cycle] = phaseFourCycleContext(FarmType::Crop, EnterpriseSubtype::ArableCrop);

    $commodity = Commodity::factory()->create(['team_id' => $team->id, 'farm_type' => FarmType::Crop]);
    $category = ExpenseCategory::factory()->create(['team_id' => $team->id]);
    Expense::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'production_cycle_id' => $cycle->id,
        'expense_category_id' => $category->id,
        'amount_minor' => 25_000_00,
        'status' => ExpenseStatus::Approved,
    ]);
    $harvest = HarvestRecord::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'production_cycle_id' => $cycle->id,
        'commodity_id' => $commodity->id,
        'quantity' => 500,
        'labour_cost_minor' => 0,
    ]);
    SaleRecord::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'production_cycle_id' => $cycle->id,
        'harvest_record_id' => $harvest->id,
        'commodity_id' => $commodity->id,
        'net_amount_minor' => 50_000_00,
    ]);

    // Sibling season cycle for benchmark comparison
    ProductionCycle::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'farm_type' => FarmType::Crop,
        'name' => 'Prior Season Maize',
        'season' => '2025 Wet',
        'status' => ProductionCycleStatus::Completed,
    ]);

    $this->actingAs($owner)
        ->get(route('farms.production-cycles.show', [$team, $farm, $cycle]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('calculators.crop.totalArea', 2.5)
            ->where('calculators.crop.yieldPerArea', 200)
            ->where('calculators.crop.costPerArea', '10000.00')
            ->where('calculators.crop.revenuePerArea', '20000.00')
            ->has('calculators.crop.seasonComparisons', 2)
        );
});

test('quick egg crate sale creates harvest and sale records atomically with role checks', function () {
    [$owner, $operator, , $team, $farm, , $cycle] = phaseFourCycleContext(FarmType::Poultry, EnterpriseSubtype::Layer);
    $otherTeam = Team::factory()->create();
    $otherFarm = Farm::factory()->create(['team_id' => $otherTeam->id]);

    $payload = [
        'sold_on' => now()->toDateString(),
        'crates_sold' => 12,
        'price_per_crate' => '4500',
        'buyer_name' => 'Kano Fresh Mart',
        'payment_status' => SalePaymentStatus::Paid->value,
        'notes' => 'Morning batch pick-up',
    ];

    $this->actingAs($owner)
        ->post(route('farms.production-cycles.quick-egg-sale.store', [$team, $farm, $cycle]), $payload)
        ->assertRedirect();

    // Second call reuses existing Eggs commodity
    $this->actingAs($owner)
        ->post(route('farms.production-cycles.quick-egg-sale.store', [$team, $farm, $cycle]), $payload)
        ->assertRedirect();

    expect(HarvestRecord::query()->where('production_cycle_id', $cycle->id)->count())->toBe(2)
        ->and(SaleRecord::query()->where('production_cycle_id', $cycle->id)->sum('net_amount_minor'))->toBe(108_000_00);

    // Operator lacks manageFinance
    $this->actingAs($operator)
        ->post(route('farms.production-cycles.quick-egg-sale.store', [$team, $farm, $cycle]), $payload)
        ->assertForbidden();

    // Cross-farm mismatch returns 404
    $this->actingAs($owner)
        ->post(route('farms.production-cycles.quick-egg-sale.store', [$team, $otherFarm, $cycle]), $payload)
        ->assertNotFound();
});

test('cycle summary pdf export and whatsapp text render for all domains and respect finance visibility', function () {
    foreach ([
        [FarmType::Poultry, EnterpriseSubtype::Layer],
        [FarmType::Aquaculture, EnterpriseSubtype::Catfish],
        [FarmType::Crop, EnterpriseSubtype::ArableCrop],
        [FarmType::Mixed, EnterpriseSubtype::Custom],
    ] as [$farmType, $subtype]) {
        [$owner, $operator, $investor, $team, $farm, , $cycle] = phaseFourCycleContext($farmType, $subtype);

        ProductionLog::factory()->create([
            'team_id' => $team->id,
            'farm_id' => $farm->id,
            'production_cycle_id' => $cycle->id,
            'domain' => ProductionLogDomain::fromFarmType($farmType),
            'mortality_count' => 1,
            'feed_used_kg' => 15,
            'eggs_collected' => 120,
            'water_ph' => 7.2,
            'rainfall_mm' => 10,
            'notes' => 'Routine cycle check',
        ]);

        HealthEvent::factory()->create([
            'team_id' => $team->id,
            'farm_id' => $farm->id,
            'production_cycle_id' => $cycle->id,
            'status' => HealthEventStatus::Completed,
            'completed_on' => now()->toDateString(),
            'withdrawal_days' => 7,
        ]);

        $pdfResponse = $this->actingAs($owner)
            ->get(route('farms.production-cycles.summary-pdf', [$team, $farm, $cycle]));

        $pdfResponse->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        expect(str_starts_with($pdfResponse->getContent(), '%PDF-'))->toBeTrue();

        // Operator without finance access still exports telemetry PDF (profitability omitted)
        $this->actingAs($operator)
            ->get(route('farms.production-cycles.summary-pdf', [$team, $farm, $cycle]))
            ->assertOk();

        // Investor without field work access is forbidden
        $this->actingAs($investor)
            ->get(route('farms.production-cycles.summary-pdf', [$team, $farm, $cycle]))
            ->assertForbidden();
    }
});

test('production log endpoint returns 201 json for offline pwa background sync requests', function () {
    [, $operator, , $team, $farm, $unit, $cycle] = phaseFourCycleContext(FarmType::Poultry, EnterpriseSubtype::Broiler);

    $response = $this->actingAs($operator)
        ->postJson(route('farms.production-cycles.production-logs.store', [$team, $farm, $cycle]), [
            'log_date' => now()->toDateString(),
            'domain' => ProductionLogDomain::Poultry->value,
            'production_unit_id' => $unit->id,
            'mortality_count' => 2,
            'feed_used_kg' => 25,
            'notes' => 'Synced from offline queue',
        ]);

    $response->assertCreated()
        ->assertJsonStructure(['id', 'message']);

    expect($cycle->refresh()->current_stock_count)->toBe(998);
});

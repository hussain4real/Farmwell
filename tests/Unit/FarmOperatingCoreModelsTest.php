<?php

use App\Enums\CommodityRole;
use App\Enums\FarmType;
use App\Enums\ProductionCycleStatus;
use App\Enums\ProductionPlanChangeType;
use App\Enums\ProductionUnitType;
use App\Models\Commodity;
use App\Models\Farm;
use App\Models\ProductionCycle;
use App\Models\ProductionPlanChange;
use App\Models\ProductionUnit;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('farm operating enums expose form options', function () {
    expect(FarmType::options())->toContain(['value' => FarmType::Crop->value, 'label' => 'Crop'])
        ->and(FarmType::options())->toContain(['value' => FarmType::Mixed->value, 'label' => 'Mixed'])
        ->and(ProductionUnitType::options())->toContain(['value' => ProductionUnitType::Pond->value, 'label' => 'Pond'])
        ->and(ProductionCycleStatus::options())->toContain(['value' => ProductionCycleStatus::Changed->value, 'label' => 'Changed'])
        ->and(CommodityRole::options())->toContain(['value' => CommodityRole::Intercrop->value, 'label' => 'Intercrop'])
        ->and(ProductionPlanChangeType::options())->toContain(['value' => ProductionPlanChangeType::RiskDecision->value, 'label' => 'Risk or decision']);
});

test('farm operating models expose tenant relationships and casts', function () {
    $team = Team::factory()->create();
    $actor = User::factory()->create();
    $farm = Farm::factory()->create([
        'team_id' => $team->id,
        'farm_type' => FarmType::Mixed,
    ]);
    $unit = ProductionUnit::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'unit_type' => ProductionUnitType::Field,
        'size' => 2.5,
    ]);
    $commodity = Commodity::factory()->create([
        'team_id' => $team->id,
        'farm_type' => FarmType::Crop,
    ]);
    $cycle = ProductionCycle::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'farm_type' => FarmType::Mixed,
        'status' => ProductionCycleStatus::Active,
    ]);

    $cycle->productionUnits()->attach($unit);
    $cycle->commodities()->attach($commodity, [
        'role' => CommodityRole::Mixed->value,
        'expected_output_quantity' => 5,
        'expected_output_unit' => 'tons',
    ]);

    $planChange = ProductionPlanChange::factory()->create([
        'team_id' => $team->id,
        'production_cycle_id' => $cycle->id,
        'actor_id' => $actor->id,
        'change_type' => ProductionPlanChangeType::UnitAllocation,
    ]);

    expect($team->farms()->first()?->is($farm))->toBeTrue()
        ->and($team->commodities()->first()?->is($commodity))->toBeTrue()
        ->and($team->productionUnits()->first()?->is($unit))->toBeTrue()
        ->and($team->productionCycles()->first()?->is($cycle))->toBeTrue()
        ->and($farm->team->is($team))->toBeTrue()
        ->and($farm->farm_type)->toBe(FarmType::Mixed)
        ->and($farm->productionUnits()->first()?->is($unit))->toBeTrue()
        ->and($farm->productionCycles()->first()?->is($cycle))->toBeTrue()
        ->and($unit->team->is($team))->toBeTrue()
        ->and($unit->farm->is($farm))->toBeTrue()
        ->and($unit->unit_type)->toBe(ProductionUnitType::Field)
        ->and($unit->size)->toBe('2.50')
        ->and($unit->productionCycles()->first()?->is($cycle))->toBeTrue()
        ->and($commodity->team->is($team))->toBeTrue()
        ->and($commodity->farm_type)->toBe(FarmType::Crop)
        ->and($commodity->productionCycles()->first()?->is($cycle))->toBeTrue()
        ->and($cycle->team->is($team))->toBeTrue()
        ->and($cycle->farm->is($farm))->toBeTrue()
        ->and($cycle->status)->toBe(ProductionCycleStatus::Active)
        ->and($cycle->productionUnits()->first()?->is($unit))->toBeTrue()
        ->and($cycle->commodities()->first()?->is($commodity))->toBeTrue()
        ->and($cycle->planChanges()->first()?->is($planChange))->toBeTrue()
        ->and($planChange->team->is($team))->toBeTrue()
        ->and($planChange->productionCycle->is($cycle))->toBeTrue()
        ->and($planChange->actor?->is($actor))->toBeTrue()
        ->and($planChange->change_type)->toBe(ProductionPlanChangeType::UnitAllocation);
});

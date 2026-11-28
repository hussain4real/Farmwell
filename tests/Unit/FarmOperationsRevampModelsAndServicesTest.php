<?php

use App\Actions\FarmOperations\BuildCycleProfitability;
use App\Actions\FarmOperations\CalculateCycleTelemetry;
use App\Actions\FarmOperations\CompleteFarmOnboarding;
use App\Actions\FarmOperations\RecordProductionLog;
use App\Actions\Inventory\RecordInventoryMovement;
use App\Enums\CropGrowthStage;
use App\Enums\EnterpriseSubtype;
use App\Enums\ExpenseStatus;
use App\Enums\FarmType;
use App\Enums\HealthEventStatus;
use App\Enums\HealthEventType;
use App\Enums\InventoryCategory;
use App\Enums\InventoryMovementType;
use App\Enums\PestPressure;
use App\Enums\ProductionLogDomain;
use App\Models\Commodity;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Farm;
use App\Models\HarvestRecord;
use App\Models\HealthEvent;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\ProductionCycle;
use App\Models\ProductionLog;
use App\Models\ProductionUnit;
use App\Models\SaleRecord;
use App\Models\Team;
use App\Models\User;
use App\Support\FarmTemplates;
use App\Support\HealthScheduleTemplates;
use Database\Seeders\FarmwellMultiFarmDemoSeeder;
use Database\Seeders\FarmwellTestingUsersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('all new domain enums expose labels, helpers, and options', function () {
    expect(EnterpriseSubtype::Broiler->label())->toBe('Broilers')
        ->and(EnterpriseSubtype::Broiler->farmType())->toBe(FarmType::Poultry)
        ->and(EnterpriseSubtype::Catfish->farmType())->toBe(FarmType::Aquaculture)
        ->and(EnterpriseSubtype::ArableCrop->farmType())->toBe(FarmType::Crop)
        ->and(EnterpriseSubtype::TreeCrop->farmType())->toBe(FarmType::Plantation)
        ->and(EnterpriseSubtype::MixedLivestock->farmType())->toBe(FarmType::Livestock)
        ->and(EnterpriseSubtype::Custom->farmType())->toBe(FarmType::Mixed)
        ->and(EnterpriseSubtype::options())->toHaveCount(11);

    expect(CropGrowthStage::Vegetative->label())->toBe('Vegetative Growth')
        ->and(CropGrowthStage::Vegetative->progressPercentage())->toBe(55)
        ->and(CropGrowthStage::PostHarvest->progressPercentage())->toBe(100)
        ->and(CropGrowthStage::options())->toHaveCount(10);

    expect(ProductionLogDomain::fromFarmType(FarmType::Poultry))->toBe(ProductionLogDomain::Poultry)
        ->and(ProductionLogDomain::fromFarmType(FarmType::Aquaculture))->toBe(ProductionLogDomain::Aquaculture)
        ->and(ProductionLogDomain::fromFarmType(FarmType::Crop))->toBe(ProductionLogDomain::Crop)
        ->and(ProductionLogDomain::fromFarmType(FarmType::Plantation))->toBe(ProductionLogDomain::Crop)
        ->and(ProductionLogDomain::fromFarmType(FarmType::Mixed))->toBe(ProductionLogDomain::General)
        ->and(ProductionLogDomain::Aquaculture->label())->toBe('Fish / Aquaculture')
        ->and(ProductionLogDomain::options())->toHaveCount(4);

    expect(PestPressure::High->label())->toBe('High')
        ->and(PestPressure::options())->toHaveCount(4);

    expect(InventoryCategory::Feed->label())->toBe('Feed')
        ->and(InventoryCategory::Feed->expenseCategorySlug())->toBe('feeding')
        ->and(InventoryCategory::Seed->expenseCategorySlug())->toBe('seeds-stock')
        ->and(InventoryCategory::Fertilizer->expenseCategorySlug())->toBe('fertilizers')
        ->and(InventoryCategory::Agrochemical->expenseCategorySlug())->toBe('pest-control')
        ->and(InventoryCategory::Vaccine->expenseCategorySlug())->toBe('health-care')
        ->and(InventoryCategory::Packaging->expenseCategorySlug())->toBe('harvest-output-handling')
        ->and(InventoryCategory::Equipment->expenseCategorySlug())->toBe('tools')
        ->and(InventoryCategory::Fuel->expenseCategorySlug())->toBe('contingency')
        ->and(InventoryCategory::options())->toHaveCount(10);

    expect(InventoryMovementType::Usage->isDeduction())->toBeTrue()
        ->and(InventoryMovementType::Purchase->isDeduction())->toBeFalse()
        ->and(InventoryMovementType::Purchase->label())->toBe('Restock / Purchase')
        ->and(InventoryMovementType::options())->toHaveCount(5);

    expect(HealthEventType::Vaccination->label())->toBe('Vaccination')
        ->and(HealthEventType::options())->toHaveCount(8)
        ->and(HealthEventStatus::Scheduled->label())->toBe('Scheduled')
        ->and(HealthEventStatus::options())->toHaveCount(4);
});

test('new models expose expected relationships and helpers', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $farm = Farm::factory()->create(['team_id' => $team->id]);
    $unit = ProductionUnit::factory()->create(['team_id' => $team->id, 'farm_id' => $farm->id]);
    $cycle = ProductionCycle::factory()->create(['team_id' => $team->id, 'farm_id' => $farm->id]);
    $item = InventoryItem::factory()->create(['team_id' => $team->id, 'farm_id' => $farm->id]);

    $log = ProductionLog::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'production_cycle_id' => $cycle->id,
        'production_unit_id' => $unit->id,
        'recorded_by_id' => $owner->id,
        'inventory_item_id' => $item->id,
    ]);

    $movement = InventoryMovement::factory()->create([
        'team_id' => $team->id,
        'inventory_item_id' => $item->id,
        'farm_id' => $farm->id,
        'production_cycle_id' => $cycle->id,
        'production_log_id' => $log->id,
        'recorded_by_id' => $owner->id,
    ]);

    $health = HealthEvent::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'production_cycle_id' => $cycle->id,
        'production_unit_id' => $unit->id,
        'inventory_item_id' => $item->id,
        'recorded_by_id' => $owner->id,
    ]);

    expect($log->team->is($team))->toBeTrue()
        ->and($log->farm->is($farm))->toBeTrue()
        ->and($log->productionCycle->is($cycle))->toBeTrue()
        ->and($log->productionUnit->is($unit))->toBeTrue()
        ->and($log->recordedBy->is($owner))->toBeTrue()
        ->and($log->inventoryItem->is($item))->toBeTrue()
        ->and($movement->team->is($team))->toBeTrue()
        ->and($movement->farm->is($farm))->toBeTrue()
        ->and($movement->productionCycle->is($cycle))->toBeTrue()
        ->and($movement->productionLog->is($log))->toBeTrue()
        ->and($movement->recordedBy->is($owner))->toBeTrue()
        ->and($movement->expense)->toBeNull()
        ->and($health->team->is($team))->toBeTrue()
        ->and($health->farm->is($farm))->toBeTrue()
        ->and($health->productionCycle->is($cycle))->toBeTrue()
        ->and($health->productionUnit->is($unit))->toBeTrue()
        ->and($health->inventoryItem->is($item))->toBeTrue()
        ->and($health->recordedBy->is($owner))->toBeTrue()
        ->and($health->withdrawalEndsOn())->toBeNull()
        ->and($item->team->is($team))->toBeTrue()
        ->and($item->movements()->count())->toBe(1)
        ->and($unit->productionLogs()->count())->toBe(1)
        ->and($unit->healthEvents()->count())->toBe(1)
        ->and($farm->inventoryItems()->count())->toBe(1)
        ->and($team->productionLogs()->count())->toBe(1)
        ->and($team->inventoryMovements()->count())->toBe(1)
        ->and($team->healthEvents()->count())->toBe(1);
});

test('templates, cycle profitability, and multi-farm demo seeder work accurately', function () {
    expect(FarmTemplates::find('poultry_broiler'))->not->toBeNull()
        ->and(FarmTemplates::find('missing'))->toBeNull()
        ->and(HealthScheduleTemplates::find('aquaculture_catfish'))->not->toBeNull()
        ->and(HealthScheduleTemplates::find('missing'))->toBeNull()
        ->and(HealthScheduleTemplates::options())->toHaveCount(5);

    // Cycle profitability calculation
    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $farm = Farm::factory()->create(['team_id' => $team->id, 'farm_type' => FarmType::Poultry]);
    $cycle = ProductionCycle::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'farm_type' => FarmType::Poultry,
        'planned_end_on' => null,
        'current_stock_count' => 100,
    ]);
    $category = ExpenseCategory::factory()->create(['team_id' => $team->id]);
    Expense::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'production_cycle_id' => $cycle->id,
        'expense_category_id' => $category->id,
        'amount_minor' => 50_000_00,
        'currency' => 'NGN',
        'status' => ExpenseStatus::Approved,
    ]);
    $item = InventoryItem::factory()->create(['team_id' => $team->id, 'currency' => 'NGN']);
    InventoryMovement::factory()->create([
        'team_id' => $team->id,
        'inventory_item_id' => $item->id,
        'production_cycle_id' => $cycle->id,
        'movement_type' => InventoryMovementType::Usage,
        'total_cost_minor' => 20_000_00,
        'currency' => 'NGN',
    ]);
    HealthEvent::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'production_cycle_id' => $cycle->id,
        'cost_minor' => 5_000_00,
        'currency' => 'NGN',
    ]);
    $commodity = Commodity::factory()->create(['team_id' => $team->id]);
    $harvest = HarvestRecord::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'production_cycle_id' => $cycle->id,
        'commodity_id' => $commodity->id,
        'quantity' => 100,
        'labour_cost_minor' => 5_000_00,
        'currency' => 'NGN',
    ]);
    SaleRecord::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'production_cycle_id' => $cycle->id,
        'harvest_record_id' => $harvest->id,
        'commodity_id' => $commodity->id,
        'net_amount_minor' => 120_000_00,
        'currency' => 'NGN',
    ]);

    $profit = app(BuildCycleProfitability::class)->handle($cycle);
    expect($profit['totalCostMinor'])->toBe(80_000_00)
        ->and($profit['netRevenueMinor'])->toBe(120_000_00)
        ->and($profit['grossProfitMinor'])->toBe(40_000_00)
        ->and($profit['marginPercent'])->toBe(33.3)
        ->and($profit['costPerLiveUnitMinor'])->toBe(80_000)
        ->and($profit['costPerHarvestedUnitMinor'])->toBe(80_000);

    expect(app(CalculateCycleTelemetry::class)->handle($cycle)['progressPercent'])->toBe(0);

    // Multi-farm demo seeder is idempotent and skipped in production
    $this->seed(FarmwellTestingUsersSeeder::class);
    $this->seed(FarmwellMultiFarmDemoSeeder::class);
    $this->seed(FarmwellMultiFarmDemoSeeder::class);

    $demoTeam = Team::query()->where('slug', FarmwellTestingUsersSeeder::TeamSlug)->firstOrFail();
    expect($demoTeam->farms()->where('name', FarmwellMultiFarmDemoSeeder::PoultryFarmName)->count())->toBe(1)
        ->and($demoTeam->farms()->where('name', FarmwellMultiFarmDemoSeeder::CropFarmName)->count())->toBe(1)
        ->and($demoTeam->farms()->where('name', FarmwellMultiFarmDemoSeeder::FishFarmName)->count())->toBe(1);

    $this->app->detectEnvironment(fn () => 'production');
    try {
        app(FarmwellMultiFarmDemoSeeder::class)->run(
            app(CompleteFarmOnboarding::class),
            app(RecordProductionLog::class),
            app(RecordInventoryMovement::class),
        );
    } finally {
        $this->app->detectEnvironment(fn () => 'testing');
    }
});

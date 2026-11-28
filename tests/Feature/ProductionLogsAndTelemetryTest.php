<?php

use App\Enums\CropGrowthStage;
use App\Enums\FarmType;
use App\Enums\InventoryCategory;
use App\Enums\PestPressure;
use App\Enums\ProductionCycleStatus;
use App\Enums\ProductionLogDomain;
use App\Enums\ProductionUnitType;
use App\Enums\TeamRole;
use App\Models\Commodity;
use App\Models\Farm;
use App\Models\HarvestRecord;
use App\Models\InventoryItem;
use App\Models\ProductionCycle;
use App\Models\ProductionLog;
use App\Models\ProductionUnit;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

function logTestContext(FarmType $farmType = FarmType::Poultry): array
{
    $owner = User::factory()->create();
    $operator = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($operator, ['role' => TeamRole::Operator->value]);
    $owner->switchTeam($team);
    $operator->switchTeam($team);

    $farm = Farm::factory()->create(['team_id' => $team->id, 'farm_type' => $farmType]);
    $unit = ProductionUnit::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'unit_type' => match ($farmType) {
            FarmType::Poultry => ProductionUnitType::House,
            FarmType::Aquaculture => ProductionUnitType::Pond,
            default => ProductionUnitType::Field,
        },
        'size' => 2,
        'size_unit' => 'acres',
    ]);
    $cycle = ProductionCycle::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'farm_type' => $farmType,
        'status' => ProductionCycleStatus::Active,
        'planned_start_on' => now()->subDays(10)->toDateString(),
        'planned_end_on' => now()->addDays(40)->toDateString(),
        'initial_stock_count' => $farmType === FarmType::Crop ? null : 500,
        'current_stock_count' => $farmType === FarmType::Crop ? null : 500,
        'initial_average_weight_g' => $farmType === FarmType::Crop ? null : 40,
    ]);
    $cycle->productionUnits()->attach($unit);

    return [$owner, $operator, $team, $farm, $unit, $cycle];
}

test('poultry daily logs update live flock count, deduct bag inventory, compute FCR and lay rate, and serve private evidence', function () {
    Storage::fake('farmwell_private');
    [$owner, $operator, $team, $farm, $unit, $cycle] = logTestContext(FarmType::Poultry);

    $feedItem = InventoryItem::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'name' => 'Broiler Finisher',
        'category' => InventoryCategory::Feed,
        'unit' => 'bags',
        'unit_weight_kg' => 25,
        'current_quantity' => 10,
        'unit_cost_minor' => 18_000_00,
    ]);

    $this->actingAs($operator)
        ->post(route('farms.production-cycles.production-logs.store', [$team, $farm, $cycle]), [
            'log_date' => now()->toDateString(),
            'domain' => ProductionLogDomain::Poultry->value,
            'production_unit_id' => $unit->id,
            'inventory_item_id' => $feedItem->id,
            'mortality_count' => 3,
            'cull_count' => 2,
            'feed_used_kg' => 50,
            'feed_type' => 'Broiler Finisher',
            'water_used_liters' => 120,
            'average_weight_g' => 1200,
            'sample_size' => 20,
            'eggs_collected' => 300,
            'eggs_damaged' => 6,
            'notes' => 'Healthy flock',
            'evidence' => [UploadedFile::fake()->image('flock.jpg')],
            'evidence_caption' => 'Morning check',
        ])
        ->assertRedirect();

    expect($cycle->refresh()->current_stock_count)->toBe(495)
        ->and((float) $feedItem->refresh()->current_quantity)->toBe(8.0);

    $log = ProductionLog::query()->where('production_cycle_id', $cycle->id)->firstOrFail();
    $media = $log->getFirstMedia(ProductionLog::EvidenceCollection);
    expect($media)->not->toBeNull();

    // Operator and Owner can download ProductionLog evidence
    $this->actingAs($operator)
        ->get(route('farm-evidence.show', [$team, $media]))
        ->assertOk();

    $this->actingAs($owner)
        ->get(route('farms.production-cycles.show', [$team, $farm, $cycle]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('telemetry.stock.currentCount', 495)
            ->where('telemetry.stock.totalMortality', 3)
            ->where('telemetry.poultry.totalCrates', 10)
            ->where('telemetry.feeding.totalFeedKg', 50)
        );

    // Delete log restores stock count
    $this->actingAs($owner)
        ->delete(route('farms.production-cycles.production-logs.destroy', [$team, $farm, $cycle, $log]))
        ->assertRedirect();

    expect($cycle->refresh()->current_stock_count)->toBe(500);
});

test('aquaculture daily logs trigger water quality alerts and support pond grading transfers', function () {
    [$owner, $operator, $team, $farm, $unit, $cycle] = logTestContext(FarmType::Aquaculture);
    $pondTwo = ProductionUnit::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'name' => 'Pond 2',
        'unit_type' => ProductionUnitType::Pond,
    ]);
    $targetCycle = ProductionCycle::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'farm_type' => FarmType::Aquaculture,
        'initial_stock_count' => 100,
        'current_stock_count' => 100,
    ]);

    // Warning water readings
    $this->actingAs($operator)
        ->post(route('farms.production-cycles.production-logs.store', [$team, $farm, $cycle]), [
            'log_date' => now()->subDay()->toDateString(),
            'domain' => ProductionLogDomain::Aquaculture->value,
            'production_unit_id' => $unit->id,
            'feed_used_kg' => 12,
            'feed_pellet_size_mm' => '4mm',
            'water_ph' => 6.0,
            'water_temp_c' => 34.5,
            'dissolved_oxygen_mg_l' => 2.5,
            'ammonia_mg_l' => 1.2,
            'water_exchanged' => true,
            'average_weight_g' => 250,
        ])
        ->assertRedirect();

    $this->actingAs($owner)
        ->get(route('farms.production-cycles.show', [$team, $farm, $cycle]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('telemetry.aquaculture.waterStatus', 'critical')
            ->has('telemetry.aquaculture.waterAlerts', 4)
        );

    // Grade transfer exceeding stock fails validation
    $this->actingAs($operator)
        ->post(route('farms.production-cycles.grade-transfer.store', [$team, $farm, $cycle]), [
            'source_unit_id' => $unit->id,
            'target_unit_id' => $pondTwo->id,
            'target_cycle_id' => $targetCycle->id,
            'count' => 999,
            'transfer_date' => now()->toDateString(),
        ])
        ->assertSessionHasErrors('count');

    // Valid grade transfer moves shooters
    $this->actingAs($operator)
        ->post(route('farms.production-cycles.grade-transfer.store', [$team, $farm, $cycle]), [
            'source_unit_id' => $unit->id,
            'target_unit_id' => $pondTwo->id,
            'target_cycle_id' => $targetCycle->id,
            'count' => 120,
            'transfer_date' => now()->toDateString(),
            'average_weight_g' => 340,
        ])
        ->assertRedirect();

    expect($cycle->refresh()->current_stock_count)->toBe(380)
        ->and($targetCycle->refresh()->current_stock_count)->toBe(220)
        ->and($targetCycle->productionUnits()->whereKey($pondTwo->id)->exists())->toBeTrue();
});

test('crop daily logs advance growth stage, track scouting and deduct unit inventory', function () {
    [$owner, $operator, $team, $farm, $unit, $cycle] = logTestContext(FarmType::Crop);

    $fertilizer = InventoryItem::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'name' => 'NPK 15-15-15',
        'category' => InventoryCategory::Fertilizer,
        'unit' => 'bags',
        'unit_weight_kg' => 50,
        'current_quantity' => 10,
    ]);

    $this->actingAs($operator)
        ->post(route('farms.production-cycles.production-logs.store', [$team, $farm, $cycle]), [
            'log_date' => now()->toDateString(),
            'domain' => ProductionLogDomain::Crop->value,
            'production_unit_id' => $unit->id,
            'inventory_item_id' => $fertilizer->id,
            'growth_stage' => CropGrowthStage::Vegetative->value,
            'rainfall_mm' => 18.5,
            'irrigation_applied' => true,
            'pest_pressure' => PestPressure::High->value,
            'disease_observed' => 'Leaf spot',
            'input_applied_name' => 'NPK 15-15-15',
            'input_applied_quantity' => 2,
            'input_applied_unit' => 'bags',
        ])
        ->assertRedirect();

    $commodity = Commodity::factory()->create(['team_id' => $team->id, 'farm_type' => FarmType::Crop]);
    HarvestRecord::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'production_cycle_id' => $cycle->id,
        'commodity_id' => $commodity->id,
        'quantity' => 1000,
    ]);

    expect($cycle->refresh()->current_growth_stage)->toBe(CropGrowthStage::Vegetative)
        ->and((float) $fertilizer->refresh()->current_quantity)->toBe(8.0);

    // Kg-based feed item and zero-quantity linked observation log
    $looseFeed = InventoryItem::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'name' => 'Bulk Maize Meal',
        'category' => InventoryCategory::Feed,
        'unit' => 'kg',
        'unit_weight_kg' => null,
        'current_quantity' => 50,
    ]);

    $this->actingAs($operator)
        ->post(route('farms.production-cycles.production-logs.store', [$team, $farm, $cycle]), [
            'log_date' => now()->toDateString(),
            'domain' => ProductionLogDomain::General->value,
            'production_unit_id' => $unit->id,
            'inventory_item_id' => $looseFeed->id,
            'feed_used_kg' => 12,
        ])
        ->assertRedirect();

    expect((float) $looseFeed->refresh()->current_quantity)->toBe(38.0);

    $this->actingAs($operator)
        ->post(route('farms.production-cycles.production-logs.store', [$team, $farm, $cycle]), [
            'log_date' => now()->toDateString(),
            'domain' => ProductionLogDomain::General->value,
            'production_unit_id' => $unit->id,
            'inventory_item_id' => $looseFeed->id,
            'notes' => 'Visual check only',
        ])
        ->assertRedirect();

    expect((float) $looseFeed->refresh()->current_quantity)->toBe(38.0);

    $this->actingAs($owner)
        ->get(route('farms.production-cycles.show', [$team, $farm, $cycle]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('telemetry.crop.currentGrowthStage', CropGrowthStage::Vegetative->value)
            ->where('telemetry.crop.hasHighPestAlert', true)
            ->where('telemetry.crop.yieldPerUnitArea', 500)
        );
});

test('production log authorization blocks cross-team access and operator deletions', function () {
    [$owner, $operator, $team, $farm, , $cycle] = logTestContext(FarmType::Poultry);
    $log = ProductionLog::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'production_cycle_id' => $cycle->id,
    ]);

    $otherOwner = User::factory()->create();
    $otherTeam = Team::factory()->create();
    $otherTeam->members()->attach($otherOwner, ['role' => TeamRole::Owner->value]);
    $otherOwner->switchTeam($otherTeam);
    $otherFarm = Farm::factory()->create(['team_id' => $otherTeam->id]);
    $otherCycle = ProductionCycle::factory()->create(['team_id' => $otherTeam->id, 'farm_id' => $otherFarm->id]);

    $this->actingAs($operator)
        ->delete(route('farms.production-cycles.production-logs.destroy', [$team, $farm, $cycle, $log]))
        ->assertForbidden();

    $this->actingAs($owner)
        ->post(route('farms.production-cycles.production-logs.store', [$team, $otherFarm, $otherCycle]), [])
        ->assertNotFound();

    $this->actingAs($owner)
        ->delete(route('farms.production-cycles.production-logs.destroy', [$team, $farm, $cycle, ProductionLog::factory()->create([
            'team_id' => $otherTeam->id,
            'farm_id' => $otherFarm->id,
            'production_cycle_id' => $otherCycle->id,
        ])]))
        ->assertNotFound();
});

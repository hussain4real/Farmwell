<?php

namespace Database\Seeders;

use App\Actions\FarmOperations\CompleteFarmOnboarding;
use App\Actions\FarmOperations\RecordProductionLog;
use App\Actions\Inventory\RecordInventoryMovement;
use App\Enums\CropGrowthStage;
use App\Enums\EnterpriseSubtype;
use App\Enums\FarmType;
use App\Enums\InventoryMovementType;
use App\Enums\PestPressure;
use App\Enums\ProductionCycleStatus;
use App\Enums\ProductionLogDomain;
use App\Enums\ProductionUnitType;
use App\Models\InventoryItem;
use App\Models\ProductionCycle;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

class FarmwellMultiFarmDemoSeeder extends Seeder
{
    public const string PoultryFarmName = 'Sunrise Poultry Farm';

    public const string CropFarmName = 'Valley Crop Farm';

    public const string FishFarmName = 'Bluewater Fish Farm';

    public function run(
        CompleteFarmOnboarding $onboard,
        RecordProductionLog $recordLog,
        RecordInventoryMovement $recordMovement,
    ): void {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $team = Team::query()->where('slug', FarmwellTestingUsersSeeder::TeamSlug)->firstOrFail();
        $owner = User::query()->where('email', FarmwellTestingUsersSeeder::Users['owner']['email'])->firstOrFail();

        $this->seedPoultry($team, $owner, $onboard, $recordLog, $recordMovement);
        $this->seedCrop($team, $owner, $onboard, $recordLog, $recordMovement);
        $this->seedFish($team, $owner, $onboard, $recordLog, $recordMovement);
    }

    private function seedPoultry(
        Team $team,
        User $owner,
        CompleteFarmOnboarding $onboard,
        RecordProductionLog $recordLog,
        RecordInventoryMovement $recordMovement,
    ): void {
        if ($team->farms()->where('name', self::PoultryFarmName)->exists()) {
            return;
        }

        $farm = $onboard->handle($team, $owner, [
            'template_key' => 'poultry_broiler',
            'farm_name' => self::PoultryFarmName,
            'farm_type' => FarmType::Poultry->value,
            'location' => 'Ibadan, Oyo State',
            'units' => [
                [
                    'name' => 'Broiler House A',
                    'unit_type' => ProductionUnitType::House->value,
                    'size' => 120,
                    'size_unit' => 'sqm',
                    'capacity' => '1000 birds',
                    'housing_system' => 'Deep litter',
                ],
            ],
            'commodity_name' => 'Broiler Chicken',
            'commodity_unit' => 'birds',
            'cycle_name' => 'Batch 2026-Q4 Broilers',
            'enterprise_subtype' => EnterpriseSubtype::Broiler->value,
            'breed_or_variety' => 'Cobb 500',
            'planned_start_on' => now()->subDays(21)->toDateString(),
            'planned_end_on' => now()->addDays(28)->toDateString(),
            'initial_stock_count' => 1000,
            'initial_average_weight_g' => 42,
            'target_weight_g' => 2500,
            'expected_output_quantity' => 960,
            'expected_output_unit' => 'birds',
            'cycle_status' => ProductionCycleStatus::Active->value,
            'create_starter_tasks' => true,
            'create_starter_inventory' => true,
            'create_health_schedule' => true,
        ]);

        /** @var ProductionCycle $cycle */
        $cycle = $farm->productionCycles()->firstOrFail();
        $unit = $farm->productionUnits()->firstOrFail();

        /** @var InventoryItem $starterFeed */
        $starterFeed = $team->inventoryItems()
            ->where('farm_id', $farm->id)
            ->where('name', 'Broiler Starter Mash')
            ->firstOrFail();

        $recordMovement->handle($team, $starterFeed, $owner, [
            'farm_id' => $farm->id,
            'production_cycle_id' => $cycle->id,
            'movement_type' => InventoryMovementType::Purchase,
            'quantity' => 40,
            'unit_cost_minor' => 18_500_00,
            'movement_date' => now()->subDays(22)->toDateString(),
            'reference' => 'PO-POULTRY-01',
        ]);

        for ($day = 5; $day >= 1; $day--) {
            $recordLog->handle($team, $farm, $owner, [
                'log_date' => now()->subDays($day)->toDateString(),
                'domain' => ProductionLogDomain::Poultry->value,
                'production_unit_id' => $unit->id,
                'inventory_item_id' => $starterFeed->id,
                'mortality_count' => $day === 3 ? 2 : 1,
                'feed_used_kg' => 50,
                'feed_type' => 'Broiler Starter Mash',
                'water_used_liters' => 180,
                'average_weight_g' => 850 + ((5 - $day) * 75),
                'sample_size' => 25,
                'notes' => 'Flock active and feeding evenly.',
            ], $cycle);
        }
    }

    private function seedCrop(
        Team $team,
        User $owner,
        CompleteFarmOnboarding $onboard,
        RecordProductionLog $recordLog,
        RecordInventoryMovement $recordMovement,
    ): void {
        if ($team->farms()->where('name', self::CropFarmName)->exists()) {
            return;
        }

        $farm = $onboard->handle($team, $owner, [
            'template_key' => 'crop_arable',
            'farm_name' => self::CropFarmName,
            'farm_type' => FarmType::Crop->value,
            'location' => 'Zaria, Kaduna State',
            'units' => [
                [
                    'name' => 'East Maize Plot',
                    'unit_type' => ProductionUnitType::Field->value,
                    'size' => 5,
                    'size_unit' => 'acres',
                    'water_source' => 'Rain-fed + Borehole drip',
                ],
            ],
            'commodity_name' => 'Yellow Maize',
            'commodity_unit' => 'kg',
            'cycle_name' => 'Dry-Season Irrigated Maize',
            'enterprise_subtype' => EnterpriseSubtype::ArableCrop->value,
            'breed_or_variety' => 'SAMMAZ 52',
            'planned_start_on' => now()->subDays(30)->toDateString(),
            'planned_end_on' => now()->addDays(90)->toDateString(),
            'current_growth_stage' => CropGrowthStage::Planting->value,
            'expected_output_quantity' => 9000,
            'expected_output_unit' => 'kg',
            'cycle_status' => ProductionCycleStatus::Active->value,
            'create_starter_tasks' => true,
            'create_starter_inventory' => true,
            'create_health_schedule' => true,
        ]);

        $cycle = $farm->productionCycles()->firstOrFail();
        $unit = $farm->productionUnits()->firstOrFail();

        $fertilizer = $team->inventoryItems()
            ->where('farm_id', $farm->id)
            ->where('name', 'NPK 15-15-15 Fertilizer')
            ->firstOrFail();

        $recordMovement->handle($team, $fertilizer, $owner, [
            'farm_id' => $farm->id,
            'production_cycle_id' => $cycle->id,
            'movement_type' => InventoryMovementType::Purchase,
            'quantity' => 15,
            'unit_cost_minor' => 42_000_00,
            'movement_date' => now()->subDays(25)->toDateString(),
        ]);

        $recordLog->handle($team, $farm, $owner, [
            'log_date' => now()->subDays(4)->toDateString(),
            'domain' => ProductionLogDomain::Crop->value,
            'production_unit_id' => $unit->id,
            'inventory_item_id' => $fertilizer->id,
            'growth_stage' => CropGrowthStage::Vegetative->value,
            'irrigation_applied' => true,
            'rainfall_mm' => 0,
            'pest_pressure' => PestPressure::Low->value,
            'input_applied_name' => 'NPK 15-15-15',
            'input_applied_quantity' => 4,
            'input_applied_unit' => 'bags',
            'notes' => 'Uniform emergence; first side-dress applied.',
        ], $cycle);
    }

    private function seedFish(
        Team $team,
        User $owner,
        CompleteFarmOnboarding $onboard,
        RecordProductionLog $recordLog,
        RecordInventoryMovement $recordMovement,
    ): void {
        if ($team->farms()->where('name', self::FishFarmName)->exists()) {
            return;
        }

        $farm = $onboard->handle($team, $owner, [
            'template_key' => 'aquaculture_catfish',
            'farm_name' => self::FishFarmName,
            'farm_type' => FarmType::Aquaculture->value,
            'location' => 'Abeokuta, Ogun State',
            'units' => [
                [
                    'name' => 'Concrete Pond 1',
                    'unit_type' => ProductionUnitType::Pond->value,
                    'size' => 40,
                    'size_unit' => 'sqm',
                    'capacity' => '2000 fish',
                    'water_source' => 'Borehole flow-through',
                ],
                [
                    'name' => 'Concrete Pond 2',
                    'unit_type' => ProductionUnitType::Pond->value,
                    'size' => 40,
                    'size_unit' => 'sqm',
                    'capacity' => '2000 fish',
                    'water_source' => 'Borehole flow-through',
                ],
            ],
            'commodity_name' => 'African Catfish',
            'commodity_unit' => 'kg',
            'cycle_name' => 'Catfish Cycle 2026-B',
            'enterprise_subtype' => EnterpriseSubtype::Catfish->value,
            'breed_or_variety' => 'Clarias gariepinus',
            'planned_start_on' => now()->subDays(40)->toDateString(),
            'planned_end_on' => now()->addDays(110)->toDateString(),
            'initial_stock_count' => 2000,
            'initial_average_weight_g' => 15,
            'target_weight_g' => 1000,
            'expected_output_quantity' => 1800,
            'expected_output_unit' => 'kg',
            'cycle_status' => ProductionCycleStatus::Active->value,
            'create_starter_tasks' => true,
            'create_starter_inventory' => true,
            'create_health_schedule' => true,
        ]);

        $cycle = $farm->productionCycles()->firstOrFail();
        $unit = $farm->productionUnits()->firstOrFail();

        $fishFeed = $team->inventoryItems()
            ->where('farm_id', $farm->id)
            ->where('name', '4mm Floating Fish Feed')
            ->firstOrFail();

        $recordMovement->handle($team, $fishFeed, $owner, [
            'farm_id' => $farm->id,
            'production_cycle_id' => $cycle->id,
            'movement_type' => InventoryMovementType::Purchase,
            'quantity' => 30,
            'unit_cost_minor' => 26_000_00,
            'movement_date' => now()->subDays(20)->toDateString(),
        ]);

        for ($day = 3; $day >= 1; $day--) {
            $recordLog->handle($team, $farm, $owner, [
                'log_date' => now()->subDays($day)->toDateString(),
                'domain' => ProductionLogDomain::Aquaculture->value,
                'production_unit_id' => $unit->id,
                'inventory_item_id' => $fishFeed->id,
                'mortality_count' => $day === 2 ? 3 : 1,
                'feed_used_kg' => 15,
                'feed_pellet_size_mm' => '4mm',
                'water_ph' => 7.2,
                'water_temp_c' => 28.4,
                'dissolved_oxygen_mg_l' => 5.6,
                'ammonia_mg_l' => 0.15,
                'water_exchanged' => $day === 2,
                'average_weight_g' => 240 + ((3 - $day) * 20),
                'sample_size' => 30,
                'notes' => 'Strong feeding response at morning and evening rations.',
            ], $cycle);
        }
    }
}

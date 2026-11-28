<?php

namespace App\Support;

use App\Enums\CropGrowthStage;
use App\Enums\EnterpriseSubtype;
use App\Enums\FarmType;
use App\Enums\InventoryCategory;
use App\Enums\ProductionUnitType;

class FarmTemplates
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            [
                'key' => 'poultry_broiler',
                'name' => 'Poultry — Broiler Farm',
                'tagline' => 'Meat bird batches with brooding, feed conversion (FCR), mortality & vaccines',
                'farmType' => FarmType::Poultry->value,
                'enterpriseSubtype' => EnterpriseSubtype::Broiler->value,
                'defaultUnitType' => ProductionUnitType::House->value,
                'defaultUnitName' => 'Broiler House 1',
                'defaultSizeUnit' => 'sqm',
                'defaultCommodityName' => 'Broiler Chicken',
                'defaultCommodityUnit' => 'birds',
                'defaultBreed' => 'Cobb 500 / Arbor Acres',
                'defaultDurationDays' => 49,
                'defaultInitialStock' => 500,
                'defaultInitialWeightG' => 42,
                'defaultTargetWeightG' => 2500,
                'defaultGrowthStage' => null,
                'defaultExpectedOutputUnit' => 'birds',
                'healthPreset' => 'poultry_broiler',
                'starterTasks' => [
                    ['title' => 'Sanitize brooder & preheat house', 'activity_type' => 'Biosecurity', 'day_offset' => 0],
                    ['title' => 'Day 7 Lasota vaccination', 'activity_type' => 'Vaccination', 'day_offset' => 7],
                    ['title' => 'Day 14 Gumboro vaccination', 'activity_type' => 'Vaccination', 'day_offset' => 14],
                    ['title' => 'Switch feed to Grower / Finisher mash', 'activity_type' => 'Feeding', 'day_offset' => 24],
                    ['title' => 'Week 6 sample weighing for market', 'activity_type' => 'Sampling', 'day_offset' => 42],
                ],
                'starterInventory' => [
                    ['name' => 'Broiler Starter Mash', 'category' => InventoryCategory::Feed->value, 'unit' => 'bags', 'unit_weight_kg' => 25, 'reorder_level' => 5],
                    ['name' => 'Broiler Finisher Mash', 'category' => InventoryCategory::Feed->value, 'unit' => 'bags', 'unit_weight_kg' => 25, 'reorder_level' => 5],
                    ['name' => 'Poultry Multivitamin', 'category' => InventoryCategory::Medication->value, 'unit' => 'bottles', 'unit_weight_kg' => null, 'reorder_level' => 2],
                ],
            ],
            [
                'key' => 'poultry_layer',
                'name' => 'Poultry — Egg Layers',
                'tagline' => 'Daily egg crate collection, lay-rate %, feed tracking & flock biosecurity',
                'farmType' => FarmType::Poultry->value,
                'enterpriseSubtype' => EnterpriseSubtype::Layer->value,
                'defaultUnitType' => ProductionUnitType::House->value,
                'defaultUnitName' => 'Layer Pen 1',
                'defaultSizeUnit' => 'sqm',
                'defaultCommodityName' => 'Table Eggs',
                'defaultCommodityUnit' => 'crates',
                'defaultBreed' => 'Isa Brown',
                'defaultDurationDays' => 365,
                'defaultInitialStock' => 500,
                'defaultInitialWeightG' => 1350,
                'defaultTargetWeightG' => 1900,
                'defaultGrowthStage' => null,
                'defaultExpectedOutputUnit' => 'crates',
                'healthPreset' => 'poultry_layer',
                'starterTasks' => [
                    ['title' => 'Verify nesting boxes & lighting schedule', 'activity_type' => 'Setup', 'day_offset' => 1],
                    ['title' => 'Flock deworming & egg weight audit', 'activity_type' => 'Health', 'day_offset' => 14],
                    ['title' => 'Monthly Newcastle booster check', 'activity_type' => 'Vaccination', 'day_offset' => 60],
                ],
                'starterInventory' => [
                    ['name' => 'Layers Mash', 'category' => InventoryCategory::Feed->value, 'unit' => 'bags', 'unit_weight_kg' => 25, 'reorder_level' => 10],
                    ['name' => 'Egg Crates (30-cell)', 'category' => InventoryCategory::Packaging->value, 'unit' => 'crates', 'unit_weight_kg' => null, 'reorder_level' => 20],
                ],
            ],
            [
                'key' => 'crop_arable',
                'name' => 'Crop — Grains & Arable',
                'tagline' => 'Maize, rice, cassava or soybeans with growth stages, scouting & input logs',
                'farmType' => FarmType::Crop->value,
                'enterpriseSubtype' => EnterpriseSubtype::ArableCrop->value,
                'defaultUnitType' => ProductionUnitType::Field->value,
                'defaultUnitName' => 'Main Field A',
                'defaultSizeUnit' => 'acres',
                'defaultCommodityName' => 'Maize',
                'defaultCommodityUnit' => 'kg',
                'defaultBreed' => 'SAMMAZ / Hybrid',
                'defaultDurationDays' => 120,
                'defaultInitialStock' => null,
                'defaultInitialWeightG' => null,
                'defaultTargetWeightG' => null,
                'defaultGrowthStage' => CropGrowthStage::LandPrep->value,
                'defaultExpectedOutputUnit' => 'kg',
                'healthPreset' => 'crop_arable',
                'starterTasks' => [
                    ['title' => 'Land clearing, plowing & harrowing', 'activity_type' => 'Land Preparation', 'day_offset' => 0],
                    ['title' => 'Planting & pre-emergence herbicide', 'activity_type' => 'Planting', 'day_offset' => 5],
                    ['title' => 'First weeding & NPK fertilizer application', 'activity_type' => 'Fertilizing', 'day_offset' => 21],
                    ['title' => 'Urea top-dressing & pest scouting', 'activity_type' => 'Scouting', 'day_offset' => 42],
                ],
                'starterInventory' => [
                    ['name' => 'Certified Maize Seed', 'category' => InventoryCategory::Seed->value, 'unit' => 'kg', 'unit_weight_kg' => 1, 'reorder_level' => 5],
                    ['name' => 'NPK 15-15-15 Fertilizer', 'category' => InventoryCategory::Fertilizer->value, 'unit' => 'bags', 'unit_weight_kg' => 50, 'reorder_level' => 2],
                    ['name' => 'Pre-Emergence Herbicide', 'category' => InventoryCategory::Agrochemical->value, 'unit' => 'liters', 'unit_weight_kg' => null, 'reorder_level' => 2],
                ],
            ],
            [
                'key' => 'crop_vegetable',
                'name' => 'Crop — Vegetables & Horticulture',
                'tagline' => 'Tomatoes, pepper, cucumber or okra with nursery, irrigation & spray schedules',
                'farmType' => FarmType::Crop->value,
                'enterpriseSubtype' => EnterpriseSubtype::Vegetable->value,
                'defaultUnitType' => ProductionUnitType::Plot->value,
                'defaultUnitName' => 'Vegetable Plot 1',
                'defaultSizeUnit' => 'plots',
                'defaultCommodityName' => 'Tomatoes',
                'defaultCommodityUnit' => 'crates',
                'defaultBreed' => 'Roma / Hybrid',
                'defaultDurationDays' => 95,
                'defaultInitialStock' => null,
                'defaultInitialWeightG' => null,
                'defaultTargetWeightG' => null,
                'defaultGrowthStage' => CropGrowthStage::Nursery->value,
                'defaultExpectedOutputUnit' => 'crates',
                'healthPreset' => 'crop_vegetable',
                'starterTasks' => [
                    ['title' => 'Prepare nursery beds under shade net', 'activity_type' => 'Nursery', 'day_offset' => 0],
                    ['title' => 'Transplant seedlings to main plot', 'activity_type' => 'Transplanting', 'day_offset' => 21],
                    ['title' => 'Staking & preventive fungicide spray', 'activity_type' => 'Crop Protection', 'day_offset' => 35],
                ],
                'starterInventory' => [
                    ['name' => 'Hybrid Vegetable Seed', 'category' => InventoryCategory::Seed->value, 'unit' => 'sachets', 'unit_weight_kg' => null, 'reorder_level' => 2],
                    ['name' => 'Fungicide Pack', 'category' => InventoryCategory::Agrochemical->value, 'unit' => 'liters', 'unit_weight_kg' => null, 'reorder_level' => 1],
                ],
            ],
            [
                'key' => 'aquaculture_catfish',
                'name' => 'Fish Farm — Catfish / Tilapia',
                'tagline' => 'Pond stocking, water quality alerts (pH, DO, ammonia), feeding, sampling & grading',
                'farmType' => FarmType::Aquaculture->value,
                'enterpriseSubtype' => EnterpriseSubtype::Catfish->value,
                'defaultUnitType' => ProductionUnitType::Pond->value,
                'defaultUnitName' => 'Pond 1',
                'defaultSizeUnit' => 'sqm',
                'defaultCommodityName' => 'African Catfish',
                'defaultCommodityUnit' => 'kg',
                'defaultBreed' => 'Clarias gariepinus (Juveniles)',
                'defaultDurationDays' => 150,
                'defaultInitialStock' => 1000,
                'defaultInitialWeightG' => 15,
                'defaultTargetWeightG' => 1000,
                'defaultGrowthStage' => null,
                'defaultExpectedOutputUnit' => 'kg',
                'healthPreset' => 'aquaculture_catfish',
                'starterTasks' => [
                    ['title' => 'Liming, water filling & pH verification', 'activity_type' => 'Pond Prep', 'day_offset' => 0],
                    ['title' => 'Week 3 grading & sorting shooters', 'activity_type' => 'Grading', 'day_offset' => 21],
                    ['title' => 'Bi-weekly biomass weight sampling', 'activity_type' => 'Sampling', 'day_offset' => 35],
                    ['title' => 'Switch pellet size & partial water flush', 'activity_type' => 'Water Exchange', 'day_offset' => 45],
                ],
                'starterInventory' => [
                    ['name' => '2mm Floating Fish Feed', 'category' => InventoryCategory::Feed->value, 'unit' => 'bags', 'unit_weight_kg' => 15, 'reorder_level' => 3],
                    ['name' => '4mm Floating Fish Feed', 'category' => InventoryCategory::Feed->value, 'unit' => 'bags', 'unit_weight_kg' => 15, 'reorder_level' => 5],
                    ['name' => '6mm Floating Fish Feed', 'category' => InventoryCategory::Feed->value, 'unit' => 'bags', 'unit_weight_kg' => 15, 'reorder_level' => 5],
                ],
            ],
            [
                'key' => 'mixed_custom',
                'name' => 'Mixed / Custom Farm',
                'tagline' => 'Combine crops, livestock, poultry or fish ponds under one farm workspace',
                'farmType' => FarmType::Mixed->value,
                'enterpriseSubtype' => EnterpriseSubtype::Custom->value,
                'defaultUnitType' => ProductionUnitType::Plot->value,
                'defaultUnitName' => 'Unit 1',
                'defaultSizeUnit' => 'acres',
                'defaultCommodityName' => 'Farm Produce',
                'defaultCommodityUnit' => 'kg',
                'defaultBreed' => null,
                'defaultDurationDays' => 120,
                'defaultInitialStock' => null,
                'defaultInitialWeightG' => null,
                'defaultTargetWeightG' => null,
                'defaultGrowthStage' => null,
                'defaultExpectedOutputUnit' => 'kg',
                'healthPreset' => null,
                'starterTasks' => [
                    ['title' => 'Initial site inspection & baseline setup', 'activity_type' => 'Inspection', 'day_offset' => 1],
                ],
                'starterInventory' => [],
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(string $key): ?array
    {
        foreach (self::all() as $template) {
            if ($template['key'] === $key) {
                return $template;
            }
        }

        return null;
    }
}

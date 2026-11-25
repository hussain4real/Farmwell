<?php

namespace App\Support;

use App\Enums\HealthEventType;

class HealthScheduleTemplates
{
    /**
     * @return array<string, array{
     *     key: string,
     *     label: string,
     *     description: string,
     *     events: list<array{
     *         day_offset: int,
     *         event_type: string,
     *         title: string,
     *         product_name: string,
     *         administration_route: string|null,
     *         withdrawal_days: int|null,
     *         notes: string
     *     }>
     * }>
     */
    public static function all(): array
    {
        return [
            'poultry_broiler' => [
                'key' => 'poultry_broiler',
                'label' => 'Broiler Vaccination & Prophylaxis',
                'description' => 'Standard 7-week broiler vaccination, vitamin, and coccidiosis schedule.',
                'events' => [
                    [
                        'day_offset' => 1,
                        'event_type' => HealthEventType::Medication->value,
                        'title' => 'Day 1 Anti-Stress & Multivitamins',
                        'product_name' => 'Glucose + Poultry Multivitamin',
                        'administration_route' => 'Drinking water',
                        'withdrawal_days' => 0,
                        'notes' => 'Help day-old chicks recover from transport stress.',
                    ],
                    [
                        'day_offset' => 7,
                        'event_type' => HealthEventType::Vaccination->value,
                        'title' => 'Newcastle (Lasota / HB1) First Dose',
                        'product_name' => 'Newcastle Disease Vaccine',
                        'administration_route' => 'Eye drop / Drinking water',
                        'withdrawal_days' => 0,
                        'notes' => 'Withdraw chlorine/sanitizers 24h before vaccination.',
                    ],
                    [
                        'day_offset' => 14,
                        'event_type' => HealthEventType::Vaccination->value,
                        'title' => 'Infectious Bursal Disease (Gumboro 1)',
                        'product_name' => 'IBD (Gumboro) Vaccine',
                        'administration_route' => 'Drinking water',
                        'withdrawal_days' => 0,
                        'notes' => 'Administer early morning in cool clean water.',
                    ],
                    [
                        'day_offset' => 21,
                        'event_type' => HealthEventType::Vaccination->value,
                        'title' => 'Newcastle (Lasota) Booster',
                        'product_name' => 'Lasota Vaccine',
                        'administration_route' => 'Drinking water',
                        'withdrawal_days' => 0,
                        'notes' => 'Follow with multivitamin booster for 2 days.',
                    ],
                    [
                        'day_offset' => 28,
                        'event_type' => HealthEventType::Vaccination->value,
                        'title' => 'Gumboro Booster & Coccidiostat Check',
                        'product_name' => 'IBD Booster',
                        'administration_route' => 'Drinking water',
                        'withdrawal_days' => 5,
                        'notes' => 'Inspect litter moisture and droppings.',
                    ],
                ],
            ],
            'poultry_layer' => [
                'key' => 'poultry_layer',
                'label' => 'Layer Flock Biosecurity & Deworming',
                'description' => 'Point-of-lay setup, deworming, and Newcastle booster schedule.',
                'events' => [
                    [
                        'day_offset' => 1,
                        'event_type' => HealthEventType::Biosecurity->value,
                        'title' => 'Pen Disinfection & Footbath Refresh',
                        'product_name' => 'Broad-spectrum Disinfectant',
                        'administration_route' => 'Spray / Footbath',
                        'withdrawal_days' => 0,
                        'notes' => 'Sanitize cages/litter and entry footbaths.',
                    ],
                    [
                        'day_offset' => 14,
                        'event_type' => HealthEventType::Deworming->value,
                        'title' => 'Flock Deworming',
                        'product_name' => 'Piperazine / Levamisole',
                        'administration_route' => 'Drinking water',
                        'withdrawal_days' => 7,
                        'notes' => 'Monitor egg production and feed intake.',
                    ],
                    [
                        'day_offset' => 60,
                        'event_type' => HealthEventType::Vaccination->value,
                        'title' => 'Periodic Newcastle Booster',
                        'product_name' => 'Lasota Vaccine',
                        'administration_route' => 'Drinking water',
                        'withdrawal_days' => 0,
                        'notes' => 'Repeat every 8-10 weeks during lay.',
                    ],
                ],
            ],
            'aquaculture_catfish' => [
                'key' => 'aquaculture_catfish',
                'label' => 'Fish Pond Conditioning & Biosecurity',
                'description' => 'Pond liming, stocking salt bath, and mid-cycle water prophylaxis.',
                'events' => [
                    [
                        'day_offset' => 0,
                        'event_type' => HealthEventType::WaterTreatment->value,
                        'title' => 'Pre-Stocking Pond Liming & Water Check',
                        'product_name' => 'Agricultural Lime',
                        'administration_route' => 'Pond water broadcast',
                        'withdrawal_days' => 0,
                        'notes' => 'Verify pH 6.5-8.5 before releasing fingerlings.',
                    ],
                    [
                        'day_offset' => 21,
                        'event_type' => HealthEventType::Biosecurity->value,
                        'title' => 'Post-Grading Salt Bath & Stress Prophylaxis',
                        'product_name' => 'Non-iodized Salt + Vitamin C',
                        'administration_route' => 'Bath / Feed top-coat',
                        'withdrawal_days' => 0,
                        'notes' => 'Prevent handling abrasions after Week 3 sorting.',
                    ],
                    [
                        'day_offset' => 60,
                        'event_type' => HealthEventType::WaterTreatment->value,
                        'title' => 'Mid-Cycle Water Quality & Parasite Check',
                        'product_name' => 'Pond Probiotic / Water Flush',
                        'administration_route' => 'Pond water',
                        'withdrawal_days' => 0,
                        'notes' => 'Inspect gills and fins during biomass sampling.',
                    ],
                ],
            ],
            'crop_arable' => [
                'key' => 'crop_arable',
                'label' => 'Arable Crop Protection & Scouting',
                'description' => 'Pre-emergence weed control and early vegetative pest scouting.',
                'events' => [
                    [
                        'day_offset' => 2,
                        'event_type' => HealthEventType::CropProtection->value,
                        'title' => 'Pre-Emergence Weed Control',
                        'product_name' => 'Pre-emergence Herbicide',
                        'administration_route' => 'Knapsack spray',
                        'withdrawal_days' => 30,
                        'notes' => 'Apply within 48 hours of planting on moist soil.',
                    ],
                    [
                        'day_offset' => 21,
                        'event_type' => HealthEventType::CropProtection->value,
                        'title' => 'Fall Armyworm & Leaf Scouting',
                        'product_name' => 'Neem / Approved Insecticide',
                        'administration_route' => 'Foliar spray (if threshold hit)',
                        'withdrawal_days' => 14,
                        'notes' => 'Spray only if pest scouting shows moderate/high pressure.',
                    ],
                ],
            ],
            'crop_vegetable' => [
                'key' => 'crop_vegetable',
                'label' => 'Vegetable Fungicide & Pest Schedule',
                'description' => 'Preventive blight/fungal management and pre-harvest withdrawal guard.',
                'events' => [
                    [
                        'day_offset' => 14,
                        'event_type' => HealthEventType::CropProtection->value,
                        'title' => 'Early Blight Preventive Spray',
                        'product_name' => 'Copper / Broad-Spectrum Fungicide',
                        'administration_route' => 'Foliar spray',
                        'withdrawal_days' => 7,
                        'notes' => 'Avoid spraying during midday heat.',
                    ],
                    [
                        'day_offset' => 35,
                        'event_type' => HealthEventType::CropProtection->value,
                        'title' => 'Flowering Pest & Fungal Check',
                        'product_name' => 'Insecticide + Foliar Micronutrient',
                        'administration_route' => 'Foliar spray',
                        'withdrawal_days' => 10,
                        'notes' => 'Respect withdrawal days before first picking.',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array{
     *     key: string,
     *     label: string,
     *     description: string,
     *     events: list<array{
     *         day_offset: int,
     *         event_type: string,
     *         title: string,
     *         product_name: string,
     *         administration_route: string|null,
     *         withdrawal_days: int|null,
     *         notes: string
     *     }>
     * }|null
     */
    public static function find(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    /**
     * @return list<array{key: string, label: string, description: string, eventCount: int}>
     */
    public static function options(): array
    {
        return array_values(array_map(
            fn (array $template): array => [
                'key' => $template['key'],
                'label' => $template['label'],
                'description' => $template['description'],
                'eventCount' => count($template['events']),
            ],
            self::all(),
        ));
    }
}

<?php

namespace App\Enums;

enum CropGrowthStage: string
{
    case LandPrep = 'land_prep';
    case Nursery = 'nursery';
    case Planting = 'planting';
    case Germination = 'germination';
    case Vegetative = 'vegetative';
    case Flowering = 'flowering';
    case Fruiting = 'fruiting';
    case Maturity = 'maturity';
    case Harvesting = 'harvesting';
    case PostHarvest = 'post_harvest';

    public function label(): string
    {
        return match ($this) {
            self::LandPrep => 'Land Preparation',
            self::Nursery => 'Nursery',
            self::Planting => 'Planting / Transplanting',
            self::Germination => 'Germination / Emergence',
            self::Vegetative => 'Vegetative Growth',
            self::Flowering => 'Flowering',
            self::Fruiting => 'Fruiting / Pod Fill',
            self::Maturity => 'Maturity / Ripening',
            self::Harvesting => 'Harvesting',
            self::PostHarvest => 'Post-Harvest / Storage',
        };
    }

    public function progressPercentage(): int
    {
        return match ($this) {
            self::LandPrep => 10,
            self::Nursery => 20,
            self::Planting => 30,
            self::Germination => 40,
            self::Vegetative => 55,
            self::Flowering => 70,
            self::Fruiting => 80,
            self::Maturity => 90,
            self::Harvesting => 95,
            self::PostHarvest => 100,
        };
    }

    /**
     * Get options for forms.
     *
     * @return array<int, array{value: string, label: string, progress: int}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $stage): array => [
                'value' => $stage->value,
                'label' => $stage->label(),
                'progress' => $stage->progressPercentage(),
            ],
            self::cases(),
        );
    }
}

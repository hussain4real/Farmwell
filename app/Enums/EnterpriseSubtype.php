<?php

namespace App\Enums;

enum EnterpriseSubtype: string
{
    case Broiler = 'broiler';
    case Layer = 'layer';
    case Cockerel = 'cockerel';
    case Turkey = 'turkey';
    case Catfish = 'catfish';
    case Tilapia = 'tilapia';
    case ArableCrop = 'arable_crop';
    case Vegetable = 'vegetable';
    case TreeCrop = 'tree_crop';
    case MixedLivestock = 'mixed_livestock';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Broiler => 'Broilers',
            self::Layer => 'Layers',
            self::Cockerel => 'Cockerels',
            self::Turkey => 'Turkeys',
            self::Catfish => 'African Catfish',
            self::Tilapia => 'Tilapia',
            self::ArableCrop => 'Arable Crop',
            self::Vegetable => 'Vegetables',
            self::TreeCrop => 'Tree / Plantation',
            self::MixedLivestock => 'Mixed Livestock',
            self::Custom => 'Custom',
        };
    }

    public function farmType(): FarmType
    {
        return match ($this) {
            self::Broiler, self::Layer, self::Cockerel, self::Turkey => FarmType::Poultry,
            self::Catfish, self::Tilapia => FarmType::Aquaculture,
            self::ArableCrop, self::Vegetable => FarmType::Crop,
            self::TreeCrop => FarmType::Plantation,
            self::MixedLivestock => FarmType::Livestock,
            self::Custom => FarmType::Mixed,
        };
    }

    /**
     * Get options for forms.
     *
     * @return array<int, array{value: string, label: string, farmType: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $subtype): array => [
                'value' => $subtype->value,
                'label' => $subtype->label(),
                'farmType' => $subtype->farmType()->value,
            ],
            self::cases(),
        );
    }
}

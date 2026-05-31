<?php

namespace App\Enums;

enum FarmType: string
{
    case Crop = 'crop';
    case Livestock = 'livestock';
    case Poultry = 'poultry';
    case Aquaculture = 'aquaculture';
    case Plantation = 'plantation';
    case Mixed = 'mixed';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Crop => 'Crop',
            self::Livestock => 'Livestock',
            self::Poultry => 'Poultry',
            self::Aquaculture => 'Aquaculture',
            self::Plantation => 'Plantation',
            self::Mixed => 'Mixed',
            self::Other => 'Other',
        };
    }

    /**
     * Get options for forms.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $type): array => ['value' => $type->value, 'label' => $type->label()],
            self::cases(),
        );
    }
}

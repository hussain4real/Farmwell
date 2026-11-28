<?php

namespace App\Enums;

enum ProductionLogDomain: string
{
    case Poultry = 'poultry';
    case Crop = 'crop';
    case Aquaculture = 'aquaculture';
    case General = 'general';

    public function label(): string
    {
        return match ($this) {
            self::Poultry => 'Poultry',
            self::Crop => 'Crop',
            self::Aquaculture => 'Fish / Aquaculture',
            self::General => 'General',
        };
    }

    public static function fromFarmType(FarmType $farmType): self
    {
        return match ($farmType) {
            FarmType::Poultry => self::Poultry,
            FarmType::Aquaculture => self::Aquaculture,
            FarmType::Crop, FarmType::Plantation => self::Crop,
            FarmType::Livestock, FarmType::Mixed, FarmType::Other => self::General,
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $domain): array => ['value' => $domain->value, 'label' => $domain->label()],
            self::cases(),
        );
    }
}

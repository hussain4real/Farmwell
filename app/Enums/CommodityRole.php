<?php

namespace App\Enums;

enum CommodityRole: string
{
    case Primary = 'primary';
    case Intercrop = 'intercrop';
    case Mixed = 'mixed';
    case Rotation = 'rotation';

    public function label(): string
    {
        return match ($this) {
            self::Primary => 'Primary',
            self::Intercrop => 'Intercrop',
            self::Mixed => 'Mixed',
            self::Rotation => 'Rotation',
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
            fn (self $role): array => ['value' => $role->value, 'label' => $role->label()],
            self::cases(),
        );
    }
}

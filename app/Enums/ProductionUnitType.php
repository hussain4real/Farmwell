<?php

namespace App\Enums;

enum ProductionUnitType: string
{
    case Field = 'field';
    case Plot = 'plot';
    case Greenhouse = 'greenhouse';
    case Pen = 'pen';
    case Pond = 'pond';
    case House = 'house';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Field => 'Field',
            self::Plot => 'Plot',
            self::Greenhouse => 'Greenhouse',
            self::Pen => 'Pen',
            self::Pond => 'Pond',
            self::House => 'House',
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

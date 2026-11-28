<?php

namespace App\Enums;

enum PestPressure: string
{
    case None = 'none';
    case Low = 'low';
    case Moderate = 'moderate';
    case High = 'high';

    public function label(): string
    {
        return match ($this) {
            self::None => 'None',
            self::Low => 'Low',
            self::Moderate => 'Moderate',
            self::High => 'High',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $pressure): array => ['value' => $pressure->value, 'label' => $pressure->label()],
            self::cases(),
        );
    }
}

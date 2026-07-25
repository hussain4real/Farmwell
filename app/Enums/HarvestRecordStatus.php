<?php

namespace App\Enums;

enum HarvestRecordStatus: string
{
    case Recorded = 'recorded';
    case Stored = 'stored';
    case PartiallySold = 'partially_sold';
    case Sold = 'sold';
    case Loss = 'loss';

    public function label(): string
    {
        return match ($this) {
            self::Recorded => 'Recorded',
            self::Stored => 'Stored',
            self::PartiallySold => 'Partially sold',
            self::Sold => 'Sold',
            self::Loss => 'Loss',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $status): array => ['value' => $status->value, 'label' => $status->label()],
            self::cases(),
        );
    }
}

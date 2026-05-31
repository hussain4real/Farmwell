<?php

namespace App\Enums;

enum ProductionCycleStatus: string
{
    case Draft = 'draft';
    case Planned = 'planned';
    case Active = 'active';
    case Delayed = 'delayed';
    case Changed = 'changed';
    case Completed = 'completed';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Planned => 'Planned',
            self::Active => 'Active',
            self::Delayed => 'Delayed',
            self::Changed => 'Changed',
            self::Completed => 'Completed',
            self::Closed => 'Closed',
            self::Cancelled => 'Cancelled',
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
            fn (self $status): array => ['value' => $status->value, 'label' => $status->label()],
            self::cases(),
        );
    }
}

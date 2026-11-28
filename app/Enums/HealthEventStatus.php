<?php

namespace App\Enums;

enum HealthEventStatus: string
{
    case Scheduled = 'scheduled';
    case Completed = 'completed';
    case Skipped = 'skipped';
    case Overdue = 'overdue';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Scheduled',
            self::Completed => 'Completed',
            self::Skipped => 'Skipped',
            self::Overdue => 'Overdue',
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

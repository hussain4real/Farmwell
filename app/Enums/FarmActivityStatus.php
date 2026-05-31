<?php

namespace App\Enums;

enum FarmActivityStatus: string
{
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Delayed = 'delayed';
    case Blocked = 'blocked';
    case Skipped = 'skipped';
    case Changed = 'changed';

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Planned',
            self::InProgress => 'In progress',
            self::Completed => 'Completed',
            self::Delayed => 'Delayed',
            self::Blocked => 'Blocked',
            self::Skipped => 'Skipped',
            self::Changed => 'Changed',
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

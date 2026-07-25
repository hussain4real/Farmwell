<?php

namespace App\Enums;

enum HarvestStage: string
{
    case Single = 'single';
    case Recurring = 'recurring';
    case Cutting = 'cutting';
    case Gathering = 'gathering';
    case Threshing = 'threshing';
    case Bagging = 'bagging';
    case Storage = 'storage';
    case Final = 'final';

    public function label(): string
    {
        return match ($this) {
            self::Single => 'Single',
            self::Recurring => 'Recurring',
            self::Cutting => 'Cutting',
            self::Gathering => 'Gathering',
            self::Threshing => 'Threshing',
            self::Bagging => 'Bagging',
            self::Storage => 'Storage',
            self::Final => 'Final',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $stage): array => ['value' => $stage->value, 'label' => $stage->label()],
            self::cases(),
        );
    }
}

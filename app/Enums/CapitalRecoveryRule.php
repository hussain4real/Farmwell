<?php

namespace App\Enums;

enum CapitalRecoveryRule: string
{
    case CapitalFirst = 'capital_first';

    public function label(): string
    {
        return match ($this) {
            self::CapitalFirst => 'Capital first',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $rule): array => ['value' => $rule->value, 'label' => $rule->label()],
            self::cases(),
        );
    }
}

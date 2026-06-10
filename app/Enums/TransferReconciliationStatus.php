<?php

namespace App\Enums;

enum TransferReconciliationStatus: string
{
    case Matched = 'matched';
    case PartiallyMatched = 'partially_matched';
    case Unmatched = 'unmatched';

    public function label(): string
    {
        return match ($this) {
            self::Matched => 'Matched',
            self::PartiallyMatched => 'Partially matched',
            self::Unmatched => 'Unmatched',
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

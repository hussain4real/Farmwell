<?php

namespace App\Enums;

enum ExpenseStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Reconciled = 'reconciled';
    case Disputed = 'disputed';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Reconciled => 'Reconciled',
            self::Disputed => 'Disputed',
            self::Voided => 'Voided',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function spendableValues(): array
    {
        return [
            self::Draft->value,
            self::Submitted->value,
            self::Approved->value,
            self::Reconciled->value,
        ];
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

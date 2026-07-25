<?php

namespace App\Enums;

enum SalePaymentStatus: string
{
    case Pending = 'pending';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Reconciled = 'reconciled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::PartiallyPaid => 'Partially paid',
            self::Paid => 'Paid',
            self::Reconciled => 'Reconciled',
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

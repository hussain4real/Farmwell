<?php

namespace App\Enums;

enum InventoryMovementType: string
{
    case Purchase = 'purchase';
    case Usage = 'usage';
    case Adjustment = 'adjustment';
    case Spoilage = 'spoilage';
    case Transfer = 'transfer';

    public function label(): string
    {
        return match ($this) {
            self::Purchase => 'Restock / Purchase',
            self::Usage => 'Daily Usage',
            self::Adjustment => 'Stock Adjustment',
            self::Spoilage => 'Spoilage / Loss',
            self::Transfer => 'Transfer Out',
        };
    }

    public function isDeduction(): bool
    {
        return match ($this) {
            self::Usage, self::Spoilage, self::Transfer => true,
            self::Purchase, self::Adjustment => false,
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $type): array => ['value' => $type->value, 'label' => $type->label()],
            self::cases(),
        );
    }
}

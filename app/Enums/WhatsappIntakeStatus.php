<?php

namespace App\Enums;

enum WhatsappIntakeStatus: string
{
    case Pending = 'pending';
    case Converted = 'converted';
    case Rejected = 'rejected';
    case NeedsClarification = 'needs_clarification';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending review',
            self::Converted => 'Converted',
            self::Rejected => 'Rejected',
            self::NeedsClarification => 'Needs clarification',
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

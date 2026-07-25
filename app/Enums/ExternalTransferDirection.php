<?php

namespace App\Enums;

enum ExternalTransferDirection: string
{
    case Incoming = 'incoming';
    case Outgoing = 'outgoing';

    public function label(): string
    {
        return match ($this) {
            self::Incoming => 'Incoming',
            self::Outgoing => 'Outgoing',
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
            fn (self $direction): array => ['value' => $direction->value, 'label' => $direction->label()],
            self::cases(),
        );
    }
}

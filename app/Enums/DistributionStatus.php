<?php

namespace App\Enums;

enum DistributionStatus: string
{
    case PendingAcknowledgement = 'pending_acknowledgement';
    case Acknowledged = 'acknowledged';
    case Paid = 'paid';
    case LossRecorded = 'loss_recorded';

    public function label(): string
    {
        return match ($this) {
            self::PendingAcknowledgement => 'Pending acknowledgement',
            self::Acknowledged => 'Acknowledged',
            self::Paid => 'Paid',
            self::LossRecorded => 'Loss recorded',
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

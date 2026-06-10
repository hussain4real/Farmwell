<?php

namespace App\Enums;

enum FundingPhaseStatus: string
{
    case Draft = 'draft';
    case Requested = 'requested';
    case Approved = 'approved';
    case ReleasedExternally = 'released_externally';
    case PartiallySpent = 'partially_spent';
    case Complete = 'complete';
    case Reconciled = 'reconciled';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Requested => 'Requested',
            self::Approved => 'Approved',
            self::ReleasedExternally => 'Released externally',
            self::PartiallySpent => 'Partially spent',
            self::Complete => 'Complete',
            self::Reconciled => 'Reconciled',
            self::Closed => 'Closed',
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

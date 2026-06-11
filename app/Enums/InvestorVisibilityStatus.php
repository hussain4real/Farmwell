<?php

namespace App\Enums;

enum InvestorVisibilityStatus: string
{
    case Private = 'private';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case ClarificationRequested = 'clarification_requested';

    public function label(): string
    {
        return match ($this) {
            self::Private => 'Private',
            self::PendingApproval => 'Pending approval',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::ClarificationRequested => 'Clarification requested',
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

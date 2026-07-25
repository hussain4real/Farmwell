<?php

namespace App\Enums;

enum ApprovalRequestType: string
{
    case FundingRelease = 'funding_release';
    case Expense = 'expense';
    case BudgetOverrun = 'budget_overrun';
    case PlanChange = 'plan_change';
    case DistributionAcknowledgement = 'distribution_acknowledgement';

    public function label(): string
    {
        return match ($this) {
            self::FundingRelease => 'Funding release',
            self::Expense => 'Expense',
            self::BudgetOverrun => 'Budget overrun',
            self::PlanChange => 'Plan change',
            self::DistributionAcknowledgement => 'Distribution acknowledgement',
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

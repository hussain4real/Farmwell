<?php

namespace App\Enums;

enum ApprovalTriggerType: string
{
    case Threshold = 'threshold';
    case Category = 'category';
    case FundingRelease = 'funding_release';
    case BudgetOverrun = 'budget_overrun';
    case PlanChange = 'plan_change';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Threshold => 'Threshold',
            self::Category => 'Category',
            self::FundingRelease => 'Funding release',
            self::BudgetOverrun => 'Budget overrun',
            self::PlanChange => 'Plan change',
            self::Manual => 'Manual',
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

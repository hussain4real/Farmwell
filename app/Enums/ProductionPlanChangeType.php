<?php

namespace App\Enums;

enum ProductionPlanChangeType: string
{
    case Schedule = 'schedule';
    case CommodityMix = 'commodity_mix';
    case ProductionMethod = 'production_method';
    case UnitAllocation = 'unit_allocation';
    case ExpectedOutput = 'expected_output';
    case BudgetImpact = 'budget_impact';
    case RiskDecision = 'risk_decision';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Schedule => 'Schedule',
            self::CommodityMix => 'Commodity mix',
            self::ProductionMethod => 'Production method',
            self::UnitAllocation => 'Unit allocation',
            self::ExpectedOutput => 'Expected output',
            self::BudgetImpact => 'Budget impact',
            self::RiskDecision => 'Risk or decision',
            self::Other => 'Other',
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
            fn (self $type): array => ['value' => $type->value, 'label' => $type->label()],
            self::cases(),
        );
    }
}

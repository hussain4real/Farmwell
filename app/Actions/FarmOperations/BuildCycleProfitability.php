<?php

namespace App\Actions\FarmOperations;

use App\Actions\Finance\ResolveTeamFinanceCurrency;
use App\Enums\ExpenseStatus;
use App\Enums\InventoryMovementType;
use App\Models\ProductionCycle;
use App\Support\Money;

class BuildCycleProfitability
{
    public function __construct(private ResolveTeamFinanceCurrency $resolveTeamFinanceCurrency)
    {
        //
    }

    /**
     * Compute per-cycle cost, revenue, gross margin, and unit economics.
     *
     * @return array<string, mixed>
     */
    public function handle(ProductionCycle $cycle): array
    {
        $currency = $this->resolveTeamFinanceCurrency->handle($cycle->team);

        $directExpensesMinor = (int) $cycle->expenses()
            ->where('currency', $currency)
            ->whereIn('status', ExpenseStatus::spendableValues())
            ->sum('amount_minor');

        $unexpensedInventoryUsageMinor = (int) $cycle->inventoryMovements()
            ->where('currency', $currency)
            ->where('movement_type', InventoryMovementType::Usage->value)
            ->whereNull('expense_id')
            ->sum('total_cost_minor');

        $healthCostsMinor = (int) $cycle->healthEvents()
            ->where('currency', $currency)
            ->sum('cost_minor');

        $harvestLabourMinor = (int) $cycle->harvestRecords()
            ->where('currency', $currency)
            ->sum('labour_cost_minor');

        $totalCostMinor = $directExpensesMinor + $unexpensedInventoryUsageMinor + $healthCostsMinor + $harvestLabourMinor;

        $netRevenueMinor = (int) $cycle->saleRecords()
            ->where('currency', $currency)
            ->sum('net_amount_minor');

        $grossProfitMinor = $netRevenueMinor - $totalCostMinor;
        $marginPercent = $netRevenueMinor > 0
            ? round(($grossProfitMinor / $netRevenueMinor) * 100, 1)
            : null;

        $totalHarvestedQuantity = (float) $cycle->harvestRecords()->sum('quantity');
        $costPerLiveUnitMinor = ($cycle->current_stock_count !== null && $cycle->current_stock_count > 0)
            ? (int) round($totalCostMinor / $cycle->current_stock_count)
            : null;

        $costPerHarvestedUnitMinor = $totalHarvestedQuantity > 0
            ? (int) round($totalCostMinor / $totalHarvestedQuantity)
            : null;

        return [
            'currency' => $currency,
            'directExpensesMinor' => $directExpensesMinor,
            'directExpenses' => Money::toDecimal($directExpensesMinor),
            'inventoryUsageMinor' => $unexpensedInventoryUsageMinor,
            'inventoryUsage' => Money::toDecimal($unexpensedInventoryUsageMinor),
            'healthCostsMinor' => $healthCostsMinor,
            'healthCosts' => Money::toDecimal($healthCostsMinor),
            'harvestLabourMinor' => $harvestLabourMinor,
            'harvestLabour' => Money::toDecimal($harvestLabourMinor),
            'totalCostMinor' => $totalCostMinor,
            'totalCost' => Money::toDecimal($totalCostMinor),
            'netRevenueMinor' => $netRevenueMinor,
            'netRevenue' => Money::toDecimal($netRevenueMinor),
            'grossProfitMinor' => $grossProfitMinor,
            'grossProfit' => Money::toDecimal($grossProfitMinor),
            'marginPercent' => $marginPercent,
            'costPerLiveUnitMinor' => $costPerLiveUnitMinor,
            'costPerLiveUnit' => $costPerLiveUnitMinor !== null ? Money::toDecimal($costPerLiveUnitMinor) : null,
            'costPerHarvestedUnitMinor' => $costPerHarvestedUnitMinor,
            'costPerHarvestedUnit' => $costPerHarvestedUnitMinor !== null ? Money::toDecimal($costPerHarvestedUnitMinor) : null,
        ];
    }
}

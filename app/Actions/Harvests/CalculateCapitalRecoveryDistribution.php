<?php

namespace App\Actions\Harvests;

use App\Models\InvestorAgreement;
use App\Models\SaleRecord;

class CalculateCapitalRecoveryDistribution
{
    /**
     * @return array{
     *     sale_gross_amount_minor: int,
     *     sale_net_amount_minor: int,
     *     previous_capital_recovered_minor: int,
     *     capital_recovered_minor: int,
     *     unrecovered_capital_minor: int,
     *     gross_profit_minor: int,
     *     net_profit_minor: int,
     *     investor_profit_share_percentage: int,
     *     farm_profit_share_percentage: int,
     *     investor_share_minor: int,
     *     farm_share_minor: int,
     *     currency: string
     * }
     */
    public function handle(InvestorAgreement $agreement, SaleRecord $saleRecord): array
    {
        $previousRecoveredMinor = (int) $agreement->distributionRecords()
            ->where('sale_record_id', '!=', $saleRecord->id)
            ->sum('capital_recovered_minor');

        $remainingCapitalMinor = max(0, $agreement->amount_funded_minor - $previousRecoveredMinor);
        $capitalRecoveredMinor = min($remainingCapitalMinor, $saleRecord->net_amount_minor);
        $unrecoveredCapitalMinor = max(0, $remainingCapitalMinor - $capitalRecoveredMinor);
        $grossProfitMinor = max(0, $saleRecord->gross_amount_minor - $capitalRecoveredMinor);
        $netProfitMinor = max(0, $saleRecord->net_amount_minor - $capitalRecoveredMinor);
        $investorShareMinor = intdiv($netProfitMinor * $agreement->investor_profit_share_percentage, 100);
        $farmShareMinor = $netProfitMinor - $investorShareMinor;

        return [
            'sale_gross_amount_minor' => $saleRecord->gross_amount_minor,
            'sale_net_amount_minor' => $saleRecord->net_amount_minor,
            'previous_capital_recovered_minor' => $previousRecoveredMinor,
            'capital_recovered_minor' => $capitalRecoveredMinor,
            'unrecovered_capital_minor' => $unrecoveredCapitalMinor,
            'gross_profit_minor' => $grossProfitMinor,
            'net_profit_minor' => $netProfitMinor,
            'investor_profit_share_percentage' => $agreement->investor_profit_share_percentage,
            'farm_profit_share_percentage' => $agreement->farm_profit_share_percentage,
            'investor_share_minor' => $investorShareMinor,
            'farm_share_minor' => $farmShareMinor,
            'currency' => $saleRecord->currency,
        ];
    }
}

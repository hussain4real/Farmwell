<?php

namespace App\Actions\Investors;

use App\Enums\InvestorVisibilityStatus;
use App\Models\Expense;
use App\Models\InvestorAgreement;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class BuildInvestorStatement
{
    public function render(InvestorAgreement $agreement, string $start, string $end): string
    {
        $scope = [
            'team_id' => $agreement->team_id,
            'farm_id' => $agreement->farm_id,
            'investor_visibility_status' => InvestorVisibilityStatus::Approved->value,
        ];
        if ($agreement->production_cycle_id !== null) {
            $scope['production_cycle_id'] = $agreement->production_cycle_id;
        }
        $until = CarbonImmutable::parse($end)->addDay()->toDateString();
        $financial = [...$scope, 'currency' => $agreement->currency];
        $releases = $agreement->fundingPhases()->where($financial);
        $expenses = $agreement->expenses()->where($financial);
        $opening = (int) (clone $releases)->where('released_on', '<', $start)->sum('externally_released_amount_minor')
            - (int) (clone $expenses)->where('incurred_on', '<', $start)->sum('amount_minor');
        $released = (int) $releases->where('released_on', '>=', $start)->where('released_on', '<', $until)->sum('externally_released_amount_minor');
        $periodExpenses = $expenses->where('incurred_on', '>=', $start)->where('incurred_on', '<', $until)
            ->withCount(['media as receipts_count' => fn (Builder $query) => $query->where('collection_name', Expense::ReceiptsCollection)])
            ->get(['id', 'amount_minor']);
        $spent = (int) $periodExpenses->sum('amount_minor');
        $netSales = (int) $agreement->saleRecords()->where($financial)->where('sold_on', '>=', $start)->where('sold_on', '<', $until)->sum('net_amount_minor');
        $distributions = $agreement->distributionRecords()->where($financial)
            ->where('calculated_at', '>=', $start)->where('calculated_at', '<', $until)
            ->get(['capital_recovered_minor', 'investor_share_minor']);
        $amounts = [
            'Opening funding balance' => $opening,
            'Funds released' => $released,
            'Approved spend' => $spent,
            'Closing funding balance' => $opening + $released - $spent,
            'Net sales' => $netSales,
            'Capital recovered' => (int) $distributions->sum('capital_recovered_minor'),
            'Investor profit share' => (int) $distributions->sum('investor_share_minor'),
        ];
        $harvests = $agreement->harvestRecords()->where($scope)->where('harvested_on', '>=', $start)->where('harvested_on', '<', $until)
            ->with('commodity:id,name')->orderBy('harvested_on')->orderBy('id')
            ->get(['id', 'commodity_id', 'harvested_on', 'quantity', 'quantity_unit']);

        return view('reports.investor-statement', [
            'agreement' => $agreement, 'start' => $start, 'end' => $end,
            'generatedAt' => now()->toIso8601String(),
            'amounts' => array_map(Money::toDecimal(...), $amounts),
            'receipts' => $periodExpenses->sum('receipts_count'), 'harvests' => $harvests,
        ])->render();
    }
}

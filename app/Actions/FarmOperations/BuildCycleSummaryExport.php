<?php

namespace App\Actions\FarmOperations;

use App\Models\Farm;
use App\Models\ProductionCycle;
use App\Models\Team;
use App\Models\User;

class BuildCycleSummaryExport
{
    public function __construct(
        private CalculateCycleTelemetry $calculateCycleTelemetry,
        private BuildCycleProfitability $buildCycleProfitability,
        private BuildCycleCalculators $buildCycleCalculators,
        private BuildFarmDetailPageData $buildFarmDetailPageData,
    ) {
        //
    }

    /**
     * Compile printable PDF & shareable summary data for a production cycle.
     *
     * @return array<string, mixed>
     */
    public function handle(Team $team, Farm $farm, ProductionCycle $cycle, User $actor): array
    {
        $cycle->load(['productionUnits', 'commodities', 'healthEvents']);

        $telemetry = $this->calculateCycleTelemetry->handle($cycle);
        $profitability = $actor->can('viewFinance', $team)
            ? $this->buildCycleProfitability->handle($cycle)
            : null;
        $calculators = $this->buildCycleCalculators->handle($cycle, $telemetry, $profitability);

        $recentLogs = $cycle->productionLogs()
            ->with(['productionCycle', 'productionUnit', 'recordedBy', 'inventoryItem', 'media'])
            ->latest('log_date')
            ->latest()
            ->limit(15)
            ->get()
            ->map(fn ($log): array => $this->buildFarmDetailPageData->logPayload($log, $team))
            ->values()
            ->all();

        $activeWithdrawals = $cycle->healthEvents
            ->filter(fn ($event): bool => $event->isUnderWithdrawal())
            ->map(fn ($event): array => [
                'title' => $event->title,
                'endsOn' => $event->withdrawalEndsOn()?->toDateString(),
            ])
            ->values()
            ->all();

        $summary = [
            'teamName' => $team->name,
            'farmName' => $farm->name,
            'cycleName' => $cycle->name,
            'farmTypeLabel' => $cycle->farm_type->label(),
            'domain' => (string) $telemetry['domain'],
            'statusLabel' => $cycle->status->label(),
            'plannedStartOn' => $cycle->planned_start_on->toDateString(),
            'plannedEndOn' => $cycle->planned_end_on?->toDateString(),
            'breedOrVariety' => $cycle->breed_or_variety,
            'commodities' => $cycle->commodities->pluck('name')->implode(', '),
            'units' => $cycle->productionUnits->pluck('name')->implode(', '),
            'telemetry' => $telemetry,
            'profitability' => $profitability,
            'calculators' => $calculators,
            'recentLogs' => $recentLogs,
            'activeWithdrawals' => $activeWithdrawals,
            'generatedAt' => now()->format('Y-m-d H:i'),
        ];

        return [
            ...$summary,
            'whatsappText' => $this->toWhatsappText($summary),
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    public function toWhatsappText(array $summary): string
    {
        $telemetry = $summary['telemetry'];
        $domain = (string) $summary['domain'];

        $lines = [
            sprintf('*%s — %s*', $summary['farmName'], $summary['cycleName']),
            sprintf('%s · %s · Day %d', $summary['farmTypeLabel'], $summary['statusLabel'], (int) $telemetry['ageDays']),
        ];

        if ($domain === 'poultry') {
            $lines[] = sprintf(
                'Flock: %s birds | Mortality: %d (%s%%) | Feed: %s kg | FCR: %s',
                $telemetry['stock']['currentCount'] ?? 'N/A',
                $telemetry['stock']['totalMortality'],
                $telemetry['stock']['mortalityRatePercent'] ?? '0',
                $telemetry['feeding']['totalFeedKg'],
                $telemetry['feeding']['fcr'] ?? '—',
            );
            if ((int) $telemetry['poultry']['totalEggs'] > 0) {
                $lines[] = sprintf('Eggs: %d (%s crates)', (int) $telemetry['poultry']['totalEggs'], $telemetry['poultry']['totalCrates']);
            }
        } elseif ($domain === 'aquaculture') {
            $lines[] = sprintf(
                'Stock: %s fish | Biomass: %s kg | Feed: %s kg | FCR: %s | Water: %s',
                $telemetry['stock']['currentCount'] ?? 'N/A',
                $telemetry['feeding']['estimatedBiomassKg'] ?? '—',
                $telemetry['feeding']['totalFeedKg'],
                $telemetry['feeding']['fcr'] ?? '—',
                strtoupper((string) $telemetry['aquaculture']['waterStatus']),
            );
        } elseif ($domain === 'crop') {
            $lines[] = sprintf(
                'Stage: %s | Rainfall: %s mm | Pest: %s | Harvested: %s',
                $telemetry['crop']['currentGrowthStageLabel'] ?? 'In progress',
                $telemetry['crop']['totalRainfallMm'],
                $telemetry['crop']['latestPestPressureLabel'] ?? 'None',
                $telemetry['crop']['totalHarvestedQuantity'],
            );
        }

        if ($summary['profitability'] !== null) {
            $profit = $summary['profitability'];
            $lines[] = sprintf(
                'P&L (%s): Cost %s | Sales %s | Gross Profit %s',
                $profit['currency'],
                $profit['totalCost'],
                $profit['netRevenue'],
                $profit['grossProfit'],
            );
        }

        return implode("\n", $lines);
    }
}

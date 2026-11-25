<?php

namespace App\Actions\FarmOperations;

use App\Enums\CropGrowthStage;
use App\Enums\PestPressure;
use App\Enums\ProductionLogDomain;
use App\Models\ProductionCycle;
use App\Models\ProductionLog;
use Illuminate\Support\Collection;

class CalculateCycleTelemetry
{
    /**
     * Compute domain-specific operational KPIs and 14-day chart series for a production cycle.
     *
     * @return array<string, mixed>
     */
    public function handle(ProductionCycle $cycle): array
    {
        $domain = ProductionLogDomain::fromFarmType($cycle->farm_type);
        $logs = $cycle->productionLogs()
            ->orderBy('log_date')
            ->orderBy('id')
            ->get();

        $startDate = $cycle->actual_start_on ?? $cycle->planned_start_on;
        $endDate = $cycle->actual_end_on ?? now();
        $ageDays = max(0, (int) $startDate->diffInDays($endDate, false));

        $progressPercent = $this->calculateProgressPercent($cycle, $domain, $ageDays);

        $initialCount = $cycle->initial_stock_count;
        $totalMortality = (int) $logs->sum('mortality_count');
        $totalCulled = (int) $logs->sum('cull_count');
        $totalGradedOut = (int) $logs->sum('graded_out_count');
        $currentCount = $cycle->current_stock_count ?? ($initialCount !== null ? max(0, $initialCount - $totalMortality - $totalCulled - $totalGradedOut) : null);

        $mortalityRatePercent = $initialCount !== null && $initialCount > 0
            ? round((($totalMortality + $totalCulled) / $initialCount) * 100, 2)
            : null;
        $survivalRatePercent = $initialCount !== null && $initialCount > 0 && $currentCount !== null
            ? round(($currentCount / $initialCount) * 100, 2)
            : null;

        $totalFeedKg = round((float) $logs->sum(fn (ProductionLog $log): float => (float) ($log->feed_used_kg ?? 0)), 2);
        $feedingDays = max(1, $logs->filter(fn (ProductionLog $log): bool => (float) ($log->feed_used_kg ?? 0) > 0)->count());
        $averageDailyFeedKg = $totalFeedKg > 0 ? round($totalFeedKg / $feedingDays, 2) : 0.0;

        $latestWeightLog = $logs->reverse()->first(fn (ProductionLog $log): bool => $log->average_weight_g !== null);
        $latestAverageWeightG = $latestWeightLog?->average_weight_g !== null
            ? (float) $latestWeightLog->average_weight_g
            : ($cycle->initial_average_weight_g !== null ? (float) $cycle->initial_average_weight_g : null);

        $estimatedBiomassKg = ($currentCount !== null && $latestAverageWeightG !== null)
            ? round(($currentCount * $latestAverageWeightG) / 1000, 2)
            : null;

        $fcr = null;
        if ($estimatedBiomassKg !== null && $totalFeedKg > 0 && $initialCount !== null) {
            $initialBiomassKg = ($initialCount * (float) ($cycle->initial_average_weight_g ?? 0)) / 1000;
            $weightGainKg = $estimatedBiomassKg - $initialBiomassKg;

            if ($weightGainKg > 0) {
                $fcr = round($totalFeedKg / $weightGainKg, 2);
            }
        }

        $totalEggs = (int) $logs->sum(fn (ProductionLog $log): int => $log->eggs_collected ?? 0);
        $totalDamagedEggs = (int) $logs->sum(fn (ProductionLog $log): int => $log->eggs_damaged ?? 0);
        $recentEggLogs = $logs->filter(fn (ProductionLog $log): bool => $log->log_date->gte(now()->subDays(6)) && $log->eggs_collected !== null);
        $recentLayRatePercent = null;
        if ($recentEggLogs->isNotEmpty() && $currentCount !== null && $currentCount > 0) {
            $avgDailyEggs = $recentEggLogs->avg('eggs_collected');
            $recentLayRatePercent = round(((float) $avgDailyEggs / $currentCount) * 100, 1);
        }

        $waterTelemetry = $this->aquacultureWaterTelemetry($logs);

        $totalHarvested = round((float) $cycle->harvestRecords()->sum('quantity'), 2);
        $totalUnitArea = (float) $cycle->productionUnits()->sum('size');
        $yieldPerArea = ($totalHarvested > 0 && $totalUnitArea > 0)
            ? round($totalHarvested / $totalUnitArea, 2)
            : null;

        $latestPestLog = $logs->reverse()->first(fn (ProductionLog $log): bool => $log->pest_pressure !== null);
        $latestDiseaseLog = $logs->reverse()->first(fn (ProductionLog $log): bool => ! empty($log->disease_observed));

        return [
            'domain' => $domain->value,
            'domainLabel' => $domain->label(),
            'ageDays' => $ageDays,
            'progressPercent' => $progressPercent,
            'logCount' => $logs->count(),
            'stock' => [
                'initialCount' => $initialCount,
                'currentCount' => $currentCount,
                'totalMortality' => $totalMortality,
                'totalCulled' => $totalCulled,
                'totalGradedOut' => $totalGradedOut,
                'mortalityRatePercent' => $mortalityRatePercent,
                'survivalRatePercent' => $survivalRatePercent,
            ],
            'feeding' => [
                'totalFeedKg' => $totalFeedKg,
                'averageDailyFeedKg' => $averageDailyFeedKg,
                'latestAverageWeightG' => $latestAverageWeightG,
                'estimatedBiomassKg' => $estimatedBiomassKg,
                'fcr' => $fcr,
            ],
            'poultry' => [
                'totalEggs' => $totalEggs,
                'totalCrates' => round($totalEggs / 30, 1),
                'totalDamagedEggs' => $totalDamagedEggs,
                'damagedRatePercent' => $totalEggs > 0 ? round(($totalDamagedEggs / $totalEggs) * 100, 1) : 0.0,
                'recentLayRatePercent' => $recentLayRatePercent,
            ],
            'aquaculture' => $waterTelemetry,
            'crop' => [
                'currentGrowthStage' => $cycle->current_growth_stage?->value,
                'currentGrowthStageLabel' => $cycle->current_growth_stage?->label(),
                'stageProgressPercent' => $cycle->current_growth_stage?->progressPercentage() ?? $progressPercent,
                'totalRainfallMm' => round((float) $logs->sum(fn (ProductionLog $log): float => (float) ($log->rainfall_mm ?? 0)), 1),
                'irrigationCount' => $logs->where('irrigation_applied', true)->count(),
                'latestPestPressure' => $latestPestLog?->pest_pressure?->value,
                'latestPestPressureLabel' => $latestPestLog?->pest_pressure?->label(),
                'hasHighPestAlert' => $latestPestLog?->pest_pressure === PestPressure::High,
                'activeDiseaseAlert' => $latestDiseaseLog?->disease_observed,
                'totalHarvestedQuantity' => $totalHarvested,
                'yieldPerUnitArea' => $yieldPerArea,
            ],
            'series' => $this->buildSeries($logs->take(-14)),
        ];
    }

    private function calculateProgressPercent(ProductionCycle $cycle, ProductionLogDomain $domain, int $ageDays): int
    {
        if ($domain === ProductionLogDomain::Crop && $cycle->current_growth_stage instanceof CropGrowthStage) {
            return $cycle->current_growth_stage->progressPercentage();
        }

        if ($cycle->planned_end_on !== null) {
            $plannedDays = max(1, (int) $cycle->planned_start_on->diffInDays($cycle->planned_end_on, false));

            return (int) min(100, max(0, round(($ageDays / $plannedDays) * 100)));
        }

        return 0;
    }

    /**
     * @param  Collection<int, ProductionLog>  $logs
     * @return array<string, mixed>
     */
    private function aquacultureWaterTelemetry(Collection $logs): array
    {
        $latestWaterLog = $logs->reverse()->first(
            fn (ProductionLog $log): bool => $log->water_ph !== null
                || $log->water_temp_c !== null
                || $log->dissolved_oxygen_mg_l !== null
                || $log->ammonia_mg_l !== null
        );

        $ph = $latestWaterLog?->water_ph !== null ? (float) $latestWaterLog->water_ph : null;
        $temp = $latestWaterLog?->water_temp_c !== null ? (float) $latestWaterLog->water_temp_c : null;
        $do = $latestWaterLog?->dissolved_oxygen_mg_l !== null ? (float) $latestWaterLog->dissolved_oxygen_mg_l : null;
        $ammonia = $latestWaterLog?->ammonia_mg_l !== null ? (float) $latestWaterLog->ammonia_mg_l : null;

        $alerts = [];
        $status = $latestWaterLog === null ? 'unknown' : 'optimal';

        if ($ph !== null && ($ph < 6.5 || $ph > 8.5)) {
            $alerts[] = sprintf('pH %.2f is outside safe range (6.5-8.5)', $ph);
            $status = 'warning';
        }

        if ($do !== null && $do < 4.0) {
            $alerts[] = sprintf('Dissolved oxygen %.2f mg/L is low (target >= 4.0)', $do);
            $status = $do < 3.0 ? 'critical' : 'warning';
        }

        if ($ammonia !== null && $ammonia > 0.5) {
            $alerts[] = sprintf('Ammonia %.3f mg/L is elevated (target <= 0.5)', $ammonia);
            $status = $ammonia > 1.0 ? 'critical' : 'warning';
        }

        if ($temp !== null && ($temp < 24.0 || $temp > 33.0)) {
            $alerts[] = sprintf('Water temp %.1f°C is outside ideal 24-33°C range', $temp);
            $status = $status === 'critical' ? 'critical' : 'warning';
        }

        return [
            'latestPh' => $ph,
            'latestTempC' => $temp,
            'latestDissolvedOxygen' => $do,
            'latestAmmonia' => $ammonia,
            'waterExchangeCount' => $logs->where('water_exchanged', true)->count(),
            'waterStatus' => $status,
            'waterAlerts' => $alerts,
        ];
    }

    /**
     * @param  Collection<int, ProductionLog>  $recentLogs
     * @return array<string, list<float|int|string|null>>
     */
    private function buildSeries(Collection $recentLogs): array
    {
        return [
            'dates' => $recentLogs->map(fn (ProductionLog $log): string => $log->log_date->format('M j'))->values()->all(),
            'feedKg' => $recentLogs->map(fn (ProductionLog $log): float => (float) ($log->feed_used_kg ?? 0))->values()->all(),
            'mortality' => $recentLogs->map(fn (ProductionLog $log): int => $log->mortality_count + $log->cull_count)->values()->all(),
            'eggs' => $recentLogs->map(fn (ProductionLog $log): int => $log->eggs_collected ?? 0)->values()->all(),
            'dissolvedOxygen' => $recentLogs->map(fn (ProductionLog $log): ?float => $log->dissolved_oxygen_mg_l !== null ? (float) $log->dissolved_oxygen_mg_l : null)->values()->all(),
            'rainfallMm' => $recentLogs->map(fn (ProductionLog $log): float => (float) ($log->rainfall_mm ?? 0))->values()->all(),
            'inputQuantity' => $recentLogs->map(fn (ProductionLog $log): float => (float) ($log->input_applied_quantity ?? 0))->values()->all(),
        ];
    }
}

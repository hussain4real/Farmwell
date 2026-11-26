<?php

namespace App\Actions\Investors;

use App\Enums\CropGrowthStage;
use App\Enums\PestPressure;
use App\Enums\ProductionLogDomain;
use App\Models\ProductionCycle;
use App\Models\ProductionLog;
use Illuminate\Support\Collection;

class BuildInvestorCycleSnapshot
{
    /**
     * Build an investor-safe read-only operational telemetry snapshot for a production cycle.
     * Excludes free-text internal notes, disease free text, and unapproved harvest totals.
     *
     * @return array<string, mixed>
     */
    public function handle(ProductionCycle $cycle): array
    {
        $domain = ProductionLogDomain::fromFarmType($cycle->farm_type);

        $logs = $cycle->productionLogs()
            ->select([
                'id',
                'log_date',
                'mortality_count',
                'cull_count',
                'graded_out_count',
                'feed_used_kg',
                'average_weight_g',
                'eggs_collected',
                'water_ph',
                'water_temp_c',
                'dissolved_oxygen_mg_l',
                'ammonia_mg_l',
                'water_exchanged',
                'rainfall_mm',
                'irrigation_applied',
                'pest_pressure',
            ])
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
        $currentCount = $cycle->current_stock_count
            ?? ($initialCount !== null ? max(0, $initialCount - $totalMortality - $totalCulled - $totalGradedOut) : null);

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
        $recentEggLogs = $logs->filter(fn (ProductionLog $log): bool => $log->log_date->gte(now()->subDays(6)) && $log->eggs_collected !== null);
        $recentLayRatePercent = null;
        if ($recentEggLogs->isNotEmpty() && $currentCount !== null && $currentCount > 0) {
            $recentLayRatePercent = round(((float) $recentEggLogs->avg('eggs_collected') / $currentCount) * 100, 1);
        }

        $latestPestLog = $logs->reverse()->first(fn (ProductionLog $log): bool => $log->pest_pressure !== null);
        $latestPestPressure = $latestPestLog?->pest_pressure instanceof PestPressure ? $latestPestLog->pest_pressure : null;

        $lastLog = $logs->last();

        return [
            'cycleId' => $cycle->id,
            'cycleName' => $cycle->name,
            'season' => $cycle->season,
            'domain' => $domain->value,
            'domainLabel' => $domain->label(),
            'farmType' => $cycle->farm_type->value,
            'farmTypeLabel' => $cycle->farm_type->label(),
            'status' => $cycle->status->value,
            'statusLabel' => $cycle->status->label(),
            'plannedStartOn' => $cycle->planned_start_on->toDateString(),
            'plannedEndOn' => $cycle->planned_end_on?->toDateString(),
            'ageDays' => $ageDays,
            'progressPercent' => $progressPercent,
            'logCount' => $logs->count(),
            'lastLoggedOn' => $lastLog?->log_date->toDateString(),
            'stock' => [
                'initialCount' => $initialCount,
                'currentCount' => $currentCount,
                'survivalRatePercent' => $survivalRatePercent,
                'mortalityRatePercent' => $mortalityRatePercent,
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
                'recentLayRatePercent' => $recentLayRatePercent,
            ],
            'aquaculture' => $this->waterTelemetry($logs),
            'crop' => [
                'currentGrowthStage' => $cycle->current_growth_stage?->value,
                'currentGrowthStageLabel' => $cycle->current_growth_stage?->label(),
                'stageProgressPercent' => $cycle->current_growth_stage?->progressPercentage() ?? $progressPercent,
                'totalRainfallMm' => round((float) $logs->sum(fn (ProductionLog $log): float => (float) ($log->rainfall_mm ?? 0)), 1),
                'irrigationCount' => $logs->where('irrigation_applied', true)->count(),
                'latestPestPressure' => $latestPestPressure?->value,
                'latestPestPressureLabel' => $latestPestPressure?->label(),
            ],
            'series' => $this->buildSeries($logs->take(-14)),
        ];
    }

    public function calculateProgressPercent(ProductionCycle $cycle, ?ProductionLogDomain $domain = null, ?int $ageDays = null): int
    {
        $domain ??= ProductionLogDomain::fromFarmType($cycle->farm_type);

        if ($domain === ProductionLogDomain::Crop && $cycle->current_growth_stage instanceof CropGrowthStage) {
            return $cycle->current_growth_stage->progressPercentage();
        }

        if ($cycle->planned_end_on !== null) {
            if ($ageDays === null) {
                $startDate = $cycle->actual_start_on ?? $cycle->planned_start_on;
                $endDate = $cycle->actual_end_on ?? now();
                $ageDays = max(0, (int) $startDate->diffInDays($endDate, false));
            }

            $plannedDays = max(1, (int) $cycle->planned_start_on->diffInDays($cycle->planned_end_on, false));

            return (int) min(100, max(0, round(($ageDays / $plannedDays) * 100)));
        }

        return 0;
    }

    /**
     * @param  Collection<int, ProductionLog>  $logs
     * @return array<string, mixed>
     */
    private function waterTelemetry(Collection $logs): array
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

        $status = $latestWaterLog === null ? 'unknown' : 'optimal';

        if (($ph !== null && ($ph < 6.5 || $ph > 8.5))
            || ($temp !== null && ($temp < 24.0 || $temp > 33.0))) {
            $status = 'warning';
        }

        if ($do !== null && $do < 4.0) {
            $status = $do < 3.0 ? 'critical' : 'warning';
        }

        if ($ammonia !== null && $ammonia > 0.5) {
            $status = $ammonia > 1.0 ? 'critical' : ($status === 'critical' ? 'critical' : 'warning');
        }

        return [
            'waterStatus' => $status,
            'waterExchangeCount' => $logs->where('water_exchanged', true)->count(),
            'latestPh' => $ph,
            'latestTempC' => $temp,
            'latestDissolvedOxygen' => $do,
            'latestAmmonia' => $ammonia,
        ];
    }

    /**
     * @param  Collection<int, ProductionLog>  $recentLogs
     * @return array<string, list<float|int|string>>
     */
    private function buildSeries(Collection $recentLogs): array
    {
        return [
            'dates' => $recentLogs->map(fn (ProductionLog $log): string => $log->log_date->format('M j'))->values()->all(),
            'feedKg' => $recentLogs->map(fn (ProductionLog $log): float => (float) ($log->feed_used_kg ?? 0))->values()->all(),
            'eggs' => $recentLogs->map(fn (ProductionLog $log): int => $log->eggs_collected ?? 0)->values()->all(),
            'rainfallMm' => $recentLogs->map(fn (ProductionLog $log): float => (float) ($log->rainfall_mm ?? 0))->values()->all(),
        ];
    }
}

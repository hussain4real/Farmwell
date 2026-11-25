<?php

namespace App\Actions\FarmOperations;

use App\Actions\Finance\ResolveTeamFinanceCurrency;
use App\Enums\EnterpriseSubtype;
use App\Enums\ProductionLogDomain;
use App\Models\ProductionCycle;
use App\Support\Money;

class BuildCycleCalculators
{
    public function __construct(
        private BuildCycleProfitability $buildCycleProfitability,
        private CalculateCycleTelemetry $calculateCycleTelemetry,
        private ResolveTeamFinanceCurrency $resolveTeamFinanceCurrency,
    ) {
        //
    }

    /**
     * Build enterprise-specific advisory calculators for a production cycle.
     *
     * @param  array<string, mixed>  $telemetry
     * @param  array<string, mixed>|null  $profitability
     * @return array{
     *     aquaculture: array<string, mixed>|null,
     *     poultry: array<string, mixed>|null,
     *     crop: array<string, mixed>|null,
     * }
     */
    public function handle(ProductionCycle $cycle, array $telemetry, ?array $profitability = null): array
    {
        $domain = ProductionLogDomain::fromFarmType($cycle->farm_type);

        return [
            'aquaculture' => $domain === ProductionLogDomain::Aquaculture ? $this->aquaculture($cycle, $telemetry) : null,
            'poultry' => $domain === ProductionLogDomain::Poultry ? $this->poultry($cycle, $telemetry) : null,
            'crop' => $domain === ProductionLogDomain::Crop ? $this->crop($cycle, $telemetry, $profitability) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $telemetry
     * @return array<string, mixed>
     */
    private function aquaculture(ProductionCycle $cycle, array $telemetry): array
    {
        $currentStock = (int) ($telemetry['stock']['currentCount'] ?? $cycle->current_stock_count ?? 0);
        $avgWeightG = (float) ($telemetry['feeding']['latestAverageWeightG'] ?? $cycle->initial_average_weight_g ?? 15.0);
        $estimatedBiomassKg = round(($currentStock * $avgWeightG) / 1000, 2);

        [$bodyWeightPercent, $pelletSizeMm, $mealsPerDay] = match (true) {
            $avgWeightG < 10.0 => [8.0, '1.5-2mm', 4],
            $avgWeightG < 50.0 => [5.0, '2-3mm', 3],
            $avgWeightG < 200.0 => [3.5, '3-4mm', 2],
            $avgWeightG < 500.0 => [2.5, '4-6mm', 2],
            default => [1.8, '6-8mm', 2],
        };

        $recommendedDailyFeedKg = round($estimatedBiomassKg * ($bodyWeightPercent / 100), 2);
        $actualDailyFeedKg = (float) ($telemetry['feeding']['averageDailyFeedKg'] ?? 0.0);

        $feedingStatus = 'no_data';
        if ($actualDailyFeedKg > 0 && $recommendedDailyFeedKg > 0) {
            if ($actualDailyFeedKg < $recommendedDailyFeedKg * 0.8) {
                $feedingStatus = 'underfeeding';
            } elseif ($actualDailyFeedKg > $recommendedDailyFeedKg * 1.2) {
                $feedingStatus = 'overfeeding';
            } else {
                $feedingStatus = 'on_track';
            }
        }

        return [
            'currentStock' => $currentStock,
            'avgWeightG' => round($avgWeightG, 1),
            'estimatedBiomassKg' => $estimatedBiomassKg,
            'bodyWeightPercent' => $bodyWeightPercent,
            'pelletSizeMm' => $pelletSizeMm,
            'mealsPerDay' => $mealsPerDay,
            'recommendedDailyFeedKg' => $recommendedDailyFeedKg,
            'actualDailyFeedKg' => $actualDailyFeedKg,
            'feedingStatus' => $feedingStatus,
        ];
    }

    /**
     * @param  array<string, mixed>  $telemetry
     * @return array<string, mixed>
     */
    private function poultry(ProductionCycle $cycle, array $telemetry): array
    {
        $ageDays = (int) ($telemetry['ageDays'] ?? 0);
        $currentStock = (int) ($telemetry['stock']['currentCount'] ?? $cycle->current_stock_count ?? 0);
        $isLayer = $cycle->enterprise_subtype === EnterpriseSubtype::Layer;

        if ($isLayer) {
            [$currentPhase, $nextPhase, $nextTransitionDay, $gramsPerBird] = match (true) {
                $ageDays <= 56 => ['Chick Mash', 'Grower Mash', 57, 35],
                $ageDays <= 126 => ['Grower Mash', 'Layers Mash', 127, 75],
                default => ['Layers Mash', null, null, 115],
            };
        } else {
            [$currentPhase, $nextPhase, $nextTransitionDay, $gramsPerBird] = match (true) {
                $ageDays <= 14 => ['Broiler Starter Mash', 'Broiler Grower Mash', 15, 45],
                $ageDays <= 28 => ['Broiler Grower Mash', 'Broiler Finisher Pellets', 29, 105],
                default => ['Broiler Finisher Pellets', null, null, 155],
            };
        }

        return [
            'isLayer' => $isLayer,
            'ageDays' => $ageDays,
            'currentStock' => $currentStock,
            'currentPhase' => $currentPhase,
            'nextPhase' => $nextPhase,
            'daysToNextPhase' => $nextTransitionDay !== null ? max(0, $nextTransitionDay - $ageDays) : null,
            'gramsPerBird' => $gramsPerBird,
            'recommendedDailyFeedKg' => round(($currentStock * $gramsPerBird) / 1000, 2),
            'actualDailyFeedKg' => (float) ($telemetry['feeding']['averageDailyFeedKg'] ?? 0.0),
        ];
    }

    /**
     * @param  array<string, mixed>  $telemetry
     * @param  array<string, mixed>|null  $profitability
     * @return array<string, mixed>
     */
    private function crop(ProductionCycle $cycle, array $telemetry, ?array $profitability): array
    {
        $cycle->loadMissing('productionUnits');

        $totalArea = round((float) $cycle->productionUnits->sum(fn ($unit): float => (float) ($unit->size ?? 0)), 2);
        $areaUnit = $cycle->productionUnits->first(fn ($unit): bool => ! empty($unit->size_unit))?->size_unit ?? 'units';
        $currency = $this->resolveTeamFinanceCurrency->handle($cycle->team);

        $costPerArea = null;
        $revenuePerArea = null;

        if ($totalArea > 0 && $profitability !== null) {
            $costPerArea = Money::toDecimal((int) round(((int) $profitability['totalCostMinor']) / $totalArea));
            $revenuePerArea = Money::toDecimal((int) round(((int) $profitability['netRevenueMinor']) / $totalArea));
        }

        $siblings = $cycle->farm->productionCycles()
            ->with('productionUnits')
            ->where('farm_type', $cycle->farm_type->value)
            ->latest('planned_start_on')
            ->limit(5)
            ->get();

        $comparisons = [];

        foreach ($siblings as $other) {
            $otherArea = round((float) $other->productionUnits->sum(fn ($unit): float => (float) ($unit->size ?? 0)), 2);
            $otherTelemetry = $other->id === $cycle->id
                ? $telemetry
                : $this->calculateCycleTelemetry->handle($other);

            $otherProfit = null;
            if ($profitability !== null) {
                $otherProfit = $other->id === $cycle->id
                    ? $profitability
                    : $this->buildCycleProfitability->handle($other);
            }

            $comparisons[] = [
                'id' => $other->id,
                'name' => $other->name,
                'season' => $other->season,
                'statusLabel' => $other->status->label(),
                'isCurrent' => $other->id === $cycle->id,
                'totalArea' => $otherArea,
                'areaUnit' => $other->productionUnits->first(fn ($unit): bool => ! empty($unit->size_unit))?->size_unit ?? $areaUnit,
                'harvestedQuantity' => (float) $otherTelemetry['crop']['totalHarvestedQuantity'],
                'yieldPerArea' => $otherTelemetry['crop']['yieldPerUnitArea'],
                'costPerArea' => ($otherArea > 0 && $otherProfit !== null)
                    ? Money::toDecimal((int) round(((int) $otherProfit['totalCostMinor']) / $otherArea))
                    : null,
                'marginPercent' => $otherProfit['marginPercent'] ?? null,
            ];
        }

        return [
            'totalArea' => $totalArea,
            'areaUnit' => $areaUnit,
            'currency' => $currency,
            'yieldPerArea' => $telemetry['crop']['yieldPerUnitArea'],
            'costPerArea' => $costPerArea,
            'revenuePerArea' => $revenuePerArea,
            'seasonComparisons' => $comparisons,
        ];
    }
}

<?php

namespace App\Actions\Finance;

use App\Enums\ExpenseStatus;
use App\Models\Farm;
use App\Models\FundingPhase;
use App\Models\ProductionCycle;
use App\Models\Team;
use App\Support\Money;

class CalculateCarryForwardBalance
{
    /**
     * @return array<string, mixed>
     */
    public function handle(Team $team, ?Farm $farm = null, ?ProductionCycle $cycle = null): array
    {
        $carryForwardMinor = 0;
        $releasedMinor = 0;
        $spentMinor = 0;

        $phases = FundingPhase::query()
            ->where('team_id', $team->id)
            ->withSum(['expenses as spent_minor' => fn ($query) => $query->whereIn('status', ExpenseStatus::spendableValues())], 'amount_minor')
            ->when($farm, fn ($query) => $query->where('farm_id', $farm->id))
            ->when($cycle, fn ($query) => $query->where('production_cycle_id', $cycle->id))
            ->orderBy('expected_on')
            ->orderBy('id')
            ->get()
            ->map(function (FundingPhase $phase) use (&$carryForwardMinor, &$releasedMinor, &$spentMinor): array {
                $phaseReleasedMinor = $phase->externally_released_amount_minor;
                $phaseSpentMinor = (int) ($phase->getAttribute('spent_minor') ?? 0);
                $openingMinor = $carryForwardMinor;

                $releasedMinor += $phaseReleasedMinor;
                $spentMinor += $phaseSpentMinor;
                $carryForwardMinor += $phaseReleasedMinor - $phaseSpentMinor;

                return [
                    'phaseId' => $phase->id,
                    'phaseName' => $phase->name,
                    'openingMinor' => $openingMinor,
                    'releasedMinor' => $phaseReleasedMinor,
                    'spentMinor' => $phaseSpentMinor,
                    'carryForwardMinor' => $carryForwardMinor,
                    'opening' => Money::toDecimal($openingMinor),
                    'released' => Money::toDecimal($phaseReleasedMinor),
                    'spent' => Money::toDecimal($phaseSpentMinor),
                    'carryForward' => Money::toDecimal($carryForwardMinor),
                ];
            })
            ->values()
            ->toArray();

        return [
            'releasedMinor' => $releasedMinor,
            'spentMinor' => $spentMinor,
            'carryForwardMinor' => $carryForwardMinor,
            'released' => Money::toDecimal($releasedMinor),
            'spent' => Money::toDecimal($spentMinor),
            'carryForward' => Money::toDecimal($carryForwardMinor),
            'phases' => $phases,
        ];
    }
}

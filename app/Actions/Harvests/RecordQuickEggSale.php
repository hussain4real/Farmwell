<?php

namespace App\Actions\Harvests;

use App\Enums\FarmType;
use App\Enums\HarvestRecordStatus;
use App\Enums\HarvestStage;
use App\Enums\InvestorVisibilityStatus;
use App\Enums\SalePaymentStatus;
use App\Models\Commodity;
use App\Models\Farm;
use App\Models\ProductionCycle;
use App\Models\SaleRecord;
use App\Models\Team;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class RecordQuickEggSale
{
    public function __construct(
        private RecordHarvest $recordHarvest,
        private RecordSale $recordSale,
    ) {
        //
    }

    /**
     * Record a quick egg crate harvest + immediate sale for a poultry cycle.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Team $team, Farm $farm, ProductionCycle $cycle, User $actor, array $attributes): SaleRecord
    {
        return DB::transaction(function () use ($team, $farm, $cycle, $actor, $attributes): SaleRecord {
            $commodity = Commodity::query()
                ->where('team_id', $team->id)
                ->where('name', 'Eggs')
                ->first()
                ?? Commodity::create([
                    'team_id' => $team->id,
                    'name' => 'Eggs',
                    'farm_type' => FarmType::Poultry,
                    'measurement_unit' => 'crates',
                    'is_active' => true,
                ]);

            $crates = number_format((float) $attributes['crates_sold'], 2, '.', '');
            $cratesInHundredths = (int) round(((float) $attributes['crates_sold']) * 100);
            $unitPriceMinor = Money::toMinorUnit((string) $attributes['price_per_crate']);
            $grossAmountMinor = intdiv(($cratesInHundredths * $unitPriceMinor) + 50, 100);

            $harvest = $this->recordHarvest->handle($team, $actor, [
                'farm_id' => $farm->id,
                'production_cycle_id' => $cycle->id,
                'commodity_id' => $commodity->id,
                'harvested_on' => $attributes['sold_on'],
                'stage' => HarvestStage::Final,
                'sequence_number' => 1,
                'quantity' => $crates,
                'quantity_unit' => 'crates',
                'quality_notes' => 'Quick crate sale',
                'labour_cost_minor' => 0,
                'status' => HarvestRecordStatus::Sold,
                'investor_visibility_status' => InvestorVisibilityStatus::Private,
                'notes' => $attributes['notes'] ?? null,
            ]);

            return $this->recordSale->handle($team, $actor, [
                'harvest_record_id' => $harvest->id,
                'sold_on' => $attributes['sold_on'],
                'buyer_name' => $attributes['buyer_name'],
                'quantity' => $crates,
                'quantity_unit' => 'crates',
                'unit_price_minor' => $unitPriceMinor,
                'gross_amount_minor' => $grossAmountMinor,
                'deduction_amount_minor' => 0,
                'net_amount_minor' => $grossAmountMinor,
                'payment_status' => SalePaymentStatus::from((string) $attributes['payment_status']),
                'investor_visibility_status' => InvestorVisibilityStatus::Private,
                'notes' => $attributes['notes'] ?? null,
            ]);
        });
    }
}

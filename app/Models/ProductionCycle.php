<?php

namespace App\Models;

use App\Enums\CropGrowthStage;
use App\Enums\EnterpriseSubtype;
use App\Enums\FarmType;
use App\Enums\ProductionCycleStatus;
use Database\Factories\ProductionCycleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int $farm_id
 * @property string $name
 * @property string|null $season
 * @property FarmType $farm_type
 * @property EnterpriseSubtype|null $enterprise_subtype
 * @property string|null $breed_or_variety
 * @property string|null $production_method
 * @property Carbon $planned_start_on
 * @property Carbon|null $planned_end_on
 * @property Carbon|null $actual_start_on
 * @property Carbon|null $actual_end_on
 * @property string|null $expected_output_quantity
 * @property string|null $expected_output_unit
 * @property int|null $initial_stock_count
 * @property int|null $current_stock_count
 * @property string|null $initial_average_weight_g
 * @property string|null $target_weight_g
 * @property CropGrowthStage|null $current_growth_stage
 * @property ProductionCycleStatus $status
 * @property int $plan_version
 * @property string|null $plan_summary
 * @property-read Team $team
 * @property-read Farm $farm
 */
#[Fillable([
    'team_id',
    'farm_id',
    'name',
    'season',
    'farm_type',
    'enterprise_subtype',
    'breed_or_variety',
    'production_method',
    'planned_start_on',
    'planned_end_on',
    'actual_start_on',
    'actual_end_on',
    'expected_output_quantity',
    'expected_output_unit',
    'initial_stock_count',
    'current_stock_count',
    'initial_average_weight_g',
    'target_weight_g',
    'current_growth_stage',
    'status',
    'plan_version',
    'plan_summary',
])]
class ProductionCycle extends Model
{
    /** @use HasFactory<ProductionCycleFactory> */
    use HasFactory;

    /**
     * Get the team that owns the production cycle.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the farm that owns the production cycle.
     *
     * @return BelongsTo<Farm, $this>
     */
    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    /**
     * Get production units assigned to the cycle.
     *
     * @return BelongsToMany<ProductionUnit, $this>
     */
    public function productionUnits(): BelongsToMany
    {
        return $this->belongsToMany(ProductionUnit::class)
            ->withTimestamps();
    }

    /**
     * Get commodities planned for the cycle.
     *
     * @return BelongsToMany<Commodity, $this>
     */
    public function commodities(): BelongsToMany
    {
        return $this->belongsToMany(Commodity::class)
            ->withPivot(['role', 'expected_output_quantity', 'expected_output_unit', 'notes'])
            ->withTimestamps();
    }

    /**
     * Get plan changes recorded against the cycle.
     *
     * @return HasMany<ProductionPlanChange, $this>
     */
    public function planChanges(): HasMany
    {
        return $this->hasMany(ProductionPlanChange::class);
    }

    /**
     * Get activities recorded for the cycle.
     *
     * @return HasMany<FarmActivity, $this>
     */
    public function farmActivities(): HasMany
    {
        return $this->hasMany(FarmActivity::class);
    }

    /**
     * Get tasks scheduled for the cycle.
     *
     * @return HasMany<FarmTask, $this>
     */
    public function farmTasks(): HasMany
    {
        return $this->hasMany(FarmTask::class);
    }

    /**
     * Get budgets scoped to this cycle.
     *
     * @return HasMany<Budget, $this>
     */
    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    /**
     * Get funding phases scoped to this cycle.
     *
     * @return HasMany<FundingPhase, $this>
     */
    public function fundingPhases(): HasMany
    {
        return $this->hasMany(FundingPhase::class);
    }

    /**
     * Get expenses scoped to this cycle.
     *
     * @return HasMany<Expense, $this>
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * Get external transfers scoped to this cycle.
     *
     * @return HasMany<ExternalTransfer, $this>
     */
    public function externalTransfers(): HasMany
    {
        return $this->hasMany(ExternalTransfer::class);
    }

    /**
     * Get harvest records scoped to this cycle.
     *
     * @return HasMany<HarvestRecord, $this>
     */
    public function harvestRecords(): HasMany
    {
        return $this->hasMany(HarvestRecord::class);
    }

    /**
     * Get sale records scoped to this cycle.
     *
     * @return HasMany<SaleRecord, $this>
     */
    public function saleRecords(): HasMany
    {
        return $this->hasMany(SaleRecord::class);
    }

    /**
     * Get distribution records scoped to this cycle.
     *
     * @return HasMany<DistributionRecord, $this>
     */
    public function distributionRecords(): HasMany
    {
        return $this->hasMany(DistributionRecord::class);
    }

    /**
     * Get investor agreements scoped to this cycle.
     *
     * @return HasMany<InvestorAgreement, $this>
     */
    public function investorAgreements(): HasMany
    {
        return $this->hasMany(InvestorAgreement::class);
    }

    /**
     * Get daily production logs recorded for this cycle.
     *
     * @return HasMany<ProductionLog, $this>
     */
    public function productionLogs(): HasMany
    {
        return $this->hasMany(ProductionLog::class);
    }

    /**
     * Get health events scheduled or recorded for this cycle.
     *
     * @return HasMany<HealthEvent, $this>
     */
    public function healthEvents(): HasMany
    {
        return $this->hasMany(HealthEvent::class);
    }

    /**
     * Get inventory movements linked to this cycle.
     *
     * @return HasMany<InventoryMovement, $this>
     */
    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'farm_type' => FarmType::class,
            'enterprise_subtype' => EnterpriseSubtype::class,
            'current_growth_stage' => CropGrowthStage::class,
            'planned_start_on' => 'date',
            'planned_end_on' => 'date',
            'actual_start_on' => 'date',
            'actual_end_on' => 'date',
            'expected_output_quantity' => 'decimal:2',
            'initial_stock_count' => 'integer',
            'current_stock_count' => 'integer',
            'initial_average_weight_g' => 'decimal:2',
            'target_weight_g' => 'decimal:2',
            'status' => ProductionCycleStatus::class,
            'plan_version' => 'integer',
        ];
    }
}

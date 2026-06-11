<?php

namespace App\Models;

use App\Enums\FarmType;
use Database\Factories\FarmFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property FarmType $farm_type
 * @property string|null $location
 * @property string $status
 * @property string|null $registration_number
 * @property string|null $contact_name
 * @property string|null $contact_phone
 * @property string|null $description
 * @property-read Team $team
 */
#[Fillable([
    'team_id',
    'name',
    'farm_type',
    'location',
    'status',
    'registration_number',
    'contact_name',
    'contact_phone',
    'description',
])]
class Farm extends Model
{
    /** @use HasFactory<FarmFactory> */
    use HasFactory;

    /**
     * Get the team that owns the farm.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get production units on this farm.
     *
     * @return HasMany<ProductionUnit, $this>
     */
    public function productionUnits(): HasMany
    {
        return $this->hasMany(ProductionUnit::class);
    }

    /**
     * Get production cycles on this farm.
     *
     * @return HasMany<ProductionCycle, $this>
     */
    public function productionCycles(): HasMany
    {
        return $this->hasMany(ProductionCycle::class);
    }

    /**
     * Get activities recorded for this farm.
     *
     * @return HasMany<FarmActivity, $this>
     */
    public function farmActivities(): HasMany
    {
        return $this->hasMany(FarmActivity::class);
    }

    /**
     * Get tasks scheduled for this farm.
     *
     * @return HasMany<FarmTask, $this>
     */
    public function farmTasks(): HasMany
    {
        return $this->hasMany(FarmTask::class);
    }

    /**
     * Get budgets scoped to this farm.
     *
     * @return HasMany<Budget, $this>
     */
    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    /**
     * Get funding phases scoped to this farm.
     *
     * @return HasMany<FundingPhase, $this>
     */
    public function fundingPhases(): HasMany
    {
        return $this->hasMany(FundingPhase::class);
    }

    /**
     * Get expenses scoped to this farm.
     *
     * @return HasMany<Expense, $this>
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * Get external transfers scoped to this farm.
     *
     * @return HasMany<ExternalTransfer, $this>
     */
    public function externalTransfers(): HasMany
    {
        return $this->hasMany(ExternalTransfer::class);
    }

    /**
     * Get investor agreements scoped to this farm.
     *
     * @return HasMany<InvestorAgreement, $this>
     */
    public function investorAgreements(): HasMany
    {
        return $this->hasMany(InvestorAgreement::class);
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
        ];
    }
}

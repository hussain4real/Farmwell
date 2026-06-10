<?php

namespace App\Models;

use App\Enums\FundingPhaseStatus;
use Database\Factories\FundingPhaseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int $farm_id
 * @property int|null $production_cycle_id
 * @property int|null $budget_id
 * @property int|null $created_by_id
 * @property string $name
 * @property string|null $milestone
 * @property FundingPhaseStatus $status
 * @property string $currency
 * @property int $planned_amount_minor
 * @property int $requested_amount_minor
 * @property int $approved_amount_minor
 * @property int $externally_released_amount_minor
 * @property Carbon|null $expected_on
 * @property Carbon|null $released_on
 * @property string|null $notes
 * @property-read Team $team
 * @property-read Farm $farm
 * @property-read Budget|null $budget
 */
class FundingPhase extends Model
{
    /** @use HasFactory<FundingPhaseFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'team_id',
        'farm_id',
        'production_cycle_id',
        'budget_id',
        'created_by_id',
        'name',
        'milestone',
        'status',
        'currency',
        'planned_amount_minor',
        'requested_amount_minor',
        'approved_amount_minor',
        'externally_released_amount_minor',
        'expected_on',
        'released_on',
        'notes',
    ];

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<Farm, $this>
     */
    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    /**
     * @return BelongsTo<ProductionCycle, $this>
     */
    public function productionCycle(): BelongsTo
    {
        return $this->belongsTo(ProductionCycle::class);
    }

    /**
     * @return BelongsTo<Budget, $this>
     */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * @return HasMany<Expense, $this>
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * @return HasMany<ExternalTransfer, $this>
     */
    public function externalTransfers(): HasMany
    {
        return $this->hasMany(ExternalTransfer::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => FundingPhaseStatus::class,
            'planned_amount_minor' => 'integer',
            'requested_amount_minor' => 'integer',
            'approved_amount_minor' => 'integer',
            'externally_released_amount_minor' => 'integer',
            'expected_on' => 'date',
            'released_on' => 'date',
        ];
    }
}

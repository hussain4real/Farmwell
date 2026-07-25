<?php

namespace App\Models;

use App\Enums\BudgetStatus;
use Database\Factories\BudgetFactory;
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
 * @property int|null $created_by_id
 * @property int|null $approved_by_id
 * @property string $name
 * @property BudgetStatus $status
 * @property string $currency
 * @property Carbon|null $period_start_on
 * @property Carbon|null $period_end_on
 * @property string|null $notes
 * @property Carbon|null $approved_at
 * @property-read Team $team
 * @property-read Farm $farm
 * @property-read ProductionCycle|null $productionCycle
 */
class Budget extends Model
{
    /** @use HasFactory<BudgetFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'team_id',
        'farm_id',
        'production_cycle_id',
        'created_by_id',
        'approved_by_id',
        'name',
        'status',
        'currency',
        'period_start_on',
        'period_end_on',
        'notes',
        'approved_at',
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
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    /**
     * @return HasMany<BudgetLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(BudgetLine::class);
    }

    /**
     * @return HasMany<FundingPhase, $this>
     */
    public function fundingPhases(): HasMany
    {
        return $this->hasMany(FundingPhase::class);
    }

    /**
     * @return HasMany<Expense, $this>
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BudgetStatus::class,
            'period_start_on' => 'date',
            'period_end_on' => 'date',
            'approved_at' => 'datetime',
        ];
    }
}

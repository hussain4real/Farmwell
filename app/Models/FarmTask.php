<?php

namespace App\Models;

use App\Enums\FarmTaskStatus;
use Database\Factories\FarmTaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int $farm_id
 * @property int|null $production_unit_id
 * @property int|null $production_cycle_id
 * @property int|null $assigned_to_id
 * @property int|null $created_by_id
 * @property int|null $completed_by_id
 * @property string $title
 * @property string|null $activity_type
 * @property string|null $description
 * @property Carbon|null $planned_for
 * @property Carbon $due_on
 * @property Carbon|null $reminder_at
 * @property FarmTaskStatus $status
 * @property string|null $status_reason
 * @property Carbon|null $completed_at
 * @property bool $investor_visible
 * @property-read Team $team
 * @property-read Farm $farm
 * @property-read ProductionUnit|null $productionUnit
 * @property-read ProductionCycle|null $productionCycle
 * @property-read User|null $assignedTo
 * @property-read User|null $createdBy
 * @property-read User|null $completedBy
 */
#[Fillable([
    'team_id',
    'farm_id',
    'production_unit_id',
    'production_cycle_id',
    'assigned_to_id',
    'created_by_id',
    'completed_by_id',
    'title',
    'activity_type',
    'description',
    'planned_for',
    'due_on',
    'reminder_at',
    'status',
    'status_reason',
    'completed_at',
    'investor_visible',
])]
class FarmTask extends Model
{
    /** @use HasFactory<FarmTaskFactory> */
    use HasFactory;

    /**
     * Get the team that owns the task.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the farm for this task.
     *
     * @return BelongsTo<Farm, $this>
     */
    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    /**
     * Get the production unit for this task.
     *
     * @return BelongsTo<ProductionUnit, $this>
     */
    public function productionUnit(): BelongsTo
    {
        return $this->belongsTo(ProductionUnit::class);
    }

    /**
     * Get the production cycle for this task.
     *
     * @return BelongsTo<ProductionCycle, $this>
     */
    public function productionCycle(): BelongsTo
    {
        return $this->belongsTo(ProductionCycle::class);
    }

    /**
     * Get the user assigned to this task.
     *
     * @return BelongsTo<User, $this>
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    /**
     * Get the user who created this task.
     *
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * Get the user who completed this task.
     *
     * @return BelongsTo<User, $this>
     */
    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'planned_for' => 'date',
            'due_on' => 'date',
            'reminder_at' => 'datetime',
            'status' => FarmTaskStatus::class,
            'completed_at' => 'datetime',
            'investor_visible' => 'boolean',
        ];
    }
}

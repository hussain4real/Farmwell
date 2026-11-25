<?php

namespace App\Models;

use App\Enums\InventoryCategory;
use Database\Factories\InventoryItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $team_id
 * @property int|null $farm_id
 * @property string $name
 * @property InventoryCategory $category
 * @property string|null $sku
 * @property string $unit
 * @property string|null $unit_weight_kg
 * @property string $current_quantity
 * @property string $reorder_level
 * @property int $unit_cost_minor
 * @property string $currency
 * @property string|null $supplier_name
 * @property string|null $notes
 * @property bool $is_active
 * @property-read Team $team
 * @property-read Farm|null $farm
 */
#[Fillable([
    'team_id',
    'farm_id',
    'name',
    'category',
    'sku',
    'unit',
    'unit_weight_kg',
    'current_quantity',
    'reorder_level',
    'unit_cost_minor',
    'currency',
    'supplier_name',
    'notes',
    'is_active',
])]
class InventoryItem extends Model
{
    /** @use HasFactory<InventoryItemFactory> */
    use HasFactory;

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
     * @return HasMany<InventoryMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function isLowStock(): bool
    {
        return $this->is_active && (float) $this->current_quantity <= (float) $this->reorder_level;
    }

    public function stockValueMinor(): int
    {
        return (int) round((float) $this->current_quantity * $this->unit_cost_minor);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => InventoryCategory::class,
            'unit_weight_kg' => 'decimal:2',
            'current_quantity' => 'decimal:2',
            'reorder_level' => 'decimal:2',
            'unit_cost_minor' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}

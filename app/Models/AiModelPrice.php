<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\AiModelPriceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $provider
 * @property string $model_id
 * @property ?string $canonical_id
 * @property string $display_name
 * @property float $input_cost_per_million
 * @property float $output_cost_per_million
 * @property ?float $cache_read_cost_per_million
 * @property ?float $cache_write_cost_per_million
 * @property ?float $reasoning_cost_per_million
 * @property ?int $context_limit
 * @property ?int $output_limit
 * @property ?array<int, array<string, mixed>> $tiers
 * @property string $source
 * @property CarbonInterface $synced_at
 */
class AiModelPrice extends Model
{
    /** @use HasFactory<AiModelPriceFactory> */
    use HasFactory;

    protected $fillable = [
        'provider',
        'model_id',
        'canonical_id',
        'display_name',
        'input_cost_per_million',
        'output_cost_per_million',
        'cache_read_cost_per_million',
        'cache_write_cost_per_million',
        'reasoning_cost_per_million',
        'context_limit',
        'output_limit',
        'tiers',
        'source',
        'synced_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'input_cost_per_million' => 'float',
            'output_cost_per_million' => 'float',
            'cache_read_cost_per_million' => 'float',
            'cache_write_cost_per_million' => 'float',
            'reasoning_cost_per_million' => 'float',
            'context_limit' => 'integer',
            'output_limit' => 'integer',
            'tiers' => 'array',
            'synced_at' => 'datetime',
        ];
    }
}

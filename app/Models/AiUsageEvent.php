<?php

namespace App\Models;

use App\Enums\AiUsageFeature;
use Carbon\CarbonInterface;
use Database\Factories\AiUsageEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $invocation_id
 * @property ?int $team_id
 * @property ?int $user_id
 * @property ?int $impersonator_id
 * @property AiUsageFeature $feature
 * @property string $agent_class
 * @property string $provider
 * @property string $model
 * @property ?string $conversation_id
 * @property string $status
 * @property bool $is_streamed
 * @property int $steps_count
 * @property int $tool_calls_count
 * @property int $input_tokens
 * @property int $uncached_input_tokens
 * @property int $cache_read_tokens
 * @property int $cache_write_tokens
 * @property int $output_tokens
 * @property int $reasoning_tokens
 * @property int $total_tokens
 * @property int $input_cost_microdollars
 * @property int $output_cost_microdollars
 * @property int $cache_cost_microdollars
 * @property int $cost_microdollars
 * @property string $pricing_status
 * @property ?int $duration_ms
 * @property ?string $error_message
 * @property ?array<string, mixed> $metadata
 * @property CarbonInterface $occurred_at
 */
class AiUsageEvent extends Model
{
    /** @use HasFactory<AiUsageEventFactory> */
    use HasFactory;

    protected $fillable = [
        'invocation_id',
        'team_id',
        'user_id',
        'impersonator_id',
        'feature',
        'agent_class',
        'provider',
        'model',
        'conversation_id',
        'status',
        'is_streamed',
        'steps_count',
        'tool_calls_count',
        'input_tokens',
        'uncached_input_tokens',
        'cache_read_tokens',
        'cache_write_tokens',
        'output_tokens',
        'reasoning_tokens',
        'total_tokens',
        'input_cost_microdollars',
        'output_cost_microdollars',
        'cache_cost_microdollars',
        'cost_microdollars',
        'pricing_status',
        'duration_ms',
        'error_message',
        'metadata',
        'occurred_at',
    ];

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function impersonator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'impersonator_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'feature' => AiUsageFeature::class,
            'is_streamed' => 'boolean',
            'steps_count' => 'integer',
            'tool_calls_count' => 'integer',
            'input_tokens' => 'integer',
            'uncached_input_tokens' => 'integer',
            'cache_read_tokens' => 'integer',
            'cache_write_tokens' => 'integer',
            'output_tokens' => 'integer',
            'reasoning_tokens' => 'integer',
            'total_tokens' => 'integer',
            'input_cost_microdollars' => 'integer',
            'output_cost_microdollars' => 'integer',
            'cache_cost_microdollars' => 'integer',
            'cost_microdollars' => 'integer',
            'duration_ms' => 'integer',
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}

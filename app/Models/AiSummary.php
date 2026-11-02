<?php

namespace App\Models;

use Database\Factories\AiSummaryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiSummary extends Model
{
    /** @use HasFactory<AiSummaryFactory> */
    use HasFactory;

    protected $fillable = [
        'team_id', 'investor_agreement_id', 'farm_id', 'production_cycle_id', 'requested_by_id', 'status', 'prompt',
        'source_activity_ids', 'type', 'whatsapp_intake_id', 'input_text', 'source_records', 'source_snapshot', 'generation_context', 'published_by_id', 'published_at', 'generated_text', 'final_text', 'reviewed_by_id', 'reviewed_at',
    ];

    protected $attributes = ['status' => 'queued', 'type' => 'investor_progress'];

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<Farm, $this> */
    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    /** @return BelongsTo<ProductionCycle, $this> */
    public function productionCycle(): BelongsTo
    {
        return $this->belongsTo(ProductionCycle::class);
    }

    /** @return BelongsTo<InvestorAgreement, $this> */
    public function investorAgreement(): BelongsTo
    {
        return $this->belongsTo(InvestorAgreement::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['source_activity_ids' => 'array', 'source_records' => 'array', 'source_snapshot' => 'array', 'generation_context' => 'array', 'reviewed_at' => 'datetime', 'published_at' => 'datetime'];
    }
}

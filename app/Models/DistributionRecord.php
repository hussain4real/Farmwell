<?php

namespace App\Models;

use App\Enums\DistributionStatus;
use App\Enums\InvestorVisibilityStatus;
use Database\Factories\DistributionRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int $farm_id
 * @property int|null $production_cycle_id
 * @property int $investor_agreement_id
 * @property int $sale_record_id
 * @property int|null $approval_request_id
 * @property int $sale_gross_amount_minor
 * @property int $sale_net_amount_minor
 * @property int $previous_capital_recovered_minor
 * @property int $capital_recovered_minor
 * @property int $unrecovered_capital_minor
 * @property int $gross_profit_minor
 * @property int $net_profit_minor
 * @property int $investor_profit_share_percentage
 * @property int $farm_profit_share_percentage
 * @property int $investor_share_minor
 * @property int $farm_share_minor
 * @property string $currency
 * @property DistributionStatus $status
 * @property InvestorVisibilityStatus $investor_visibility_status
 * @property Carbon $calculated_at
 * @property Carbon|null $acknowledged_at
 * @property Carbon|null $paid_at
 * @property string|null $notes
 * @property-read Team $team
 * @property-read InvestorAgreement $investorAgreement
 * @property-read SaleRecord $saleRecord
 */
class DistributionRecord extends Model
{
    /** @use HasFactory<DistributionRecordFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'team_id',
        'farm_id',
        'production_cycle_id',
        'investor_agreement_id',
        'sale_record_id',
        'approval_request_id',
        'sale_gross_amount_minor',
        'sale_net_amount_minor',
        'previous_capital_recovered_minor',
        'capital_recovered_minor',
        'unrecovered_capital_minor',
        'gross_profit_minor',
        'net_profit_minor',
        'investor_profit_share_percentage',
        'farm_profit_share_percentage',
        'investor_share_minor',
        'farm_share_minor',
        'currency',
        'status',
        'investor_visibility_status',
        'calculated_at',
        'acknowledged_at',
        'paid_at',
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
     * @return BelongsTo<InvestorAgreement, $this>
     */
    public function investorAgreement(): BelongsTo
    {
        return $this->belongsTo(InvestorAgreement::class);
    }

    /**
     * @return BelongsTo<SaleRecord, $this>
     */
    public function saleRecord(): BelongsTo
    {
        return $this->belongsTo(SaleRecord::class);
    }

    /**
     * @return BelongsTo<ApprovalRequest, $this>
     */
    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sale_gross_amount_minor' => 'integer',
            'sale_net_amount_minor' => 'integer',
            'previous_capital_recovered_minor' => 'integer',
            'capital_recovered_minor' => 'integer',
            'unrecovered_capital_minor' => 'integer',
            'gross_profit_minor' => 'integer',
            'net_profit_minor' => 'integer',
            'investor_profit_share_percentage' => 'integer',
            'farm_profit_share_percentage' => 'integer',
            'investor_share_minor' => 'integer',
            'farm_share_minor' => 'integer',
            'status' => DistributionStatus::class,
            'investor_visibility_status' => InvestorVisibilityStatus::class,
            'calculated_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }
}

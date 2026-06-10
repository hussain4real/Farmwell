<?php

namespace App\Models;

use App\Enums\TransferReconciliationStatus;
use Database\Factories\TransferReconciliationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int $external_transfer_id
 * @property int|null $reconciled_by_id
 * @property TransferReconciliationStatus $status
 * @property int $reconciled_amount_minor
 * @property string $currency
 * @property Carbon $reconciled_at
 * @property string|null $notes
 * @property-read Team $team
 * @property-read ExternalTransfer $externalTransfer
 */
class TransferReconciliation extends Model
{
    /** @use HasFactory<TransferReconciliationFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'team_id',
        'external_transfer_id',
        'reconciled_by_id',
        'status',
        'reconciled_amount_minor',
        'currency',
        'reconciled_at',
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
     * @return BelongsTo<ExternalTransfer, $this>
     */
    public function externalTransfer(): BelongsTo
    {
        return $this->belongsTo(ExternalTransfer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reconciledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TransferReconciliationStatus::class,
            'reconciled_amount_minor' => 'integer',
            'reconciled_at' => 'datetime',
        ];
    }
}

<?php

namespace App\Models;

use Database\Factories\InvestorCommentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $team_id
 * @property int $investor_agreement_id
 * @property int $author_id
 * @property string $body
 */
class InvestorComment extends Model
{
    /** @use HasFactory<InvestorCommentFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'team_id',
        'investor_agreement_id',
        'author_id',
        'subject_type',
        'subject_id',
        'body',
    ];

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<InvestorAgreement, $this>
     */
    public function investorAgreement(): BelongsTo
    {
        return $this->belongsTo(InvestorAgreement::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}

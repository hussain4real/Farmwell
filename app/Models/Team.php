<?php

namespace App\Models;

use App\Concerns\GeneratesUniqueTeamSlugs;
use App\Enums\TeamRole;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property bool $is_personal
 */
#[Fillable(['name', 'slug', 'is_personal'])]
class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use GeneratesUniqueTeamSlugs, HasFactory, SoftDeletes;

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Team $team) {
            if (empty($team->slug)) {
                $team->slug = static::generateUniqueTeamSlug($team->name);
            }
        });

        static::updating(function (Team $team) {
            if ($team->isDirty('name')) {
                $team->slug = static::generateUniqueTeamSlug($team->name, $team->id);
            }
        });
    }

    /**
     * Get the team owner.
     */
    public function owner(): ?User
    {
        return $this->members()
            ->wherePivot('role', TeamRole::Owner->value)
            ->first();
    }

    /**
     * Get all members of this team.
     *
     * @return BelongsToMany<User, $this, Membership, 'pivot'>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_members', 'team_id', 'user_id')
            ->using(Membership::class)
            ->withPivot(['role'])
            ->withTimestamps();
    }

    /**
     * Get all memberships for this team.
     *
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * Get all invitations for this team.
     *
     * @return HasMany<TeamInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(TeamInvitation::class);
    }

    /**
     * Get all settings for this team.
     *
     * @return HasMany<TeamSetting, $this>
     */
    public function settings(): HasMany
    {
        return $this->hasMany(TeamSetting::class);
    }

    /**
     * Get all commodities for this team.
     *
     * @return HasMany<Commodity, $this>
     */
    public function commodities(): HasMany
    {
        return $this->hasMany(Commodity::class);
    }

    /**
     * Get all farms for this team.
     *
     * @return HasMany<Farm, $this>
     */
    public function farms(): HasMany
    {
        return $this->hasMany(Farm::class);
    }

    /**
     * Get all production units for this team.
     *
     * @return HasMany<ProductionUnit, $this>
     */
    public function productionUnits(): HasMany
    {
        return $this->hasMany(ProductionUnit::class);
    }

    /**
     * Get all production cycles for this team.
     *
     * @return HasMany<ProductionCycle, $this>
     */
    public function productionCycles(): HasMany
    {
        return $this->hasMany(ProductionCycle::class);
    }

    /**
     * Get all farm activities for this team.
     *
     * @return HasMany<FarmActivity, $this>
     */
    public function farmActivities(): HasMany
    {
        return $this->hasMany(FarmActivity::class);
    }

    /**
     * Get all farm tasks for this team.
     *
     * @return HasMany<FarmTask, $this>
     */
    public function farmTasks(): HasMany
    {
        return $this->hasMany(FarmTask::class);
    }

    /**
     * Get all WhatsApp intake records for this team.
     *
     * @return HasMany<WhatsappIntake, $this>
     */
    public function whatsappIntakes(): HasMany
    {
        return $this->hasMany(WhatsappIntake::class);
    }

    /**
     * Get all expense categories for this team.
     *
     * @return HasMany<ExpenseCategory, $this>
     */
    public function expenseCategories(): HasMany
    {
        return $this->hasMany(ExpenseCategory::class);
    }

    /**
     * Get all budgets for this team.
     *
     * @return HasMany<Budget, $this>
     */
    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    /**
     * Get all budget lines for this team.
     *
     * @return HasMany<BudgetLine, $this>
     */
    public function budgetLines(): HasMany
    {
        return $this->hasMany(BudgetLine::class);
    }

    /**
     * Get all funding phases for this team.
     *
     * @return HasMany<FundingPhase, $this>
     */
    public function fundingPhases(): HasMany
    {
        return $this->hasMany(FundingPhase::class);
    }

    /**
     * Get all expenses for this team.
     *
     * @return HasMany<Expense, $this>
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * Get all external transfers for this team.
     *
     * @return HasMany<ExternalTransfer, $this>
     */
    public function externalTransfers(): HasMany
    {
        return $this->hasMany(ExternalTransfer::class);
    }

    /**
     * Get all transfer reconciliations for this team.
     *
     * @return HasMany<TransferReconciliation, $this>
     */
    public function transferReconciliations(): HasMany
    {
        return $this->hasMany(TransferReconciliation::class);
    }

    /**
     * Get all investor agreements for this team.
     *
     * @return HasMany<InvestorAgreement, $this>
     */
    public function investorAgreements(): HasMany
    {
        return $this->hasMany(InvestorAgreement::class);
    }

    /**
     * Get all approval rules for this team.
     *
     * @return HasMany<ApprovalRule, $this>
     */
    public function approvalRules(): HasMany
    {
        return $this->hasMany(ApprovalRule::class);
    }

    /**
     * Get all approval requests for this team.
     *
     * @return HasMany<ApprovalRequest, $this>
     */
    public function approvalRequests(): HasMany
    {
        return $this->hasMany(ApprovalRequest::class);
    }

    /**
     * Get all investor comments for this team.
     *
     * @return HasMany<InvestorComment, $this>
     */
    public function investorComments(): HasMany
    {
        return $this->hasMany(InvestorComment::class);
    }

    /**
     * Get all audit events for this team.
     *
     * @return HasMany<AuditEvent, $this>
     */
    public function auditEvents(): HasMany
    {
        return $this->hasMany(AuditEvent::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_personal' => 'boolean',
        ];
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}

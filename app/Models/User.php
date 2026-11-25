<?php

namespace App\Models;

use App\Concerns\HasTeams;
use App\Enums\PlatformRole;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property PlatformRole|null $platform_role
 */
#[Fillable(['name', 'email', 'password', 'current_team_id'])]
#[Hidden(['phone', 'password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, HasTeams, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable {
        HasRoles::teams as permissionTeams;
        HasTeams::teams insteadof HasRoles;
    }

    /**
     * @return BelongsToMany<Team, $this>
     */
    public function phoneInvitedTeams(): BelongsToMany
    {
        return $this->teams()->whereIn('teams.id', TeamInvitation::query()
            ->select('team_id')->whereNotNull('phone')->where('phone', $this->phone)->whereNotNull('accepted_at'));
    }

    /**
     * Get investor agreements assigned to this user.
     *
     * @return HasMany<InvestorAgreement, $this>
     */
    public function investorAgreements(): HasMany
    {
        return $this->hasMany(InvestorAgreement::class, 'investor_id');
    }

    /**
     * Get approval requests decided by this user.
     *
     * @return HasMany<ApprovalRequest, $this>
     */
    public function decidedApprovalRequests(): HasMany
    {
        return $this->hasMany(ApprovalRequest::class, 'decided_by_id');
    }

    /**
     * Get harvest records recorded by this user.
     *
     * @return HasMany<HarvestRecord, $this>
     */
    public function recordedHarvests(): HasMany
    {
        return $this->hasMany(HarvestRecord::class, 'recorded_by_id');
    }

    /**
     * Get sale records recorded by this user.
     *
     * @return HasMany<SaleRecord, $this>
     */
    public function recordedSales(): HasMany
    {
        return $this->hasMany(SaleRecord::class, 'recorded_by_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'platform_role' => PlatformRole::class,
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}

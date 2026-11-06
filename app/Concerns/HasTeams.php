<?php

namespace App\Concerns;

use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use App\Models\Membership;
use App\Models\Team;
use App\Support\TeamPermissions;
use App\Support\UserTeam;
use Closure;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

trait HasTeams
{
    /**
     * Get all of the teams the user belongs to.
     *
     * @return BelongsToMany<Team, $this>
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_members', 'user_id', 'team_id')
            ->withPivot(['role'])
            ->withTimestamps();
    }

    /**
     * Get all of the teams the user owns.
     *
     * @return HasManyThrough<Team, Membership, $this>
     */
    public function ownedTeams(): HasManyThrough
    {
        return $this->hasManyThrough(
            Team::class,
            Membership::class,
            'user_id',
            'id',
            'id',
            'team_id',
        )->where('team_members.role', TeamRole::Owner->value);
    }

    /**
     * Get all of the memberships for the user.
     *
     * @return HasMany<Membership, $this>
     */
    public function teamMemberships(): HasMany
    {
        return $this->hasMany(Membership::class, 'user_id');
    }

    /**
     * Get the user's current team.
     *
     * @return BelongsTo<Team, $this>
     */
    public function currentTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'current_team_id');
    }

    /**
     * Get the user's personal team.
     */
    public function personalTeam(): ?Team
    {
        return $this->teams()
            ->where('is_personal', true)
            ->first();
    }

    /**
     * Switch to the given team.
     */
    public function switchTeam(Team $team): bool
    {
        if (! $this->belongsToTeam($team)) {
            return false;
        }

        $this->update(['current_team_id' => $team->id]);
        $this->setRelation('currentTeam', $team);
        $this->activatePermissionsTeam($team);

        URL::defaults(['current_team' => $team->slug]);

        return true;
    }

    /**
     * Determine if the user belongs to the given team.
     */
    public function belongsToTeam(Team $team): bool
    {
        return $this->teams()->where('teams.id', $team->id)->exists();
    }

    /**
     * Determine if the given team is the user's current team.
     */
    public function isCurrentTeam(Team $team): bool
    {
        return $this->current_team_id === $team->id;
    }

    /**
     * Determine if the user is the owner of the given team.
     */
    public function ownsTeam(Team $team): bool
    {
        return $this->teamRole($team) === TeamRole::Owner;
    }

    /**
     * Get the user's role on the given team.
     */
    public function teamRole(Team $team): ?TeamRole
    {
        /** @var TeamRole|null $role */
        $role = $this->teamMemberships()
            ->where('team_id', $team->id)
            ->first()
            ?->role;

        return $role;
    }

    /**
     * Get the user's teams as a collection of UserTeam objects.
     *
     * @return Collection<int, UserTeam>
     */
    public function toUserTeams(bool $includeCurrent = false): Collection
    {
        return $this->teams()
            ->get()
            ->map(fn (Team $team) => ! $includeCurrent && $this->isCurrentTeam($team) ? null : $this->toUserTeam($team))
            ->filter()
            ->values();
    }

    /**
     * Get the user's team as a UserTeam object.
     */
    public function toUserTeam(Team $team): UserTeam
    {
        $role = $this->teamRole($team);

        return new UserTeam(
            id: $team->id,
            name: $team->name,
            slug: $team->slug,
            isPersonal: $team->is_personal,
            role: $role?->value,
            roleLabel: $role?->label(),
            isCurrent: $this->isCurrentTeam($team),
        );
    }

    /**
     * Get the standard permissions for a team as a TeamPermissions object.
     */
    public function toTeamPermissions(Team $team): TeamPermissions
    {
        $attributes = request()->attributes;
        $key = "farmwell.team-permissions.{$this->id}.{$team->id}";
        $permissions = $attributes->get($key);
        if ($permissions instanceof TeamPermissions) {
            return $permissions;
        }

        $role = $this->teamRole($team);
        $permissions = $this->inTeamPermissionContext($team, function () use ($role): TeamPermissions {
            $can = fn (TeamPermission $permission): bool => $role !== null
                && ($role->hasPermission($permission) || $this->can($permission->value, 'web'));

            return new TeamPermissions(
                canUpdateTeam: $can(TeamPermission::UpdateTeam),
                canDeleteTeam: $can(TeamPermission::DeleteTeam),
                canAddMember: $can(TeamPermission::AddMember),
                canUpdateMember: $can(TeamPermission::UpdateMember),
                canRemoveMember: $can(TeamPermission::RemoveMember),
                canCreateInvitation: $can(TeamPermission::CreateInvitation),
                canCancelInvitation: $can(TeamPermission::CancelInvitation),
                canManageSettings: $can(TeamPermission::ManageSettings),
                canViewAuditEvents: $can(TeamPermission::ViewAuditEvents),
                canViewFarmOperations: $can(TeamPermission::ViewFarmOperations),
                canManageFarmOperations: $can(TeamPermission::ManageFarmOperations),
                canManageFieldWork: $can(TeamPermission::ManageFarmOperations) || $can(TeamPermission::ManageFieldWork),
                canViewFinance: $can(TeamPermission::ViewFinance),
                canManageFinance: $can(TeamPermission::ManageFinance),
                canViewInvestorPortal: $can(TeamPermission::ViewInvestorPortal),
                canViewInvestorAgreements: $can(TeamPermission::ViewInvestorAgreements),
                canManageInvestorAgreements: $can(TeamPermission::ManageInvestorAgreements),
                canViewApprovalRequests: $can(TeamPermission::ViewApprovalRequests),
                canManageApprovalRequests: $can(TeamPermission::ManageApprovalRequests),
            );
        });
        $attributes->set($key, $permissions);

        return $permissions;
    }

    public function switchAfterLeaving(Team $team): void
    {
        $personal = $this->personalTeam();
        $next = $personal && ! $personal->is($team) ? $personal : $this->fallbackTeam($team);
        if ($next) {
            $this->switchTeam($next);
        } else {
            $this->update(['current_team_id' => null]);
            $this->unsetRelation('currentTeam');
        }
    }

    public function fallbackTeam(?Team $excluding = null): ?Team
    {
        return $this->teams()
            ->when($excluding, fn ($query) => $query->where('teams.id', '!=', $excluding->id))
            ->orderByRaw('LOWER(teams.name)')
            ->first();
    }

    /**
     * Determine if the user has the given permission on the team.
     */
    public function hasTeamPermission(Team $team, TeamPermission $permission): bool
    {
        $role = $this->teamRole($team);

        if ($role === null) {
            return false;
        }

        if ($role->hasPermission($permission)) {
            return true;
        }

        return $this->inTeamPermissionContext($team, fn (): bool => $this->can($permission->value, 'web'));
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $check
     * @return T
     */
    private function inTeamPermissionContext(Team $team, Closure $check): mixed
    {
        $previousTeamId = getPermissionsTeamId();

        setPermissionsTeamId($team->id);
        $this->unsetRelation('roles')->unsetRelation('permissions');

        try {
            return $check();
        } finally {
            setPermissionsTeamId($previousTeamId);
            $this->unsetRelation('roles')->unsetRelation('permissions');
        }
    }

    /**
     * Activate package-backed permissions for the given team.
     */
    public function activatePermissionsTeam(Team $team): void
    {
        setPermissionsTeamId($team->id);
        $this->unsetRelation('roles')->unsetRelation('permissions');
    }
}

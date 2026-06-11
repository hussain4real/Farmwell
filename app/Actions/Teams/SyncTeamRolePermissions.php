<?php

namespace App\Actions\Teams;

use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SyncTeamRolePermissions
{
    private const string GuardName = 'web';

    /**
     * Ensure the package-backed permissions mirror Farmwell's role enum.
     */
    public function ensureBaseline(): void
    {
        $previousTeamId = getPermissionsTeamId();

        setPermissionsTeamId(null);

        try {
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            foreach (TeamPermission::cases() as $permission) {
                Permission::findOrCreate($permission->value, self::GuardName);
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            foreach (TeamRole::cases() as $teamRole) {
                $role = Role::findOrCreate($teamRole->value, self::GuardName);

                $role->syncPermissions(
                    collect($teamRole->permissions())
                        ->map(fn (TeamPermission $permission) => $permission->value)
                        ->all(),
                );
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } finally {
            setPermissionsTeamId($previousTeamId);
        }
    }

    /**
     * Sync one membership's package-backed role for a team.
     */
    public function syncMembership(User $user, Team $team, TeamRole $role): void
    {
        $this->ensureBaseline();

        $previousTeamId = getPermissionsTeamId();

        setPermissionsTeamId($team->id);

        try {
            $user->unsetRelation('roles')->unsetRelation('permissions');
            $user->syncRoles($role->value);
        } finally {
            $user->unsetRelation('roles')->unsetRelation('permissions');
            setPermissionsTeamId($previousTeamId);
        }
    }

    /**
     * Remove one membership's package-backed roles for a team.
     */
    public function removeMembership(User $user, Team $team): void
    {
        $previousTeamId = getPermissionsTeamId();

        setPermissionsTeamId($team->id);

        try {
            $user->unsetRelation('roles')->unsetRelation('permissions');
            $user->syncRoles([]);
        } finally {
            $user->unsetRelation('roles')->unsetRelation('permissions');
            setPermissionsTeamId($previousTeamId);
        }
    }
}

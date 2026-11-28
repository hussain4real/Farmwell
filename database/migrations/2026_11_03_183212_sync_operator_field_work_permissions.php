<?php

use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $previousTeamId = getPermissionsTeamId();
        setPermissionsTeamId(null);

        try {
            $role = Role::query()->whereNull('team_id')->where('name', TeamRole::Operator->value)->where('guard_name', 'web')->first();
            if ($role === null) {
                return;
            }

            Permission::findOrCreate(TeamPermission::ManageFieldWork->value, 'web');
            $role->syncPermissions([TeamPermission::ManageFieldWork->value]);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } finally {
            setPermissionsTeamId($previousTeamId);
        }
    }

    public function down(): void
    {
        // Preserve the restricted role and existing assignments on rollback.
    }
};

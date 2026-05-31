<?php

namespace Database\Seeders;

use App\Actions\Teams\SyncTeamRolePermissions;
use App\Enums\TeamRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(SyncTeamRolePermissions $syncTeamRolePermissions): void
    {
        // User::factory(10)->create();

        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        if ($team = $user->personalTeam()) {
            $syncTeamRolePermissions->syncMembership($user, $team, TeamRole::Owner);
        }
    }
}

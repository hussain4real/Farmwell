<?php

namespace App\Actions\Teams;

use App\Enums\TeamRole;
use App\Enums\WorkspacePlan;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateTeam
{
    public function __construct(
        private SyncTeamRolePermissions $syncTeamRolePermissions,
        private ResolveWorkspacePlan $resolveWorkspacePlan,
    ) {
        //
    }

    /**
     * Create a new team and add the user as owner.
     */
    public function handle(
        User $user,
        string $name,
        bool $isPersonal = false,
        WorkspacePlan $plan = WorkspacePlan::Starter,
    ): Team {
        return DB::transaction(function () use ($user, $name, $isPersonal, $plan) {
            $team = Team::create([
                'name' => $name,
                'is_personal' => $isPersonal,
            ]);

            $team->memberships()->create([
                'user_id' => $user->id,
                'role' => TeamRole::Owner,
            ]);

            $this->resolveWorkspacePlan->setPlan($team, $plan, $user);

            $this->syncTeamRolePermissions->syncMembership($user, $team, TeamRole::Owner);

            $user->switchTeam($team);

            return $team;
        });
    }
}

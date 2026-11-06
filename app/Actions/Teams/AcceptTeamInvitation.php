<?php

namespace App\Actions\Teams;

use App\Models\TeamInvitation;
use App\Models\User;

class AcceptTeamInvitation
{
    public function __construct(private SyncTeamRolePermissions $roles) {}

    public function handle(TeamInvitation $invitation, User $user): void
    {
        $team = $invitation->team;
        $membership = $team->memberships()->firstOrCreate(['user_id' => $user->id], ['role' => $invitation->role]);
        $this->roles->syncMembership($user, $team, $membership->role);
        $invitation->update(['accepted_at' => now()]);
        $user->switchTeam($team);
    }
}

<?php

namespace App\Http\Controllers\Teams;

use App\Actions\Audit\RecordAuditEvent;
use App\Actions\Teams\SyncTeamRolePermissions;
use App\Enums\TeamRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\UpdateTeamMemberRequest;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class TeamMemberController extends Controller
{
    public function __construct(
        private RecordAuditEvent $recordAuditEvent,
        private SyncTeamRolePermissions $syncTeamRolePermissions,
    ) {
        //
    }

    /**
     * Update the specified team member's role.
     */
    public function update(UpdateTeamMemberRequest $request, Team $team, User $user): RedirectResponse
    {
        Gate::authorize('updateMember', $team);

        $newRole = TeamRole::from($request->validated('role'));

        $membership = $team->memberships()
            ->where('user_id', $user->id)
            ->firstOrFail();

        abort_if($membership->role === TeamRole::Owner, 403, __('The team owner role cannot be changed.'));

        $oldRole = $membership->role;

        $membership->update(['role' => $newRole]);

        $this->syncTeamRolePermissions->syncMembership($user, $team, $newRole);

        if ($oldRole !== $newRole) {
            $this->recordAuditEvent->handle(
                team: $team,
                actor: $request->user(),
                action: 'team_member.role_updated',
                subject: $membership,
                oldValues: [
                    'role' => $oldRole->value,
                ],
                newValues: [
                    'role' => $newRole->value,
                    'user_id' => $user->id,
                ],
                reason: $request->reason(),
                metadata: [
                    'member_id' => $user->id,
                ],
            );
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member role updated.')]);

        return to_route('teams.edit', ['team' => $team->slug]);
    }

    /**
     * Remove the specified team member.
     */
    public function destroy(Team $team, User $user): RedirectResponse
    {
        Gate::authorize('removeMember', $team);

        abort_if($team->owner()?->is($user), 403, __('The team owner cannot be removed.'));

        $team->memberships()
            ->where('user_id', $user->id)
            ->delete();

        if ($user->isCurrentTeam($team)) {
            $user->switchTeam($user->personalTeam());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member removed.')]);

        return to_route('teams.edit', ['team' => $team->slug]);
    }
}

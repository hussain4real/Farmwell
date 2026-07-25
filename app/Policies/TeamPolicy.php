<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Models\Team;
use App\Models\User;

class TeamPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::UpdateTeam);
    }

    /**
     * Determine whether the user can add a member to the team.
     */
    public function addMember(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::AddMember);
    }

    /**
     * Determine whether the user can update a member's role in the team.
     */
    public function updateMember(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::UpdateMember);
    }

    /**
     * Determine whether the user can remove a member from the team.
     */
    public function removeMember(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::RemoveMember);
    }

    /**
     * Determine whether the user can invite members to the team.
     */
    public function inviteMember(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::CreateInvitation);
    }

    /**
     * Determine whether the user can cancel invitations.
     */
    public function cancelInvitation(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::CancelInvitation);
    }

    /**
     * Determine whether the user can manage team settings.
     */
    public function manageSettings(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::ManageSettings);
    }

    /**
     * Determine whether the user can view team audit events.
     */
    public function viewAuditEvents(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::ViewAuditEvents);
    }

    /**
     * Determine whether the user can view farm operating records.
     */
    public function viewFarmOperations(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::ViewFarmOperations);
    }

    /**
     * Determine whether the user can manage farm operating records.
     */
    public function manageFarmOperations(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::ManageFarmOperations);
    }

    /**
     * Determine whether the user can view finance records.
     */
    public function viewFinance(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::ViewFinance);
    }

    /**
     * Determine whether the user can manage finance records.
     */
    public function manageFinance(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::ManageFinance);
    }

    /**
     * Determine whether the user can view their investor portal.
     */
    public function viewInvestorPortal(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::ViewInvestorPortal);
    }

    /**
     * Determine whether the user can view investor agreements.
     */
    public function viewInvestorAgreements(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::ViewInvestorAgreements);
    }

    /**
     * Determine whether the user can manage investor agreements.
     */
    public function manageInvestorAgreements(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::ManageInvestorAgreements);
    }

    /**
     * Determine whether the user can view approval requests.
     */
    public function viewApprovalRequests(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::ViewApprovalRequests);
    }

    /**
     * Determine whether the user can manage approval requests.
     */
    public function manageApprovalRequests(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::ManageApprovalRequests);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Team $team): bool
    {
        return ! $team->is_personal && $user->hasTeamPermission($team, TeamPermission::DeleteTeam);
    }
}

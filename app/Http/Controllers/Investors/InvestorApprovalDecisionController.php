<?php

namespace App\Http\Controllers\Investors;

use App\Actions\Investors\DecideApprovalRequest;
use App\Enums\ApprovalRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Investors\DecideApprovalRequestRequest;
use App\Models\ApprovalRequest;
use App\Models\Team;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class InvestorApprovalDecisionController extends Controller
{
    public function store(
        DecideApprovalRequestRequest $request,
        Team $currentTeam,
        ApprovalRequest $approvalRequest,
        DecideApprovalRequest $decideApprovalRequest,
    ): RedirectResponse {
        $user = $request->user();
        assert($user instanceof User);

        $validated = $request->validated();
        $approvedAmount = $validated['approved_amount'] ?? null;

        $decideApprovalRequest->handle(
            team: $currentTeam,
            approvalRequest: $approvalRequest,
            actor: $user,
            status: ApprovalRequestStatus::from((string) $validated['status']),
            approvedAmountMinor: is_string($approvedAmount) ? Money::toMinorUnit($approvedAmount) : null,
            comment: is_string($validated['decision_comment'] ?? null) ? $validated['decision_comment'] : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Approval request updated.')]);

        if (! $user->can('manageApprovalRequests', $currentTeam)) {
            return to_route('investor-portal.index', ['current_team' => $currentTeam]);
        }

        return to_route('investors.approvals.index', ['current_team' => $currentTeam]);
    }
}

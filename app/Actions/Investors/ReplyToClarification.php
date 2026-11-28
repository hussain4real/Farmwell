<?php

namespace App\Actions\Investors;

use App\Enums\ApprovalRequestStatus;
use App\Models\InvestorComment;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReplyToClarification
{
    public function __construct(private RecordInvestorComment $comments) {}

    public static function canReply(User $user, Team $team): bool
    {
        return $user->can('viewApprovalRequests', $team)
            && $user->can('manageInvestorAgreements', $team);
    }

    /** @return array<string, list<string>> */
    public static function rules(): array
    {
        return ['body' => ['required', 'string', 'max:2000']];
    }

    public function handle(Team $team, User $actor, int $requestId, string $body): InvestorComment
    {
        abort_unless(self::canReply($actor, $team), 403);

        return DB::transaction(function () use ($team, $actor, $requestId, $body): InvestorComment {
            $request = $team->approvalRequests()->lockForUpdate()->findOrFail($requestId);

            if ($request->status !== ApprovalRequestStatus::ClarificationRequested) {
                throw ValidationException::withMessages(['body' => 'This request is no longer awaiting clarification.']);
            }

            $agreement = $team->investorAgreements()->findOrFail($request->investor_agreement_id);
            $existingReply = $request->clarificationReplies()
                ->whereBelongsTo($agreement, 'investorAgreement')
                ->where('team_id', $team->id)
                ->where('author_id', $actor->id)
                ->where('body', $body)
                ->where('created_at', '>=', $request->decided_at ?? $request->requested_at)
                ->latest('id')
                ->first();

            return $existingReply ?? $this->comments->handle($team, $agreement, $actor, $body, $request);
        });
    }
}

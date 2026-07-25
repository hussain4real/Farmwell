<?php

namespace App\Actions\Investors;

use App\Actions\Audit\RecordAuditEvent;
use App\Models\InvestorAgreement;
use App\Models\InvestorComment;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class RecordInvestorComment
{
    public function __construct(private RecordAuditEvent $recordAuditEvent)
    {
        //
    }

    public function handle(Team $team, InvestorAgreement $agreement, User $actor, string $body, ?Model $subject = null): InvestorComment
    {
        $comment = InvestorComment::create([
            'team_id' => $team->id,
            'investor_agreement_id' => $agreement->id,
            'author_id' => $actor->id,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'body' => $body,
        ]);

        $this->recordAuditEvent->handle(
            team: $team,
            actor: $actor,
            action: 'investor_comment.created',
            subject: $comment,
            newValues: [
                'investor_agreement_id' => $agreement->id,
                'subject_type' => $comment->subject_type,
                'subject_id' => $comment->subject_id,
            ],
        );

        return $comment->refresh();
    }
}

<?php

namespace App\Actions\Investors;

use App\Actions\Audit\RecordAuditEvent;
use App\Models\InvestorAgreement;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;

class AttachInvestorAgreementDocument
{
    public function __construct(private RecordAuditEvent $recordAuditEvent)
    {
        //
    }

    public function handle(Team $team, InvestorAgreement $agreement, User $actor, UploadedFile $document, ?string $caption = null): InvestorAgreement
    {
        $agreement
            ->addMedia($document)
            ->withCustomProperties([
                'caption' => $caption,
                'visibility' => 'private',
                'uploaded_by_id' => $actor->id,
            ])
            ->toMediaCollection(InvestorAgreement::DocumentsCollection, 'farmwell_private');

        $this->recordAuditEvent->handle(
            team: $team,
            actor: $actor,
            action: 'investor_agreement_document.attached',
            subject: $agreement,
            newValues: [
                'document_count' => 1,
            ],
        );

        return $agreement->refresh();
    }
}

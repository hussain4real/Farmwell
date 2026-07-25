<?php

namespace App\Actions\Investors;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\CapitalRecoveryRule;
use App\Enums\InvestorAgreementStatus;
use App\Models\InvestorAgreement;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class CreateInvestorAgreement
{
    public function __construct(private RecordAuditEvent $recordAuditEvent)
    {
        //
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Team $team, User $actor, array $attributes, ?UploadedFile $document = null, ?string $caption = null): InvestorAgreement
    {
        return DB::transaction(function () use ($team, $actor, $attributes, $document, $caption): InvestorAgreement {
            $agreement = InvestorAgreement::create([
                ...$attributes,
                'team_id' => $team->id,
                'created_by_id' => $actor->id,
                'status' => InvestorAgreementStatus::tryFrom((string) ($attributes['status'] ?? InvestorAgreementStatus::Active->value)) ?? InvestorAgreementStatus::Active,
                'capital_recovery_rule' => CapitalRecoveryRule::tryFrom((string) ($attributes['capital_recovery_rule'] ?? CapitalRecoveryRule::CapitalFirst->value)) ?? CapitalRecoveryRule::CapitalFirst,
            ]);

            if ($document instanceof UploadedFile) {
                $agreement
                    ->addMedia($document)
                    ->withCustomProperties([
                        'caption' => $caption,
                        'visibility' => 'private',
                        'uploaded_by_id' => $actor->id,
                    ])
                    ->toMediaCollection(InvestorAgreement::DocumentsCollection, 'farmwell_private');
            }

            $this->recordAuditEvent->handle(
                team: $team,
                actor: $actor,
                action: 'investor_agreement.created',
                subject: $agreement,
                newValues: [
                    'investor_id' => $agreement->investor_id,
                    'farm_id' => $agreement->farm_id,
                    'production_cycle_id' => $agreement->production_cycle_id,
                    'amount_committed_minor' => $agreement->amount_committed_minor,
                    'amount_funded_minor' => $agreement->amount_funded_minor,
                    'currency' => $agreement->currency,
                    'status' => $agreement->status->value,
                ],
            );

            if ($document instanceof UploadedFile) {
                $this->recordAuditEvent->handle(
                    team: $team,
                    actor: $actor,
                    action: 'investor_agreement_document.attached',
                    subject: $agreement,
                    newValues: [
                        'document_count' => 1,
                    ],
                );
            }

            return $agreement->refresh();
        });
    }
}

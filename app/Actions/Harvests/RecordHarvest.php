<?php

namespace App\Actions\Harvests;

use App\Actions\Audit\RecordAuditEvent;
use App\Actions\Finance\ResolveTeamFinanceCurrency;
use App\Models\HarvestRecord;
use App\Models\InvestorAgreement;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordHarvest
{
    public function __construct(
        private AttachHarvestEvidence $attachHarvestEvidence,
        private RecordAuditEvent $recordAuditEvent,
        private ResolveTeamFinanceCurrency $resolveTeamFinanceCurrency,
    ) {
        //
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, UploadedFile>  $evidence
     */
    public function handle(Team $team, User $actor, array $attributes, array $evidence = [], ?string $caption = null): HarvestRecord
    {
        return DB::transaction(function () use ($team, $actor, $attributes, $evidence, $caption): HarvestRecord {
            $this->validateInvestorAgreementScope($team, $attributes);

            $harvest = HarvestRecord::create([
                ...$attributes,
                'team_id' => $team->id,
                'recorded_by_id' => $actor->id,
                'currency' => $this->resolveTeamFinanceCurrency->handle($team),
            ]);

            $this->recordAuditEvent->handle(
                team: $team,
                actor: $actor,
                action: 'harvest.recorded',
                subject: $harvest,
                newValues: [
                    'farm_id' => $harvest->farm_id,
                    'production_cycle_id' => $harvest->production_cycle_id,
                    'commodity_id' => $harvest->commodity_id,
                    'investor_agreement_id' => $harvest->investor_agreement_id,
                    'quantity' => $harvest->quantity,
                    'quantity_unit' => $harvest->quantity_unit,
                    'stage' => $harvest->stage->value,
                    'status' => $harvest->status->value,
                    'investor_visibility_status' => $harvest->investor_visibility_status->value,
                ],
            );

            if ($evidence !== []) {
                $this->attachHarvestEvidence->handle($harvest, $evidence, HarvestRecord::EvidenceCollection, $actor, $caption);

                $this->recordAuditEvent->handle(
                    team: $team,
                    actor: $actor,
                    action: 'harvest_evidence.attached',
                    subject: $harvest,
                    newValues: [
                        'evidence_count' => count($evidence),
                    ],
                );
            }

            return $harvest->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function validateInvestorAgreementScope(Team $team, array $attributes): void
    {
        $agreementId = $attributes['investor_agreement_id'] ?? null;

        if ($agreementId === null) {
            return;
        }

        $agreement = InvestorAgreement::query()
            ->where('team_id', $team->id)
            ->find((int) $agreementId);
        $farmId = (int) $attributes['farm_id'];
        $productionCycleId = isset($attributes['production_cycle_id'])
            ? (int) $attributes['production_cycle_id']
            : null;

        if (
            ! $agreement instanceof InvestorAgreement
            || $agreement->farm_id !== $farmId
            || (
                $agreement->production_cycle_id !== null
                && $agreement->production_cycle_id !== $productionCycleId
            )
        ) {
            throw ValidationException::withMessages([
                'investor_agreement_id' => __('The investor agreement must belong to the harvest farm and production cycle.'),
            ]);
        }
    }
}

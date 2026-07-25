<?php

namespace App\Actions\Harvests;

use App\Actions\Audit\RecordAuditEvent;
use App\Actions\Finance\ResolveTeamFinanceCurrency;
use App\Enums\HarvestRecordStatus;
use App\Models\HarvestRecord;
use App\Models\InvestorAgreement;
use App\Models\SaleRecord;
use App\Models\Team;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordSale
{
    public function __construct(
        private AttachHarvestEvidence $attachHarvestEvidence,
        private RecalculateDistribution $recalculateDistribution,
        private RecordAuditEvent $recordAuditEvent,
        private ResolveTeamFinanceCurrency $resolveTeamFinanceCurrency,
    ) {
        //
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, UploadedFile>  $evidence
     */
    public function handle(Team $team, User $actor, array $attributes, array $evidence = [], ?string $caption = null): SaleRecord
    {
        return DB::transaction(function () use ($team, $actor, $attributes, $evidence, $caption): SaleRecord {
            $harvest = HarvestRecord::query()
                ->where('team_id', $team->id)
                ->lockForUpdate()
                ->findOrFail((int) $attributes['harvest_record_id']);

            $this->validateAvailableHarvestQuantity($harvest, $attributes);
            $this->validateSaleAmounts($attributes);
            $agreement = $this->resolveInvestorAgreement($team, $harvest, $attributes);
            $currency = $this->resolveTeamFinanceCurrency->handle($team);
            $this->validateInvestorAgreementCurrency($agreement, $currency);

            $sale = SaleRecord::create([
                ...$attributes,
                'team_id' => $team->id,
                'farm_id' => $harvest->farm_id,
                'production_cycle_id' => $harvest->production_cycle_id,
                'commodity_id' => $harvest->commodity_id,
                'investor_agreement_id' => $agreement?->id,
                'quantity_unit' => $harvest->quantity_unit,
                'recorded_by_id' => $actor->id,
                'currency' => $currency,
            ]);

            $this->updateHarvestSaleStatus($harvest);

            $this->recordAuditEvent->handle(
                team: $team,
                actor: $actor,
                action: 'sale.recorded',
                subject: $sale,
                newValues: [
                    'harvest_record_id' => $sale->harvest_record_id,
                    'buyer_name' => $sale->buyer_name,
                    'gross_amount_minor' => $sale->gross_amount_minor,
                    'deduction_amount_minor' => $sale->deduction_amount_minor,
                    'net_amount_minor' => $sale->net_amount_minor,
                    'payment_status' => $sale->payment_status->value,
                    'investor_agreement_id' => $sale->investor_agreement_id,
                    'investor_visibility_status' => $sale->investor_visibility_status->value,
                ],
            );

            if ($evidence !== []) {
                $this->attachHarvestEvidence->handle($sale, $evidence, SaleRecord::EvidenceCollection, $actor, $caption);

                $this->recordAuditEvent->handle(
                    team: $team,
                    actor: $actor,
                    action: 'sale_evidence.attached',
                    subject: $sale,
                    newValues: [
                        'evidence_count' => count($evidence),
                    ],
                );
            }

            $this->recalculateDistribution->handle($team, $sale->refresh(), $actor);

            return $sale->refresh();
        });
    }

    private function updateHarvestSaleStatus(HarvestRecord $harvest): void
    {
        $soldQuantity = (float) $harvest->sales()->sum('quantity');
        $harvestQuantity = (float) $harvest->quantity;

        $harvest->forceFill([
            'status' => $soldQuantity >= $harvestQuantity
                ? HarvestRecordStatus::Sold
                : HarvestRecordStatus::PartiallySold,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function validateAvailableHarvestQuantity(HarvestRecord $harvest, array $attributes): void
    {
        if ($this->normalizeQuantityUnit((string) $attributes['quantity_unit']) !== $this->normalizeQuantityUnit($harvest->quantity_unit)) {
            throw ValidationException::withMessages([
                'quantity_unit' => __('The sale quantity unit must match the harvest quantity unit.'),
            ]);
        }

        $remainingQuantity = max(
            0,
            $this->quantityInHundredths($harvest->quantity)
            - $this->quantityInHundredths((string) $harvest->sales()->sum('quantity')),
        );

        if ($this->quantityInHundredths((string) $attributes['quantity']) > $remainingQuantity) {
            throw ValidationException::withMessages([
                'quantity' => __('The sale quantity may not exceed the remaining harvest quantity.'),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function resolveInvestorAgreement(Team $team, HarvestRecord $harvest, array $attributes): ?InvestorAgreement
    {
        $agreementId = $attributes['investor_agreement_id'] ?? $harvest->investor_agreement_id;

        if ($agreementId === null) {
            return null;
        }

        $agreement = InvestorAgreement::query()
            ->where('team_id', $team->id)
            ->find((int) $agreementId);

        if (
            ! $agreement instanceof InvestorAgreement
            || $agreement->farm_id !== $harvest->farm_id
            || (
                $agreement->production_cycle_id !== null
                && $agreement->production_cycle_id !== $harvest->production_cycle_id
            )
        ) {
            throw ValidationException::withMessages([
                'investor_agreement_id' => __('The investor agreement must belong to the harvest farm and production cycle.'),
            ]);
        }

        return $agreement;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function validateSaleAmounts(array $attributes): void
    {
        $expectedGrossAmountMinor = intdiv(
            ($this->quantityInHundredths((string) $attributes['quantity']) * (int) $attributes['unit_price_minor']) + 50,
            100,
        );

        if ($expectedGrossAmountMinor !== (int) $attributes['gross_amount_minor']) {
            throw ValidationException::withMessages([
                'gross_amount' => __('The gross amount must equal the sale quantity multiplied by the unit price.'),
            ]);
        }
    }

    private function validateInvestorAgreementCurrency(?InvestorAgreement $agreement, string $saleCurrency): void
    {
        if (
            $agreement instanceof InvestorAgreement
            && Money::normalizeCurrency($agreement->currency) !== Money::normalizeCurrency($saleCurrency)
        ) {
            throw ValidationException::withMessages([
                'investor_agreement_id' => __('The investor agreement currency must match the sale currency.'),
            ]);
        }
    }

    private function normalizeQuantityUnit(string $unit): string
    {
        return mb_strtolower(trim($unit));
    }

    private function quantityInHundredths(string $quantity): int
    {
        return (int) round((float) $quantity * 100);
    }
}

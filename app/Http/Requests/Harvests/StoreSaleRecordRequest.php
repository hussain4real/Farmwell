<?php

namespace App\Http\Requests\Harvests;

use App\Enums\InvestorVisibilityStatus;
use App\Enums\SalePaymentStatus;
use App\Models\HarvestRecord;
use App\Models\InvestorAgreement;
use App\Models\Team;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;

class StoreSaleRecordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $team = $this->route('current_team');

        return $team instanceof Team && $this->user()?->can('manageFinance', $team) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'harvest_record_id' => ['required', 'integer', $this->harvestRule()],
            'investor_agreement_id' => ['nullable', 'integer', $this->investorAgreementRule()],
            'sold_on' => ['required', 'date'],
            'buyer_name' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'quantity_unit' => ['required', 'string', 'max:32'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'gross_amount' => ['required', 'numeric', 'min:0'],
            'deduction_amount' => ['nullable', 'numeric', 'min:0', 'lte:gross_amount'],
            'payment_status' => ['required', Rule::enum(SalePaymentStatus::class)],
            'reference' => ['nullable', 'string', 'max:255'],
            'investor_visibility_status' => ['sometimes', 'filled', Rule::enum(InvestorVisibilityStatus::class)],
            'notes' => ['nullable', 'string', 'max:4000'],
            'evidence_caption' => ['nullable', 'string', 'max:255'],
            'evidence' => ['nullable', 'array', 'max:5'],
            'evidence.*' => [
                'file',
                File::types(['jpg', 'jpeg', 'png', 'webp', 'pdf', 'doc', 'docx'])
                    ->max(10 * 1024),
            ],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('harvest_record_id')) {
                    return;
                }

                if (! $validator->errors()->hasAny(['quantity', 'unit_price', 'gross_amount'])) {
                    $expectedGrossAmountMinor = intdiv(
                        ($this->quantityInHundredths((string) $this->input('quantity')) * Money::toMinorUnit((string) $this->input('unit_price'))) + 50,
                        100,
                    );

                    if (Money::toMinorUnit((string) $this->input('gross_amount')) !== $expectedGrossAmountMinor) {
                        $validator->errors()->add(
                            'gross_amount',
                            __('The gross amount must equal the sale quantity multiplied by the unit price.'),
                        );
                    }
                }

                $harvest = HarvestRecord::query()
                    ->where('team_id', $this->team()->id)
                    ->findOrFail($this->integer('harvest_record_id'));

                if (
                    ! $validator->errors()->has('quantity_unit')
                    && $this->normalizeQuantityUnit((string) $this->input('quantity_unit')) !== $this->normalizeQuantityUnit($harvest->quantity_unit)
                ) {
                    $validator->errors()->add('quantity_unit', __('The sale quantity unit must match the harvest quantity unit.'));
                }

                if (! $validator->errors()->has('quantity')) {
                    $remainingQuantity = max(
                        0,
                        $this->quantityInHundredths($harvest->quantity)
                        - $this->quantityInHundredths((string) $harvest->sales()->sum('quantity')),
                    );

                    if ($this->quantityInHundredths((string) $this->input('quantity')) > $remainingQuantity) {
                        $validator->errors()->add('quantity', __('The sale quantity may not exceed the remaining harvest quantity.'));
                    }
                }

                if (! $this->filled('investor_agreement_id') || $validator->errors()->has('investor_agreement_id')) {
                    return;
                }

                $agreement = InvestorAgreement::query()
                    ->where('team_id', $this->team()->id)
                    ->find($this->integer('investor_agreement_id'));

                if (
                    $agreement instanceof InvestorAgreement
                    && (
                        $agreement->farm_id !== $harvest->farm_id
                        || (
                            $agreement->production_cycle_id !== null
                            && $agreement->production_cycle_id !== $harvest->production_cycle_id
                        )
                    )
                ) {
                    $validator->errors()->add('investor_agreement_id', __('The investor agreement must belong to the harvest farm and production cycle.'));
                }
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function saleAttributes(): array
    {
        $validated = $this->validated();
        $grossAmountMinor = Money::toMinorUnit((string) $validated['gross_amount']);
        $deductionAmountMinor = Money::toMinorUnit((string) ($validated['deduction_amount'] ?? '0'));

        return [
            'harvest_record_id' => $validated['harvest_record_id'],
            'investor_agreement_id' => $validated['investor_agreement_id'] ?? null,
            'sold_on' => $validated['sold_on'],
            'buyer_name' => $validated['buyer_name'],
            'quantity' => $validated['quantity'],
            'quantity_unit' => $validated['quantity_unit'],
            'unit_price_minor' => Money::toMinorUnit((string) $validated['unit_price']),
            'gross_amount_minor' => $grossAmountMinor,
            'deduction_amount_minor' => $deductionAmountMinor,
            'net_amount_minor' => $grossAmountMinor - $deductionAmountMinor,
            'payment_status' => $validated['payment_status'],
            'reference' => $validated['reference'] ?? null,
            'investor_visibility_status' => $validated['investor_visibility_status'] ?? InvestorVisibilityStatus::Private->value,
            'notes' => $validated['notes'] ?? null,
        ];
    }

    public function evidenceCaption(): ?string
    {
        $caption = $this->validated('evidence_caption');

        return is_string($caption) ? $caption : null;
    }

    /**
     * @return array<int, UploadedFile>
     */
    public function evidenceFiles(): array
    {
        $files = $this->file('evidence', []);
        $files = is_array($files) ? $files : [$files];

        return array_values(array_filter(
            $files,
            fn (mixed $file): bool => $file instanceof UploadedFile,
        ));
    }

    private function team(): Team
    {
        $team = $this->route('current_team');

        assert($team instanceof Team);

        return $team;
    }

    private function harvestRule(): Exists
    {
        return Rule::exists((new HarvestRecord)->getTable(), 'id')->where('team_id', $this->team()->id);
    }

    private function investorAgreementRule(): Exists
    {
        return Rule::exists((new InvestorAgreement)->getTable(), 'id')->where('team_id', $this->team()->id);
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

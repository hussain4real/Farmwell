<?php

namespace App\Http\Requests\Harvests;

use App\Enums\HarvestRecordStatus;
use App\Enums\HarvestStage;
use App\Enums\InvestorVisibilityStatus;
use App\Models\Commodity;
use App\Models\Farm;
use App\Models\InvestorAgreement;
use App\Models\ProductionCycle;
use App\Models\ProductionUnit;
use App\Models\Team;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;

class StoreHarvestRecordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $team = $this->route('current_team');

        return $team instanceof Team
            && (
                $this->user()?->can('manageFarmOperations', $team) === true
                || $this->user()?->can('manageFinance', $team) === true
            );
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $farmId = $this->integer('farm_id');

        return [
            'farm_id' => ['required', 'integer', $this->farmRule()],
            'production_unit_id' => ['nullable', 'integer', $this->unitRule($farmId)],
            'production_cycle_id' => ['nullable', 'integer', $this->cycleRule($farmId)],
            'commodity_id' => ['required', 'integer', $this->commodityRule()],
            'investor_agreement_id' => ['nullable', 'integer', $this->investorAgreementRule($farmId)],
            'harvested_on' => ['required', 'date'],
            'stage' => ['required', Rule::enum(HarvestStage::class)],
            'sequence_number' => ['required', 'integer', 'min:1', 'max:999'],
            'quantity' => ['required', 'numeric', 'gt:0', 'regex:/^\d+(\.\d{1,2})?$/'],
            'quantity_unit' => ['required', 'string', 'max:32'],
            'quality_notes' => ['nullable', 'string', 'max:4000'],
            'labour_cost' => ['nullable', 'numeric', 'min:0', 'regex:/^\d+(\.\d{1,2})?$/'],
            'status' => ['sometimes', 'filled', Rule::enum(HarvestRecordStatus::class)],
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
                if (
                    ! $this->filled('investor_agreement_id')
                    || $validator->errors()->hasAny(['farm_id', 'production_cycle_id', 'investor_agreement_id'])
                ) {
                    return;
                }

                $agreement = InvestorAgreement::query()
                    ->where('team_id', $this->team()->id)
                    ->find($this->integer('investor_agreement_id'));
                $productionCycleId = $this->filled('production_cycle_id')
                    ? $this->integer('production_cycle_id')
                    : null;

                if (
                    $agreement instanceof InvestorAgreement
                    && $agreement->production_cycle_id !== null
                    && $agreement->production_cycle_id !== $productionCycleId
                ) {
                    $validator->errors()->add(
                        'investor_agreement_id',
                        __('The investor agreement must belong to the harvest farm and production cycle.'),
                    );
                }
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function harvestAttributes(): array
    {
        $validated = $this->validated();

        return [
            'farm_id' => $validated['farm_id'],
            'production_unit_id' => $validated['production_unit_id'] ?? null,
            'production_cycle_id' => $validated['production_cycle_id'] ?? null,
            'commodity_id' => $validated['commodity_id'],
            'investor_agreement_id' => $validated['investor_agreement_id'] ?? null,
            'harvested_on' => $validated['harvested_on'],
            'stage' => $validated['stage'],
            'sequence_number' => $validated['sequence_number'],
            'quantity' => $validated['quantity'],
            'quantity_unit' => $validated['quantity_unit'],
            'quality_notes' => $validated['quality_notes'] ?? null,
            'labour_cost_minor' => Money::toMinorUnit((string) ($validated['labour_cost'] ?? '0')),
            'status' => $validated['status'] ?? HarvestRecordStatus::Recorded->value,
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

    private function farmRule(): Exists
    {
        return Rule::exists((new Farm)->getTable(), 'id')->where('team_id', $this->team()->id);
    }

    private function unitRule(?int $farmId): Exists
    {
        $rule = Rule::exists((new ProductionUnit)->getTable(), 'id')
            ->where('team_id', $this->team()->id);

        if ($farmId !== null && $farmId > 0) {
            $rule->where('farm_id', $farmId);
        }

        return $rule;
    }

    private function cycleRule(?int $farmId): Exists
    {
        $rule = Rule::exists((new ProductionCycle)->getTable(), 'id')
            ->where('team_id', $this->team()->id);

        if ($farmId !== null && $farmId > 0) {
            $rule->where('farm_id', $farmId);
        }

        return $rule;
    }

    private function commodityRule(): Exists
    {
        return Rule::exists((new Commodity)->getTable(), 'id')->where('team_id', $this->team()->id);
    }

    private function investorAgreementRule(?int $farmId): Exists
    {
        $rule = Rule::exists((new InvestorAgreement)->getTable(), 'id')
            ->where('team_id', $this->team()->id);

        if ($farmId !== null && $farmId > 0) {
            $rule->where('farm_id', $farmId);
        }

        return $rule;
    }
}

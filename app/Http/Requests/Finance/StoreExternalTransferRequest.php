<?php

namespace App\Http\Requests\Finance;

use App\Enums\ExternalTransferDirection;
use App\Enums\ExternalTransferStatus;
use App\Http\Requests\Finance\Concerns\ResolvesFinanceRequestData;
use App\Models\Expense;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StoreExternalTransferRequest extends FormRequest
{
    use ResolvesFinanceRequestData;

    public function authorize(): bool
    {
        return $this->authorizeFinanceManagement();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $farmId = $this->integer('farm_id');

        return [
            'farm_id' => ['required', 'integer', $this->farmRule()],
            'production_cycle_id' => ['nullable', 'integer', $this->cycleRule($farmId)],
            'budget_id' => ['nullable', 'integer', $this->budgetRule($farmId)],
            'funding_phase_id' => ['nullable', 'integer', $this->fundingPhaseRule($farmId)],
            'expense_id' => [
                'nullable',
                'integer',
                Rule::exists((new Expense)->getTable(), 'id')
                    ->where('team_id', $this->team()->id)
                    ->where('farm_id', $farmId),
            ],
            'direction' => ['required', Rule::enum(ExternalTransferDirection::class)],
            'transfer_type' => ['required', 'string', 'max:255'],
            'status' => ['sometimes', 'filled', Rule::enum(ExternalTransferStatus::class)],
            'counterparty_name' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'transferred_on' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'proof_caption' => ['nullable', 'string', 'max:255'],
            'proof' => [
                'nullable',
                'file',
                File::types(['jpg', 'jpeg', 'png', 'webp', 'pdf', 'doc', 'docx'])
                    ->max(10 * 1024),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function transferAttributes(): array
    {
        $validated = $this->validated();

        return [
            'farm_id' => $validated['farm_id'],
            'production_cycle_id' => $validated['production_cycle_id'] ?? null,
            'budget_id' => $validated['budget_id'] ?? null,
            'funding_phase_id' => $validated['funding_phase_id'] ?? null,
            'expense_id' => $validated['expense_id'] ?? null,
            'direction' => $validated['direction'],
            'transfer_type' => $validated['transfer_type'],
            'status' => $validated['status'] ?? ExternalTransferStatus::Recorded->value,
            'counterparty_name' => $validated['counterparty_name'] ?? null,
            'reference' => $validated['reference'] ?? null,
            'amount_minor' => Money::toMinorUnit((string) $validated['amount']),
            'transferred_on' => $validated['transferred_on'],
            'notes' => $validated['notes'] ?? null,
        ];
    }

    public function proofCaption(): ?string
    {
        $caption = $this->validated('proof_caption');

        return is_string($caption) ? $caption : null;
    }

    public function proofFile(): ?UploadedFile
    {
        $file = $this->file('proof');

        return $file instanceof UploadedFile ? $file : null;
    }
}

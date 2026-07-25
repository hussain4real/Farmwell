<?php

namespace App\Http\Requests\Finance;

use App\Enums\TransferReconciliationStatus;
use App\Http\Requests\Finance\Concerns\ResolvesFinanceRequestData;
use App\Models\ExternalTransfer;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransferReconciliationRequest extends FormRequest
{
    use ResolvesFinanceRequestData;

    public function authorize(): bool
    {
        $transfer = $this->route('external_transfer');

        return $transfer instanceof ExternalTransfer
            && $transfer->team_id === $this->team()->id
            && $this->authorizeFinanceManagement();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(TransferReconciliationStatus::class)],
            'reconciled_amount' => ['required', 'numeric', 'min:0', 'regex:/^\d+(\.\d{1,2})?$/'],
            'reconciled_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function reconciliationAttributes(): array
    {
        $validated = $this->validated();

        return [
            'status' => $validated['status'],
            'reconciled_amount_minor' => Money::toMinorUnit((string) $validated['reconciled_amount']),
            'reconciled_at' => $validated['reconciled_at'] ?? now(),
            'notes' => $validated['notes'] ?? null,
        ];
    }
}

<?php

namespace App\Http\Requests\Finance;

use App\Enums\ExpenseStatus;
use App\Enums\InvestorVisibilityStatus;
use App\Http\Requests\Finance\Concerns\ResolvesFinanceRequestData;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StoreExpenseRequest extends FormRequest
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
        $budgetId = $this->integer('budget_id') ?: null;

        return [
            'farm_id' => ['required', 'integer', $this->farmRule()],
            'production_cycle_id' => ['nullable', 'integer', $this->cycleRule($farmId)],
            'budget_id' => ['nullable', 'integer', $this->budgetRule($farmId)],
            'budget_line_id' => ['nullable', 'integer', $this->budgetLineRule($budgetId)],
            'funding_phase_id' => ['nullable', 'integer', $this->fundingPhaseRule($farmId)],
            'investor_agreement_id' => ['nullable', 'integer', $this->investorAgreementRule($farmId)],
            'expense_category_id' => ['required', 'integer', $this->categoryRule()],
            'farm_activity_id' => ['nullable', 'integer', $this->activityRule($farmId)],
            'incurred_on' => ['required', 'date'],
            'vendor' => ['nullable', 'string', 'max:255'],
            'payment_method' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:4000'],
            'amount' => ['required', 'numeric', 'min:0'],
            'status' => ['sometimes', 'filled', Rule::enum(ExpenseStatus::class)],
            'investor_visibility_status' => ['sometimes', 'filled', Rule::enum(InvestorVisibilityStatus::class)],
            'notes' => ['nullable', 'string', 'max:4000'],
            'receipt_caption' => ['nullable', 'string', 'max:255'],
            'receipts' => ['nullable', 'array', 'max:5'],
            'receipts.*' => [
                'file',
                File::types(['jpg', 'jpeg', 'png', 'webp', 'pdf', 'doc', 'docx'])
                    ->max(10 * 1024),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function expenseAttributes(): array
    {
        $validated = $this->validated();

        return [
            'farm_id' => $validated['farm_id'],
            'production_cycle_id' => $validated['production_cycle_id'] ?? null,
            'budget_id' => $validated['budget_id'] ?? null,
            'budget_line_id' => $validated['budget_line_id'] ?? null,
            'funding_phase_id' => $validated['funding_phase_id'] ?? null,
            'investor_agreement_id' => $validated['investor_agreement_id'] ?? null,
            'expense_category_id' => $validated['expense_category_id'],
            'farm_activity_id' => $validated['farm_activity_id'] ?? null,
            'incurred_on' => $validated['incurred_on'],
            'vendor' => $validated['vendor'] ?? null,
            'payment_method' => $validated['payment_method'] ?? null,
            'description' => $validated['description'],
            'amount_minor' => Money::toMinorUnit((string) $validated['amount']),
            'status' => $validated['status'] ?? ExpenseStatus::Approved->value,
            'investor_visibility_status' => $validated['investor_visibility_status'] ?? InvestorVisibilityStatus::Private->value,
            'notes' => $validated['notes'] ?? null,
        ];
    }

    public function receiptCaption(): ?string
    {
        $caption = $this->validated('receipt_caption');

        return is_string($caption) ? $caption : null;
    }

    /**
     * @return array<int, UploadedFile>
     */
    public function receiptFiles(): array
    {
        $files = $this->file('receipts', []);
        $files = is_array($files) ? $files : [$files];

        return array_values(array_filter(
            $files,
            fn (mixed $file): bool => $file instanceof UploadedFile,
        ));
    }
}

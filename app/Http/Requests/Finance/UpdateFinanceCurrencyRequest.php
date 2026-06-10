<?php

namespace App\Http\Requests\Finance;

use App\Http\Requests\Finance\Concerns\ResolvesFinanceRequestData;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFinanceCurrencyRequest extends FormRequest
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
        return [
            'currency' => ['required', 'string', 'size:3', 'uppercase'],
        ];
    }

    public function currency(): string
    {
        return strtoupper((string) $this->validated('currency'));
    }
}

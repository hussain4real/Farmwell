<?php

namespace App\Http\Requests\Harvests;

use App\Enums\SalePaymentStatus;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQuickEggSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $team = $this->route('current_team');

        return $team instanceof Team && $this->user()?->can('manageFinance', $team) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sold_on' => ['required', 'date'],
            'crates_sold' => ['required', 'numeric', 'gt:0'],
            'price_per_crate' => ['required', 'numeric', 'min:0'],
            'buyer_name' => ['required', 'string', 'max:255'],
            'payment_status' => ['required', Rule::enum(SalePaymentStatus::class)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

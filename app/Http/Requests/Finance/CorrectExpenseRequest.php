<?php

namespace App\Http\Requests\Finance;

use App\Actions\Finance\CorrectExpense;
use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;

class CorrectExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $team = $this->route('current_team');

        return $team instanceof Team && $this->user()?->can('manageFinance', $team) === true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return CorrectExpense::rules();
    }
}

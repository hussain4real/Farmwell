<?php

namespace App\Http\Requests\Finance;

use App\Models\FundingPhase;
use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;

class RecordFundingReleaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $team = $this->route('current_team');
        $phase = $this->route('funding_phase');

        return $team instanceof Team && $phase instanceof FundingPhase
            && $phase->team_id === $team->id && $this->user()?->can('manageFinance', $team) === true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0', 'regex:/^\d+(\.\d{1,2})?$/'],
            'released_on' => ['required', 'date'],
        ];
    }
}

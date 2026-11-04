<?php

namespace App\Http\Requests\Finance;

use App\Models\FundingPhase;
use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class SubmitFundingRequest extends FormRequest
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
            'milestone_evidence' => ['nullable', 'array', 'max:5'],
            'milestone_evidence.*' => ['file', File::types(['jpg', 'jpeg', 'png', 'webp', 'pdf', 'doc', 'docx'])->max(10 * 1024)],
        ];
    }
}

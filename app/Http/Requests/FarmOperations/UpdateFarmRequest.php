<?php

namespace App\Http\Requests\FarmOperations;

use App\Enums\FarmType;
use App\Models\Farm;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFarmRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $team = $this->route('current_team');
        $farm = $this->route('farm');

        return $team instanceof Team
            && $farm instanceof Farm
            && $farm->team_id === $team->id
            && $this->user()?->can('manageFarmOperations', $team) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $team = $this->route('current_team');
        $farm = $this->route('farm');
        assert($team instanceof Team && $farm instanceof Farm);

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('farms')->where('team_id', $team->id)->ignore($farm->id),
            ],
            'farm_type' => ['required', Rule::enum(FarmType::class)],
            'location' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string', Rule::in(['active', 'inactive', 'archived'])],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

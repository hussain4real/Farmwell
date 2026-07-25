<?php

namespace App\Http\Requests\FarmOperations;

use App\Enums\FarmType;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFarmRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $team = $this->route('current_team');

        return $team instanceof Team && $this->user()?->can('manageFarmOperations', $team) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $team = $this->team();

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('farms')->where('team_id', $team->id),
            ],
            'farm_type' => ['required', Rule::enum(FarmType::class)],
            'location' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'filled', 'string', 'max:50'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function team(): Team
    {
        $team = $this->route('current_team');

        assert($team instanceof Team);

        return $team;
    }
}

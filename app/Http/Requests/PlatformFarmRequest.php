<?php

namespace App\Http\Requests;

use App\Enums\FarmType;
use App\Models\Farm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlatformFarmRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('managePlatform') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $farm = $this->route('farm');
        $teamId = $farm instanceof Farm ? $farm->team_id : $this->input('team_id');

        return [
            'team_id' => $farm instanceof Farm ? ['prohibited'] : ['required', 'integer', Rule::exists('teams', 'id')->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:255', Rule::unique('farms')->where('team_id', $teamId)->ignore($farm instanceof Farm ? $farm : null)],
            'farm_type' => ['required', Rule::enum(FarmType::class)],
            'location' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}

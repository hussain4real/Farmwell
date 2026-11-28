<?php

namespace App\Http\Requests\Teams;

use App\Actions\Teams\ResolveInvestorMode;
use App\Actions\Teams\ResolveWorkspacePlan;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateTeamSettingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $team = $this->route('team');

        if (! $team instanceof Team || $this->user()?->can('manageSettings', $team) !== true) {
            return false;
        }

        if (
            $this->route('settingGroup') === ResolveWorkspacePlan::SettingGroup
            && $this->route('settingKey') === ResolveWorkspacePlan::SettingKey
        ) {
            return false;
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'value' => ['required', 'array'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Validate plan eligibility when updating workspace feature flags.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $team = $this->route('team');

                if (
                    $team instanceof Team
                    && $this->route('settingGroup') === ResolveInvestorMode::SettingGroup
                    && $this->route('settingKey') === ResolveInvestorMode::SettingKey
                    && ! empty($this->input('value.enabled'))
                    && ! app(ResolveWorkspacePlan::class)->handle($team)->allowsInvestorMode()
                ) {
                    $validator->errors()->add('value', __('Investor Transparency Mode requires the Investor / Pilot plan.'));
                }
            },
        ];
    }

    /**
     * Get the validated setting value.
     *
     * @return array<string, mixed>
     */
    public function settingValue(): array
    {
        /** @var array<string, mixed> $value */
        $value = $this->validated('value');

        return $value;
    }

    /**
     * Get the validated change reason.
     */
    public function reason(): ?string
    {
        $reason = $this->validated('reason');

        return is_string($reason) ? $reason : null;
    }
}

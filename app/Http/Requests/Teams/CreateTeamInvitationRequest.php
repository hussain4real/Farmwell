<?php

namespace App\Http\Requests\Teams;

use App\Actions\Teams\ResolveWorkspacePlan;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Rules\UniqueTeamInvitation;
use App\Services\InfobipVerification;
use App\Support\PilotPhone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CreateTeamInvitationRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['bail', 'required_without:phone', 'prohibits:phone', 'nullable', 'string', 'email', 'max:255', new UniqueTeamInvitation($this->route('team'))],
            'phone' => ['bail', 'nullable', Rule::prohibitedIf(! InfobipVerification::enabled()), 'string',
                config('services.phone_login.pilot_host') ? 'regex:'.PilotPhone::NumberPattern : Rule::in([config('services.phone_login.allowed_number')]), new UniqueTeamInvitation($this->route('team'))],
            'role' => ['required', 'string', Rule::in(array_column(TeamRole::assignable(), 'value'))],
        ];
    }

    /**
     * Enforce workspace plan limits on invitations.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $team = $this->route('team');
                assert($team instanceof Team);

                $plans = app(ResolveWorkspacePlan::class);
                $plan = $plans->handle($team);

                if ($this->filled('phone') && ! $plan->allowsPhoneInvitations()) {
                    $validator->errors()->add('phone', __('SMS phone invitations require the Pro Farm or Investor plan.'));
                }

                if ($this->input('role') === TeamRole::Investor->value && ! $plan->allowsInvestorMode()) {
                    $validator->errors()->add('role', __('Inviting investors requires the Investor / Pilot plan.'));
                }

                if (! $plans->canAddMember($team)) {
                    $validator->errors()->add(
                        $this->filled('phone') ? 'phone' : 'email',
                        __('Your Starter plan includes up to 2 team members. Upgrade to Pro Farm to invite more workers.'),
                    );
                }
            },
        ];
    }
}

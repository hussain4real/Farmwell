<?php

namespace App\Http\Requests\Assistant;

use App\Actions\Teams\ResolveWorkspacePlan;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SendAssistantMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $team = $this->route('current_team');

        return $team instanceof Team
            && ! $this->attributes->has('platform_access_team')
            && app(ResolveWorkspacePlan::class)->handle($team)->allowsAssistant();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:2000'],
            'conversation_id' => ['nullable', 'uuid'],
        ];
    }
}

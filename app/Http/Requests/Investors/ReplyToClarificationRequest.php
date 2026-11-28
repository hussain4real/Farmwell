<?php

namespace App\Http\Requests\Investors;

use App\Actions\Investors\ReplyToClarification;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class ReplyToClarificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $team = $this->route('current_team');

        return $team instanceof Team && $this->user() instanceof User && ReplyToClarification::canReply($this->user(), $team);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ReplyToClarification::rules();
    }
}

<?php

namespace App\Http\Requests\FarmOperations;

use App\Enums\FarmActivityStatus;
use App\Models\Commodity;
use App\Models\Farm;
use App\Models\ProductionCycle;
use App\Models\ProductionUnit;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StoreFarmActivityRequest extends FormRequest
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
        $farmId = $this->integer('farm_id');

        return [
            'farm_id' => [
                'required',
                'integer',
                Rule::exists((new Farm)->getTable(), 'id')->where('team_id', $team->id),
            ],
            'production_unit_id' => [
                'nullable',
                'integer',
                Rule::exists((new ProductionUnit)->getTable(), 'id')
                    ->where('team_id', $team->id)
                    ->where('farm_id', $farmId),
            ],
            'production_cycle_id' => [
                'nullable',
                'integer',
                Rule::exists((new ProductionCycle)->getTable(), 'id')
                    ->where('team_id', $team->id)
                    ->where('farm_id', $farmId),
            ],
            'commodity_id' => [
                'nullable',
                'integer',
                Rule::exists((new Commodity)->getTable(), 'id')->where('team_id', $team->id),
            ],
            'activity_date' => ['required', 'date'],
            'activity_type' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:4000'],
            'inputs_used' => ['nullable', 'string', 'max:2000'],
            'labour_used' => ['nullable', 'string', 'max:2000'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'next_activity' => ['nullable', 'string', 'max:2000'],
            'status' => ['sometimes', 'filled', Rule::enum(FarmActivityStatus::class)],
            'internal_notes' => ['nullable', 'string', 'max:4000'],
            'investor_safe_summary' => ['nullable', 'string', 'max:2000'],
            'evidence_caption' => ['nullable', 'string', 'max:255'],
            'evidence_visibility' => ['nullable', Rule::in(['private', 'investor_visible'])],
            'evidence' => ['nullable', 'array', 'max:5'],
            'evidence.*' => [
                'file',
                File::types(['jpg', 'jpeg', 'png', 'webp', 'pdf', 'doc', 'docx'])
                    ->max(10 * 1024),
            ],
        ];
    }

    public function team(): Team
    {
        $team = $this->route('current_team');

        assert($team instanceof Team);

        return $team;
    }

    /**
     * Get activity attributes without upload-only fields.
     *
     * @return array<string, mixed>
     */
    public function activityAttributes(): array
    {
        return collect($this->validated())
            ->except(['evidence', 'evidence_caption', 'evidence_visibility'])
            ->toArray();
    }

    public function evidenceCaption(): ?string
    {
        $caption = $this->validated('evidence_caption');

        return is_string($caption) ? $caption : null;
    }

    public function evidenceVisibility(): string
    {
        $visibility = $this->validated('evidence_visibility');

        return is_string($visibility) ? $visibility : 'private';
    }

    /**
     * Get uploaded evidence files.
     *
     * @return array<int, UploadedFile>
     */
    public function evidenceFiles(): array
    {
        $files = $this->file('evidence', []);
        $files = is_array($files) ? $files : [$files];

        return array_values(array_filter(
            $files,
            fn (mixed $file): bool => $file instanceof UploadedFile,
        ));
    }
}

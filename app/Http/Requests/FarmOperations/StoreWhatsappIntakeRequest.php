<?php

namespace App\Http\Requests\FarmOperations;

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

class StoreWhatsappIntakeRequest extends FormRequest
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
                'nullable',
                'required_with:production_unit_id,production_cycle_id',
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
            'source_message' => ['required', 'string', 'max:8000'],
            'source_sender' => ['nullable', 'string', 'max:255'],
            'source_date' => ['nullable', 'date'],
            'normalized_activity_date' => ['nullable', 'date'],
            'normalized_activity_type' => ['nullable', 'string', 'max:100'],
            'normalized_description' => ['nullable', 'string', 'max:4000'],
            'normalized_cost' => ['nullable', 'numeric', 'min:0'],
            'normalized_next_activity' => ['nullable', 'string', 'max:2000'],
            'normalized_investor_safe_summary' => ['nullable', 'string', 'max:2000'],
            'evidence_caption' => ['nullable', 'string', 'max:255'],
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
     * Get intake attributes without upload-only fields.
     *
     * @return array<string, mixed>
     */
    public function intakeAttributes(): array
    {
        return collect($this->validated())
            ->except(['evidence', 'evidence_caption'])
            ->toArray();
    }

    public function evidenceCaption(): ?string
    {
        $caption = $this->validated('evidence_caption');

        return is_string($caption) ? $caption : null;
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

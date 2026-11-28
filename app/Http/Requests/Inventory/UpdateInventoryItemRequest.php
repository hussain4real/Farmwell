<?php

namespace App\Http\Requests\Inventory;

use App\Enums\InventoryCategory;
use App\Models\Farm;
use App\Models\InventoryItem;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInventoryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $team = $this->route('current_team');
        $item = $this->route('inventory_item');

        return $team instanceof Team
            && $item instanceof InventoryItem
            && $item->team_id === $team->id
            && $this->user()?->can('manageFarmOperations', $team) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $team = $this->route('current_team');
        assert($team instanceof Team);

        return [
            'farm_id' => [
                'nullable',
                'integer',
                Rule::exists((new Farm)->getTable(), 'id')->where('team_id', $team->id),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'category' => ['sometimes', 'required', Rule::enum(InventoryCategory::class)],
            'sku' => ['nullable', 'string', 'max:100'],
            'unit' => ['sometimes', 'required', 'string', 'max:50'],
            'unit_weight_kg' => ['nullable', 'numeric', 'min:0.01'],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'supplier_name' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}

<?php

namespace App\Ai\Tools;

use App\Actions\Finance\ResolveTeamFinanceCurrency;
use App\Ai\Tools\Concerns\InteractsWithFarmwellContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class AskUserInputTool implements Tool
{
    use InteractsWithFarmwellContext;

    /**
     * @var list<string>
     */
    private const ALLOWED_TYPES = [
        'text',
        'currency',
        'number',
        'date',
        'select',
        'radio',
        'multiselect',
        'textarea',
    ];

    public function description(): string
    {
        return 'Display an interactive rich form card (radio cards, select menus, multi-select pills, currency, number, date, or text fields) to collect missing required details from the user before executing a task.';
    }

    public function handle(Request $request): string
    {
        $title = trim((string) ($request['title'] ?? 'Provide required details'));
        $description = $request->filled('description') ? trim((string) $request['description']) : null;
        $submitLabel = $request->filled('submit_label') ? trim((string) $request['submit_label']) : 'Continue';
        $rawFields = $request['fields'] ?? [];

        $fields = [];
        if (is_array($rawFields)) {
            foreach ($rawFields as $rawField) {
                if (! is_array($rawField)) {
                    continue;
                }

                $name = trim((string) ($rawField['name'] ?? ''));
                $label = trim((string) ($rawField['label'] ?? ''));

                if ($name === '' || $label === '') {
                    continue;
                }

                $type = (string) ($rawField['type'] ?? 'text');
                if (! in_array($type, self::ALLOWED_TYPES, true)) {
                    $type = 'text';
                }

                $options = [];
                if (is_array($rawField['options'] ?? null)) {
                    foreach ($rawField['options'] as $rawOption) {
                        if (! is_array($rawOption)) {
                            continue;
                        }

                        $optValue = trim((string) ($rawOption['value'] ?? ''));
                        $optLabel = trim((string) ($rawOption['label'] ?? ''));
                        if ($optValue === '' || $optLabel === '') {
                            continue;
                        }

                        $option = [
                            'value' => $optValue,
                            'label' => $optLabel,
                        ];

                        if (isset($rawOption['hint']) && trim((string) $rawOption['hint']) !== '') {
                            $option['hint'] = trim((string) $rawOption['hint']);
                        }

                        $options[] = $option;
                    }
                }

                $field = [
                    'name' => $name,
                    'label' => $label,
                    'type' => $type,
                    'required' => array_key_exists('required', $rawField) ? (bool) $rawField['required'] : true,
                ];

                foreach (['placeholder', 'hint', 'default_value'] as $optionalTextKey) {
                    if (isset($rawField[$optionalTextKey]) && trim((string) $rawField[$optionalTextKey]) !== '') {
                        $field[$optionalTextKey] = trim((string) $rawField[$optionalTextKey]);
                    }
                }

                if ($options !== []) {
                    $field['options'] = $options;
                }

                $fields[] = $field;
            }
        }

        return $this->encodeResult([
            'ui_type' => 'input_form',
            'awaiting_user_input' => true,
            'title' => $title !== '' ? $title : 'Provide required details',
            'description' => $description,
            'submit_label' => $submitLabel !== '' ? $submitLabel : 'Continue',
            'currency' => app(ResolveTeamFinanceCurrency::class)->handle($this->team),
            'fields' => $fields,
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->description('Concise form heading shown on the card.')->required(),
            'description' => $schema->string()->description('Optional brief context, such as matched farm and agreement.'),
            'submit_label' => $schema->string()->description('Action button label, e.g. Review funding request or Prepare expense.'),
            'fields' => $schema->array()->items(
                $schema->object([
                    'name' => $schema->string()->description('Machine field key matching the target tool argument.')->required(),
                    'label' => $schema->string()->description('Clear human-readable label.')->required(),
                    'type' => $schema->string()->enum(self::ALLOWED_TYPES)->required(),
                    'required' => $schema->boolean()->description('True for required inputs, false for optional inputs.'),
                    'placeholder' => $schema->string(),
                    'hint' => $schema->string(),
                    'default_value' => $schema->string(),
                    'options' => $schema->array()->items(
                        $schema->object([
                            'value' => $schema->string()->required(),
                            'label' => $schema->string()->required(),
                            'hint' => $schema->string(),
                        ])->withoutAdditionalProperties(),
                    )->description('Required for select, radio, and multiselect fields.'),
                ])->withoutAdditionalProperties(),
            )->min(1)->required(),
        ];
    }
}

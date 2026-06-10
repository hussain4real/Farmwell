<?php

namespace App\Actions\Finance;

use App\Models\ExpenseCategory;
use App\Models\Team;
use Illuminate\Support\Str;

class EnsureDefaultExpenseCategories
{
    /**
     * @var list<string>
     */
    public const Defaults = [
        'tools',
        'seeds/stock',
        'land preparation',
        'herbicides',
        'planting',
        'labour',
        'feeding',
        'health care',
        'fertilizers',
        'pest control',
        'harvest/output handling',
        'transport',
        'contingency',
    ];

    public function handle(Team $team): void
    {
        foreach (self::Defaults as $index => $name) {
            ExpenseCategory::query()->firstOrCreate(
                [
                    'team_id' => $team->id,
                    'slug' => Str::slug($name),
                ],
                [
                    'name' => Str::title($name),
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ],
            );
        }
    }
}

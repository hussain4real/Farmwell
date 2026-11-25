<?php

namespace App\Enums;

enum InventoryCategory: string
{
    case Feed = 'feed';
    case Seed = 'seed';
    case Fertilizer = 'fertilizer';
    case Agrochemical = 'agrochemical';
    case Medication = 'medication';
    case Vaccine = 'vaccine';
    case Packaging = 'packaging';
    case Fuel = 'fuel';
    case Equipment = 'equipment';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Feed => 'Feed',
            self::Seed => 'Seeds & Stock',
            self::Fertilizer => 'Fertilizer',
            self::Agrochemical => 'Herbicides & Pesticides',
            self::Medication => 'Medication',
            self::Vaccine => 'Vaccines',
            self::Packaging => 'Packaging & Crates',
            self::Fuel => 'Fuel & Energy',
            self::Equipment => 'Tools & Equipment',
            self::Other => 'Other',
        };
    }

    public function expenseCategorySlug(): string
    {
        return match ($this) {
            self::Feed => 'feeding',
            self::Seed => 'seeds-stock',
            self::Fertilizer => 'fertilizers',
            self::Agrochemical => 'pest-control',
            self::Medication, self::Vaccine => 'health-care',
            self::Packaging => 'harvest-output-handling',
            self::Equipment => 'tools',
            self::Fuel, self::Other => 'contingency',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $category): array => ['value' => $category->value, 'label' => $category->label()],
            self::cases(),
        );
    }
}

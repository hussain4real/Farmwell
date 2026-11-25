<?php

namespace App\Enums;

enum HealthEventType: string
{
    case Vaccination = 'vaccination';
    case Medication = 'medication';
    case Deworming = 'deworming';
    case Biosecurity = 'biosecurity';
    case WaterTreatment = 'water_treatment';
    case CropProtection = 'crop_protection';
    case VetVisit = 'vet_visit';
    case MortalityInvestigation = 'mortality_investigation';

    public function label(): string
    {
        return match ($this) {
            self::Vaccination => 'Vaccination',
            self::Medication => 'Medication',
            self::Deworming => 'Deworming',
            self::Biosecurity => 'Biosecurity / Disinfection',
            self::WaterTreatment => 'Water Treatment / Prophylaxis',
            self::CropProtection => 'Crop Spraying / Protection',
            self::VetVisit => 'Vet / Agronomist Visit',
            self::MortalityInvestigation => 'Mortality Investigation',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $type): array => ['value' => $type->value, 'label' => $type->label()],
            self::cases(),
        );
    }
}

<?php

namespace App\Enums;

enum AiUsageFeature: string
{
    case Assistant = 'assistant';
    case SummaryInvestorProgress = 'summary_investor_progress';
    case SummaryWeekly = 'summary_weekly';
    case SummaryRiskDecision = 'summary_risk_decision';
    case SummaryIntakeCleanup = 'summary_intake_cleanup';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Assistant => 'Farmwell Assistant',
            self::SummaryInvestorProgress => 'Investor Progress Summary',
            self::SummaryWeekly => 'Weekly Progress Summary',
            self::SummaryRiskDecision => 'Risk & Decision Summary',
            self::SummaryIntakeCleanup => 'WhatsApp Intake Cleanup',
            self::Other => 'Other AI Call',
        };
    }

    public static function fromSummaryType(string $type): self
    {
        return match ($type) {
            'investor_progress' => self::SummaryInvestorProgress,
            'weekly' => self::SummaryWeekly,
            'risk_decision' => self::SummaryRiskDecision,
            'intake_cleanup' => self::SummaryIntakeCleanup,
            default => self::Other,
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $feature): array => [
                'value' => $feature->value,
                'label' => $feature->label(),
            ],
            self::cases(),
        );
    }
}

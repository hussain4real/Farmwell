<?php

namespace App\Actions\Investors;

use App\Models\Team;
use App\Support\Money;

class ResolveInvestorApprovalSettings
{
    public const DefaultCurrency = 'NGN';

    public const DefaultExpenseThresholdMinor = 50_000_000;

    /**
     * @return array{currency: string, expenseThresholdMinor: int, expenseThreshold: string}
     */
    public function handle(Team $team): array
    {
        $setting = $team->settings()
            ->where('setting_group', 'approvals')
            ->where('setting_key', 'investor_expense_threshold')
            ->first();

        $value = $setting?->value ?? [];
        $currency = Money::normalizeCurrency(
            is_string($value['currency'] ?? null) ? $value['currency'] : null,
            self::DefaultCurrency,
        );
        $thresholdMinor = (int) ($value['threshold_amount_minor'] ?? self::DefaultExpenseThresholdMinor);

        return [
            'currency' => $currency,
            'expenseThresholdMinor' => $thresholdMinor,
            'expenseThreshold' => Money::toDecimal($thresholdMinor),
        ];
    }
}

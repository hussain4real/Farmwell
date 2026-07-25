<?php

namespace App\Http\Controllers\Investors;

use App\Actions\Finance\ResolveTeamFinanceCurrency;
use App\Actions\Investors\UpdateExpenseCategoryInvestorApproval;
use App\Actions\Investors\UpdateInvestorApprovalSettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Investors\UpdateInvestorApprovalSettingsRequest;
use App\Models\ExpenseCategory;
use App\Models\Team;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class InvestorApprovalSettingController extends Controller
{
    public function update(
        UpdateInvestorApprovalSettingsRequest $request,
        Team $currentTeam,
        ResolveTeamFinanceCurrency $resolveTeamFinanceCurrency,
        UpdateInvestorApprovalSettings $updateInvestorApprovalSettings,
        UpdateExpenseCategoryInvestorApproval $updateExpenseCategoryInvestorApproval,
    ): RedirectResponse {
        $user = $request->user();
        assert($user instanceof User);

        $validated = $request->validated();
        $currency = Money::normalizeCurrency(
            is_string($validated['currency'] ?? null) ? $validated['currency'] : null,
            $resolveTeamFinanceCurrency->handle($currentTeam),
        );
        $reason = is_string($validated['reason'] ?? null) ? $validated['reason'] : null;

        $updateInvestorApprovalSettings->handle(
            team: $currentTeam,
            actor: $user,
            currency: $currency,
            thresholdAmountMinor: Money::toMinorUnit((string) $validated['threshold_amount']),
            reason: $reason,
        );

        $requiredCategoryIds = collect($validated['required_expense_category_ids'] ?? [])
            ->map(fn (mixed $id): int => (int) $id)
            ->values();

        $currentTeam->expenseCategories()
            ->get()
            ->each(fn (ExpenseCategory $category) => $updateExpenseCategoryInvestorApproval->handle(
                team: $currentTeam,
                category: $category,
                actor: $user,
                requiresApproval: $requiredCategoryIds->contains($category->id),
                reason: $reason,
            ));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Investor approval settings updated.')]);

        return to_route('investors.index', ['current_team' => $currentTeam]);
    }
}

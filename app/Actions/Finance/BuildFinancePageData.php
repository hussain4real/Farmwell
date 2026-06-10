<?php

namespace App\Actions\Finance;

use App\Enums\BudgetStatus;
use App\Enums\ExpenseStatus;
use App\Enums\ExternalTransferDirection;
use App\Enums\ExternalTransferStatus;
use App\Enums\FundingPhaseStatus;
use App\Enums\TransferReconciliationStatus;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExternalTransfer;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FundingPhase;
use App\Models\Team;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Collection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class BuildFinancePageData
{
    public function __construct(
        private CalculateBudgetVariance $calculateBudgetVariance,
        private CalculateCarryForwardBalance $calculateCarryForwardBalance,
        private EnsureDefaultExpenseCategories $ensureDefaultExpenseCategories,
        private ResolveTeamFinanceCurrency $resolveTeamFinanceCurrency,
    ) {
        //
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(Team $team, User $user): array
    {
        $this->ensureDefaultExpenseCategories->handle($team);

        return [
            'permissions' => $user->toTeamPermissions($team),
            'currency' => $this->resolveTeamFinanceCurrency->handle($team),
            'summary' => $this->summary($team),
            'variance' => $this->calculateBudgetVariance->handle($team),
            'carryForward' => $this->calculateCarryForwardBalance->handle($team),
            'recentExpenses' => $this->latestExpenses($team, 6)->map(fn (Expense $expense) => $this->expensePayload($expense, $team)),
            'recentTransfers' => $this->latestExternalTransfers($team, 6)->map(fn (ExternalTransfer $transfer) => $this->externalTransferPayload($transfer, $team)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function budgets(Team $team, User $user): array
    {
        $this->ensureDefaultExpenseCategories->handle($team);

        return [
            'permissions' => $user->toTeamPermissions($team),
            'currency' => $this->resolveTeamFinanceCurrency->handle($team),
            'farms' => $this->farms($team)->map(fn (Farm $farm) => $this->farmPayload($farm)),
            'categories' => $this->categories($team)->map(fn (ExpenseCategory $category) => $this->categoryPayload($category)),
            'budgets' => $this->budgetsQuery($team)->map(fn (Budget $budget) => $this->budgetPayload($budget)),
            'options' => $this->options(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function fundingPhases(Team $team, User $user): array
    {
        return [
            'permissions' => $user->toTeamPermissions($team),
            'currency' => $this->resolveTeamFinanceCurrency->handle($team),
            'farms' => $this->farms($team)->map(fn (Farm $farm) => $this->farmPayload($farm)),
            'budgets' => $this->budgetsQuery($team)->map(fn (Budget $budget) => $this->budgetSummaryPayload($budget)),
            'fundingPhases' => $this->fundingPhasesQuery($team)->map(fn (FundingPhase $phase) => $this->fundingPhasePayload($phase)),
            'carryForward' => $this->calculateCarryForwardBalance->handle($team),
            'options' => $this->options(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function expenses(Team $team, User $user): array
    {
        $this->ensureDefaultExpenseCategories->handle($team);

        return [
            'permissions' => $user->toTeamPermissions($team),
            'currency' => $this->resolveTeamFinanceCurrency->handle($team),
            'farms' => $this->farms($team)->map(fn (Farm $farm) => $this->farmPayload($farm)),
            'categories' => $this->categories($team)->map(fn (ExpenseCategory $category) => $this->categoryPayload($category)),
            'budgets' => $this->budgetsQuery($team)->map(fn (Budget $budget) => $this->budgetSummaryPayload($budget)),
            'budgetLines' => $this->budgetLines($team)->map(fn (BudgetLine $line) => $this->budgetLinePayload($line)),
            'fundingPhases' => $this->fundingPhasesQuery($team)->map(fn (FundingPhase $phase) => $this->fundingPhaseSummaryPayload($phase)),
            'activities' => $this->activities($team)->map(fn (FarmActivity $activity) => $this->activityPayload($activity)),
            'expenses' => $this->latestExpenses($team, 25)->map(fn (Expense $expense) => $this->expensePayload($expense, $team)),
            'variance' => $this->calculateBudgetVariance->handle($team),
            'options' => $this->options(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function externalTransfers(Team $team, User $user): array
    {
        return [
            'permissions' => $user->toTeamPermissions($team),
            'currency' => $this->resolveTeamFinanceCurrency->handle($team),
            'farms' => $this->farms($team)->map(fn (Farm $farm) => $this->farmPayload($farm)),
            'budgets' => $this->budgetsQuery($team)->map(fn (Budget $budget) => $this->budgetSummaryPayload($budget)),
            'fundingPhases' => $this->fundingPhasesQuery($team)->map(fn (FundingPhase $phase) => $this->fundingPhaseSummaryPayload($phase)),
            'expenses' => $this->latestExpenses($team, 25)->map(fn (Expense $expense) => $this->expenseSummaryPayload($expense)),
            'externalTransfers' => $this->latestExternalTransfers($team, 25)->map(fn (ExternalTransfer $transfer) => $this->externalTransferPayload($transfer, $team)),
            'options' => $this->options(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboardSummary(Team $team): array
    {
        return [
            ...$this->summary($team),
            'variance' => $this->calculateBudgetVariance->handle($team),
            'carryForward' => $this->calculateCarryForwardBalance->handle($team),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Team $team): array
    {
        $budgetedMinor = (int) $team->budgetLines()->sum('planned_amount_minor');
        $spentMinor = (int) $team->expenses()->whereIn('status', ExpenseStatus::spendableValues())->sum('amount_minor');
        $releasedMinor = (int) $team->fundingPhases()->sum('externally_released_amount_minor');
        $transfersMinor = (int) $team->externalTransfers()->sum('amount_minor');

        return [
            'budgets' => $team->budgets()->count(),
            'fundingPhases' => $team->fundingPhases()->count(),
            'expenses' => $team->expenses()->count(),
            'externalTransfers' => $team->externalTransfers()->count(),
            'budgetedMinor' => $budgetedMinor,
            'spentMinor' => $spentMinor,
            'releasedMinor' => $releasedMinor,
            'transfersMinor' => $transfersMinor,
            'budgeted' => Money::toDecimal($budgetedMinor),
            'spent' => Money::toDecimal($spentMinor),
            'released' => Money::toDecimal($releasedMinor),
            'transfers' => Money::toDecimal($transfersMinor),
        ];
    }

    /**
     * @return Collection<int, Farm>
     */
    private function farms(Team $team): Collection
    {
        return $team->farms()
            ->with(['productionCycles' => fn ($query) => $query->latest()])
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, ExpenseCategory>
     */
    private function categories(Team $team): Collection
    {
        return $team->expenseCategories()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Budget>
     */
    private function budgetsQuery(Team $team): Collection
    {
        return $team->budgets()
            ->with(['farm', 'productionCycle', 'lines.expenseCategory'])
            ->latest()
            ->get();
    }

    /**
     * @return Collection<int, BudgetLine>
     */
    private function budgetLines(Team $team): Collection
    {
        return $team->budgetLines()
            ->with(['budget', 'expenseCategory'])
            ->orderBy('sort_order')
            ->latest()
            ->get();
    }

    /**
     * @return Collection<int, FundingPhase>
     */
    private function fundingPhasesQuery(Team $team): Collection
    {
        return $team->fundingPhases()
            ->with(['farm', 'productionCycle', 'budget'])
            ->orderBy('expected_on')
            ->latest()
            ->get();
    }

    /**
     * @return Collection<int, Expense>
     */
    private function latestExpenses(Team $team, int $limit): Collection
    {
        return $team->expenses()
            ->with(['farm', 'productionCycle', 'expenseCategory', 'budget', 'fundingPhase', 'farmActivity', 'media'])
            ->latest('incurred_on')
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, ExternalTransfer>
     */
    private function latestExternalTransfers(Team $team, int $limit): Collection
    {
        return $team->externalTransfers()
            ->with(['farm', 'productionCycle', 'budget', 'fundingPhase', 'expense', 'reconciliations', 'media'])
            ->latest('transferred_on')
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, FarmActivity>
     */
    private function activities(Team $team): Collection
    {
        return $team->farmActivities()
            ->with(['farm', 'productionCycle'])
            ->latest('activity_date')
            ->limit(25)
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function farmPayload(Farm $farm): array
    {
        return [
            'id' => $farm->id,
            'name' => $farm->name,
            'productionCycles' => $farm->productionCycles->map(fn ($cycle) => [
                'id' => $cycle->id,
                'name' => $cycle->name,
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function categoryPayload(ExpenseCategory $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function budgetPayload(Budget $budget): array
    {
        return [
            ...$this->budgetSummaryPayload($budget),
            'periodStartOn' => $budget->period_start_on?->toDateString(),
            'periodEndOn' => $budget->period_end_on?->toDateString(),
            'notes' => $budget->notes,
            'lines' => $budget->lines->map(fn (BudgetLine $line) => $this->budgetLinePayload($line)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function budgetSummaryPayload(Budget $budget): array
    {
        return [
            'id' => $budget->id,
            'name' => $budget->name,
            'farmId' => $budget->farm_id,
            'farmName' => $budget->farm->name,
            'productionCycleId' => $budget->production_cycle_id,
            'productionCycleName' => $budget->productionCycle?->name,
            'status' => $budget->status->value,
            'statusLabel' => $budget->status->label(),
            'currency' => $budget->currency,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function budgetLinePayload(BudgetLine $line): array
    {
        return [
            'id' => $line->id,
            'budgetId' => $line->budget_id,
            'budgetName' => $line->budget->name,
            'expenseCategoryId' => $line->expense_category_id,
            'expenseCategoryName' => $line->expenseCategory?->name,
            'description' => $line->description,
            'plannedAmountMinor' => $line->planned_amount_minor,
            'plannedAmount' => Money::toDecimal($line->planned_amount_minor),
            'currency' => $line->currency,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fundingPhasePayload(FundingPhase $phase): array
    {
        return [
            ...$this->fundingPhaseSummaryPayload($phase),
            'milestone' => $phase->milestone,
            'plannedAmountMinor' => $phase->planned_amount_minor,
            'requestedAmountMinor' => $phase->requested_amount_minor,
            'approvedAmountMinor' => $phase->approved_amount_minor,
            'externallyReleasedAmountMinor' => $phase->externally_released_amount_minor,
            'plannedAmount' => Money::toDecimal($phase->planned_amount_minor),
            'requestedAmount' => Money::toDecimal($phase->requested_amount_minor),
            'approvedAmount' => Money::toDecimal($phase->approved_amount_minor),
            'externallyReleasedAmount' => Money::toDecimal($phase->externally_released_amount_minor),
            'expectedOn' => $phase->expected_on?->toDateString(),
            'releasedOn' => $phase->released_on?->toDateString(),
            'notes' => $phase->notes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fundingPhaseSummaryPayload(FundingPhase $phase): array
    {
        return [
            'id' => $phase->id,
            'name' => $phase->name,
            'farmId' => $phase->farm_id,
            'farmName' => $phase->farm->name,
            'productionCycleId' => $phase->production_cycle_id,
            'productionCycleName' => $phase->productionCycle?->name,
            'budgetId' => $phase->budget_id,
            'budgetName' => $phase->budget?->name,
            'status' => $phase->status->value,
            'statusLabel' => $phase->status->label(),
            'currency' => $phase->currency,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function expensePayload(Expense $expense, Team $team): array
    {
        return [
            ...$this->expenseSummaryPayload($expense),
            'vendor' => $expense->vendor,
            'paymentMethod' => $expense->payment_method,
            'description' => $expense->description,
            'notes' => $expense->notes,
            'incurredOn' => $expense->incurred_on->toDateString(),
            'receipts' => $expense->media->map(fn (Media $media) => $this->mediaPayload($media, $team)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function expenseSummaryPayload(Expense $expense): array
    {
        return [
            'id' => $expense->id,
            'farmId' => $expense->farm_id,
            'farmName' => $expense->farm->name,
            'productionCycleId' => $expense->production_cycle_id,
            'productionCycleName' => $expense->productionCycle?->name,
            'budgetId' => $expense->budget_id,
            'budgetName' => $expense->budget?->name,
            'budgetLineId' => $expense->budget_line_id,
            'fundingPhaseId' => $expense->funding_phase_id,
            'fundingPhaseName' => $expense->fundingPhase?->name,
            'expenseCategoryId' => $expense->expense_category_id,
            'expenseCategoryName' => $expense->expenseCategory->name,
            'farmActivityId' => $expense->farm_activity_id,
            'farmActivityType' => $expense->farmActivity?->activity_type,
            'amountMinor' => $expense->amount_minor,
            'amount' => Money::toDecimal($expense->amount_minor),
            'currency' => $expense->currency,
            'status' => $expense->status->value,
            'statusLabel' => $expense->status->label(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function externalTransferPayload(ExternalTransfer $transfer, Team $team): array
    {
        return [
            'id' => $transfer->id,
            'farmId' => $transfer->farm_id,
            'farmName' => $transfer->farm->name,
            'productionCycleId' => $transfer->production_cycle_id,
            'productionCycleName' => $transfer->productionCycle?->name,
            'budgetId' => $transfer->budget_id,
            'budgetName' => $transfer->budget?->name,
            'fundingPhaseId' => $transfer->funding_phase_id,
            'fundingPhaseName' => $transfer->fundingPhase?->name,
            'expenseId' => $transfer->expense_id,
            'direction' => $transfer->direction->value,
            'directionLabel' => $transfer->direction->label(),
            'transferType' => $transfer->transfer_type,
            'status' => $transfer->status->value,
            'statusLabel' => $transfer->status->label(),
            'counterpartyName' => $transfer->counterparty_name,
            'reference' => $transfer->reference,
            'amountMinor' => $transfer->amount_minor,
            'amount' => Money::toDecimal($transfer->amount_minor),
            'currency' => $transfer->currency,
            'transferredOn' => $transfer->transferred_on->toDateString(),
            'notes' => $transfer->notes,
            'proof' => $transfer->media->map(fn (Media $media) => $this->mediaPayload($media, $team)),
            'reconciliations' => $transfer->reconciliations->map(fn ($reconciliation) => [
                'id' => $reconciliation->id,
                'status' => $reconciliation->status->value,
                'statusLabel' => $reconciliation->status->label(),
                'reconciledAmountMinor' => $reconciliation->reconciled_amount_minor,
                'reconciledAmount' => Money::toDecimal($reconciliation->reconciled_amount_minor),
                'reconciledAt' => $reconciliation->reconciled_at->toISOString(),
                'notes' => $reconciliation->notes,
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function activityPayload(FarmActivity $activity): array
    {
        return [
            'id' => $activity->id,
            'farmId' => $activity->farm_id,
            'farmName' => $activity->farm->name,
            'productionCycleId' => $activity->production_cycle_id,
            'productionCycleName' => $activity->productionCycle?->name,
            'activityType' => $activity->activity_type,
            'activityDate' => $activity->activity_date->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mediaPayload(Media $media, Team $team): array
    {
        return [
            'id' => $media->id,
            'name' => $media->name,
            'fileName' => $media->file_name,
            'mimeType' => $media->mime_type,
            'size' => $media->size,
            'caption' => $media->getCustomProperty('caption'),
            'downloadUrl' => route('finance.evidence.show', [$team, $media]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return [
            'budgetStatuses' => BudgetStatus::options(),
            'fundingPhaseStatuses' => FundingPhaseStatus::options(),
            'expenseStatuses' => ExpenseStatus::options(),
            'externalTransferDirections' => ExternalTransferDirection::options(),
            'externalTransferStatuses' => ExternalTransferStatus::options(),
            'transferReconciliationStatuses' => TransferReconciliationStatus::options(),
        ];
    }
}

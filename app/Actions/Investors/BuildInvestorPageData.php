<?php

namespace App\Actions\Investors;

use App\Enums\ApprovalRequestStatus;
use App\Enums\ApprovalRequestType;
use App\Enums\ApprovalTriggerType;
use App\Enums\CapitalRecoveryRule;
use App\Enums\InvestorAgreementStatus;
use App\Enums\InvestorVisibilityStatus;
use App\Enums\TeamRole;
use App\Models\ApprovalRequest;
use App\Models\DistributionRecord;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExternalTransfer;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FarmTask;
use App\Models\FundingPhase;
use App\Models\HarvestRecord;
use App\Models\InvestorAgreement;
use App\Models\InvestorComment;
use App\Models\ProductionPlanChange;
use App\Models\SaleRecord;
use App\Models\Team;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class BuildInvestorPageData
{
    public function __construct(private ResolveInvestorApprovalSettings $resolveInvestorApprovalSettings)
    {
        //
    }

    /**
     * @return array<string, mixed>
     */
    public function investors(Team $team, User $user): array
    {
        return [
            'permissions' => $user->toTeamPermissions($team),
            'approvalSettings' => $this->resolveInvestorApprovalSettings->handle($team),
            'investors' => $this->investorUsers($team)->map(fn (User $investor) => $this->userPayload($investor)),
            'farms' => $this->farms($team)->map(fn (Farm $farm) => $this->farmPayload($farm)),
            'categories' => $this->categories($team)->map(fn (ExpenseCategory $category) => $this->categoryPayload($category)),
            'agreements' => $this->agreements($team)->map(fn (InvestorAgreement $agreement) => $this->agreementPayload($agreement, includeInternal: true)),
            'options' => $this->options(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function approvals(Team $team, User $user): array
    {
        return [
            'permissions' => $user->toTeamPermissions($team),
            'approvalRequests' => $team->approvalRequests()
                ->with(['investorAgreement.investor', 'investorAgreement.farm', 'requestedBy', 'decidedBy', 'subject'])
                ->latest()
                ->get()
                ->map(fn (ApprovalRequest $approvalRequest) => $this->approvalRequestPayload($approvalRequest)),
            'options' => $this->options(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function portal(Team $team, User $user): array
    {
        $agreements = $team->investorAgreements()
            ->where('investor_id', $user->id)
            ->whereIn('status', [InvestorAgreementStatus::Active->value, InvestorAgreementStatus::Completed->value])
            ->with(['team', 'investor', 'farm', 'productionCycle', 'approvalRequests', 'investorComments.author'])
            ->latest()
            ->get();

        return [
            'permissions' => $user->toTeamPermissions($team),
            'agreements' => $agreements->map(fn (InvestorAgreement $agreement) => [
                ...$this->agreementPayload($agreement),
                'summary' => $this->agreementSummary($agreement),
                'fundingPhases' => $this->approvedFundingPhases($agreement)->map(fn (FundingPhase $phase) => $this->fundingPhasePayload($phase)),
                'expenses' => $this->approvedExpenses($agreement)->map(fn (Expense $expense) => $this->expensePayload($expense, $agreement)),
                'externalTransfers' => $this->approvedExternalTransfers($agreement)->map(fn (ExternalTransfer $transfer) => $this->externalTransferPayload($transfer, $agreement)),
                'harvestRecords' => $this->approvedHarvestRecords($agreement)->map(fn (HarvestRecord $harvest) => $this->harvestPayload($harvest, $agreement)),
                'saleRecords' => $this->approvedSaleRecords($agreement)->map(fn (SaleRecord $sale) => $this->salePayload($sale, $agreement)),
                'distributionRecords' => $this->approvedDistributionRecords($agreement)->map(fn (DistributionRecord $distribution) => $this->distributionPayload($distribution)),
                'activities' => $this->approvedActivities($agreement)->map(fn (FarmActivity $activity) => $this->activityPayload($activity, $agreement)),
                'tasks' => $this->approvedTasks($agreement)->map(fn (FarmTask $task) => $this->taskPayload($task)),
                'planChanges' => $this->approvedPlanChanges($agreement)->map(fn (ProductionPlanChange $change) => $this->planChangePayload($change)),
                'approvalRequests' => $agreement->approvalRequests
                    ->sortByDesc('created_at')
                    ->values()
                    ->map(fn (ApprovalRequest $approvalRequest) => $this->approvalRequestPayload($approvalRequest)),
                'comments' => $agreement->investorComments
                    ->sortByDesc('created_at')
                    ->values()
                    ->map(fn (InvestorComment $comment) => $this->commentPayload($comment)),
            ]),
            'options' => [
                'commentSubjectTypes' => [
                    ['value' => 'expense', 'label' => 'Expense'],
                    ['value' => 'funding_phase', 'label' => 'Funding phase'],
                    ['value' => 'activity', 'label' => 'Activity'],
                    ['value' => 'harvest', 'label' => 'Harvest'],
                    ['value' => 'sale', 'label' => 'Sale'],
                    ['value' => 'distribution', 'label' => 'Distribution'],
                ],
            ],
        ];
    }

    /**
     * @return Collection<int, User>
     */
    private function investorUsers(Team $team): Collection
    {
        return $team->members()
            ->wherePivot('role', TeamRole::Investor->value)
            ->orderBy('name')
            ->get();
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
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, InvestorAgreement>
     */
    private function agreements(Team $team): Collection
    {
        return $team->investorAgreements()
            ->with(['team', 'investor', 'farm.productionCycles', 'productionCycle', 'media'])
            ->latest()
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function agreementPayload(InvestorAgreement $agreement, bool $includeInternal = false): array
    {
        return [
            'id' => $agreement->id,
            'title' => $agreement->title,
            'investorId' => $agreement->investor_id,
            'investorName' => $agreement->investor->name,
            'investorEmail' => $agreement->investor->email,
            'farmId' => $agreement->farm_id,
            'farmName' => $agreement->farm->name,
            'productionCycleId' => $agreement->production_cycle_id,
            'productionCycleName' => $agreement->productionCycle?->name,
            'status' => $agreement->status->value,
            'statusLabel' => $agreement->status->label(),
            'currency' => $agreement->currency,
            'amountCommittedMinor' => $agreement->amount_committed_minor,
            'amountCommitted' => Money::toDecimal($agreement->amount_committed_minor),
            'amountFundedMinor' => $agreement->amount_funded_minor,
            'amountFunded' => Money::toDecimal($agreement->amount_funded_minor),
            'capitalRecoveryRule' => $agreement->capital_recovery_rule->value,
            'capitalRecoveryRuleLabel' => $agreement->capital_recovery_rule->label(),
            'investorProfitSharePercentage' => $agreement->investor_profit_share_percentage,
            'farmProfitSharePercentage' => $agreement->farm_profit_share_percentage,
            'fundingModel' => $agreement->funding_model,
            'roleResponsibilities' => $agreement->role_responsibilities,
            'publicNotes' => $agreement->public_notes,
            'internalNotes' => $includeInternal ? $agreement->internal_notes : null,
            'startsOn' => $agreement->starts_on?->toDateString(),
            'endsOn' => $agreement->ends_on?->toDateString(),
            'signedAt' => $agreement->signed_at?->toISOString(),
            'documents' => $agreement->getMedia(InvestorAgreement::DocumentsCollection)
                ->map(fn (Media $media) => $this->mediaPayload($media, $agreement->team))
                ->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function agreementSummary(InvestorAgreement $agreement): array
    {
        $approvedSaleRecords = $this->approvedSaleRecords($agreement);
        $approvedDistributionRecords = $this->approvedDistributionRecords($agreement);
        $releasedMinor = (int) $this->approvedFundingPhases($agreement)->sum('externally_released_amount_minor');
        $spentMinor = (int) $this->approvedExpenses($agreement)->sum('amount_minor');
        $saleNetMinor = (int) $approvedSaleRecords->sum('net_amount_minor');
        $capitalRecoveredMinor = (int) $approvedDistributionRecords->sum('capital_recovered_minor');
        $unrecoveredCapitalMinor = max(0, $agreement->amount_funded_minor - $capitalRecoveredMinor);
        $investorShareMinor = (int) $approvedDistributionRecords->sum('investor_share_minor');
        $farmShareMinor = (int) $approvedDistributionRecords->sum('farm_share_minor');

        return [
            'releasedMinor' => $releasedMinor,
            'spentMinor' => $spentMinor,
            'balanceMinor' => max(0, $releasedMinor - $spentMinor),
            'saleNetMinor' => $saleNetMinor,
            'capitalRecoveredMinor' => $capitalRecoveredMinor,
            'unrecoveredCapitalMinor' => $unrecoveredCapitalMinor,
            'investorShareMinor' => $investorShareMinor,
            'farmShareMinor' => $farmShareMinor,
            'released' => Money::toDecimal($releasedMinor),
            'spent' => Money::toDecimal($spentMinor),
            'balance' => Money::toDecimal(max(0, $releasedMinor - $spentMinor)),
            'saleNet' => Money::toDecimal($saleNetMinor),
            'capitalRecovered' => Money::toDecimal($capitalRecoveredMinor),
            'unrecoveredCapital' => Money::toDecimal($unrecoveredCapitalMinor),
            'investorShare' => Money::toDecimal($investorShareMinor),
            'farmShare' => Money::toDecimal($farmShareMinor),
        ];
    }

    /**
     * @return Collection<int, FundingPhase>
     */
    private function approvedFundingPhases(InvestorAgreement $agreement): Collection
    {
        return $agreement->fundingPhases()
            ->where('investor_visibility_status', InvestorVisibilityStatus::Approved->value)
            ->orderBy('expected_on')
            ->get();
    }

    /**
     * @return Collection<int, Expense>
     */
    private function approvedExpenses(InvestorAgreement $agreement): Collection
    {
        return $agreement->expenses()
            ->with(['expenseCategory', 'farmActivity', 'media'])
            ->where('investor_visibility_status', InvestorVisibilityStatus::Approved->value)
            ->latest('incurred_on')
            ->get();
    }

    /**
     * @return Collection<int, ExternalTransfer>
     */
    private function approvedExternalTransfers(InvestorAgreement $agreement): Collection
    {
        return $agreement->externalTransfers()
            ->with('media')
            ->where('investor_visibility_status', InvestorVisibilityStatus::Approved->value)
            ->latest('transferred_on')
            ->get();
    }

    /**
     * @return Collection<int, HarvestRecord>
     */
    private function approvedHarvestRecords(InvestorAgreement $agreement): Collection
    {
        return $agreement->harvestRecords()
            ->with(['commodity', 'media'])
            ->where('investor_visibility_status', InvestorVisibilityStatus::Approved->value)
            ->latest('harvested_on')
            ->get();
    }

    /**
     * @return Collection<int, SaleRecord>
     */
    private function approvedSaleRecords(InvestorAgreement $agreement): Collection
    {
        return $agreement->saleRecords()
            ->with(['commodity', 'media', 'distributionRecord'])
            ->where(function ($query): void {
                $query->where('investor_visibility_status', InvestorVisibilityStatus::Approved->value)
                    ->orWhereHas('distributionRecord', fn ($query) => $query->where('investor_visibility_status', InvestorVisibilityStatus::Approved->value));
            })
            ->latest('sold_on')
            ->get();
    }

    /**
     * @return Collection<int, DistributionRecord>
     */
    private function approvedDistributionRecords(InvestorAgreement $agreement): Collection
    {
        return $agreement->distributionRecords()
            ->with('saleRecord')
            ->where('investor_visibility_status', InvestorVisibilityStatus::Approved->value)
            ->latest('calculated_at')
            ->get();
    }

    /**
     * @return Collection<int, FarmActivity>
     */
    private function approvedActivities(InvestorAgreement $agreement): Collection
    {
        return FarmActivity::query()
            ->where('team_id', $agreement->team_id)
            ->where('farm_id', $agreement->farm_id)
            ->when($agreement->production_cycle_id, fn ($query) => $query->where('production_cycle_id', $agreement->production_cycle_id))
            ->whereNotNull('investor_safe_summary')
            ->with('media')
            ->latest('activity_date')
            ->limit(20)
            ->get();
    }

    /**
     * @return Collection<int, FarmTask>
     */
    private function approvedTasks(InvestorAgreement $agreement): Collection
    {
        return FarmTask::query()
            ->where('team_id', $agreement->team_id)
            ->where('farm_id', $agreement->farm_id)
            ->when($agreement->production_cycle_id, fn ($query) => $query->where('production_cycle_id', $agreement->production_cycle_id))
            ->where('investor_visible', true)
            ->latest('due_on')
            ->limit(20)
            ->get();
    }

    /**
     * @return Collection<int, ProductionPlanChange>
     */
    private function approvedPlanChanges(InvestorAgreement $agreement): Collection
    {
        return ProductionPlanChange::query()
            ->where('team_id', $agreement->team_id)
            ->where('investor_agreement_id', $agreement->id)
            ->where('investor_visibility_status', InvestorVisibilityStatus::Approved->value)
            ->latest()
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function approvalRequestPayload(ApprovalRequest $approvalRequest): array
    {
        return [
            'id' => $approvalRequest->id,
            'investorAgreementId' => $approvalRequest->investor_agreement_id,
            'agreementTitle' => $approvalRequest->investorAgreement->title,
            'investorName' => $approvalRequest->investorAgreement->investor->name,
            'requestType' => $approvalRequest->request_type->value,
            'requestTypeLabel' => $approvalRequest->request_type->label(),
            'triggerType' => $approvalRequest->trigger_type->value,
            'triggerTypeLabel' => $approvalRequest->trigger_type->label(),
            'status' => $approvalRequest->status->value,
            'statusLabel' => $approvalRequest->status->label(),
            'requestedAmount' => Money::toDecimal($approvalRequest->requested_amount_minor),
            'approvedAmount' => $approvalRequest->approved_amount_minor === null ? null : Money::toDecimal($approvalRequest->approved_amount_minor),
            'currency' => $approvalRequest->currency,
            'subjectLabel' => $this->subjectLabel($approvalRequest->subject),
            'requesterComment' => $approvalRequest->requester_comment,
            'decisionComment' => $approvalRequest->decision_comment,
            'requestedAt' => $approvalRequest->requested_at->toISOString(),
            'decidedAt' => $approvalRequest->decided_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fundingPhasePayload(FundingPhase $phase): array
    {
        return [
            'id' => $phase->id,
            'name' => $phase->name,
            'milestone' => $phase->milestone,
            'status' => $phase->status->value,
            'statusLabel' => $phase->status->label(),
            'plannedAmount' => Money::toDecimal($phase->planned_amount_minor),
            'approvedAmount' => Money::toDecimal($phase->approved_amount_minor),
            'externallyReleasedAmount' => Money::toDecimal($phase->externally_released_amount_minor),
            'expectedOn' => $phase->expected_on?->toDateString(),
            'releasedOn' => $phase->released_on?->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function expensePayload(Expense $expense, InvestorAgreement $agreement): array
    {
        return [
            'id' => $expense->id,
            'incurredOn' => $expense->incurred_on->toDateString(),
            'categoryName' => $expense->expenseCategory->name,
            'description' => $expense->farmActivity?->investor_safe_summary ?: $expense->description,
            'amount' => Money::toDecimal($expense->amount_minor),
            'currency' => $expense->currency,
            'status' => $expense->status->value,
            'receipts' => $expense->getMedia(Expense::ReceiptsCollection)
                ->map(fn (Media $media) => $this->mediaPayload($media, $agreement->team))
                ->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function externalTransferPayload(ExternalTransfer $transfer, InvestorAgreement $agreement): array
    {
        return [
            'id' => $transfer->id,
            'direction' => $transfer->direction->value,
            'transferType' => $transfer->transfer_type,
            'status' => $transfer->status->value,
            'amount' => Money::toDecimal($transfer->amount_minor),
            'currency' => $transfer->currency,
            'transferredOn' => $transfer->transferred_on->toDateString(),
            'proof' => $transfer->getMedia(ExternalTransfer::ProofCollection)
                ->map(fn (Media $media) => $this->mediaPayload($media, $agreement->team))
                ->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function harvestPayload(HarvestRecord $harvest, InvestorAgreement $agreement): array
    {
        return [
            'id' => $harvest->id,
            'harvestedOn' => $harvest->harvested_on->toDateString(),
            'commodityName' => $harvest->commodity->name,
            'stage' => $harvest->stage->value,
            'stageLabel' => $harvest->stage->label(),
            'quantity' => $harvest->quantity,
            'quantityUnit' => $harvest->quantity_unit,
            'qualityNotes' => $harvest->quality_notes,
            'status' => $harvest->status->value,
            'statusLabel' => $harvest->status->label(),
            'evidence' => $harvest->getMedia(HarvestRecord::EvidenceCollection)
                ->map(fn (Media $media) => $this->mediaPayload($media, $agreement->team))
                ->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function salePayload(SaleRecord $sale, InvestorAgreement $agreement): array
    {
        return [
            'id' => $sale->id,
            'soldOn' => $sale->sold_on->toDateString(),
            'buyerName' => $sale->buyer_name,
            'commodityName' => $sale->commodity->name,
            'quantity' => $sale->quantity,
            'quantityUnit' => $sale->quantity_unit,
            'grossAmount' => Money::toDecimal($sale->gross_amount_minor),
            'deductionAmount' => Money::toDecimal($sale->deduction_amount_minor),
            'netAmount' => Money::toDecimal($sale->net_amount_minor),
            'currency' => $sale->currency,
            'paymentStatus' => $sale->payment_status->value,
            'paymentStatusLabel' => $sale->payment_status->label(),
            'evidence' => $sale->getMedia(SaleRecord::EvidenceCollection)
                ->map(fn (Media $media) => $this->mediaPayload($media, $agreement->team))
                ->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function distributionPayload(DistributionRecord $distribution): array
    {
        return [
            'id' => $distribution->id,
            'saleRecordId' => $distribution->sale_record_id,
            'buyerName' => $distribution->saleRecord->buyer_name,
            'saleNetAmount' => Money::toDecimal($distribution->sale_net_amount_minor),
            'capitalRecovered' => Money::toDecimal($distribution->capital_recovered_minor),
            'unrecoveredCapital' => Money::toDecimal($distribution->unrecovered_capital_minor),
            'netProfit' => Money::toDecimal($distribution->net_profit_minor),
            'investorShare' => Money::toDecimal($distribution->investor_share_minor),
            'farmShare' => Money::toDecimal($distribution->farm_share_minor),
            'currency' => $distribution->currency,
            'status' => $distribution->status->value,
            'statusLabel' => $distribution->status->label(),
            'calculatedAt' => $distribution->calculated_at->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function activityPayload(FarmActivity $activity, InvestorAgreement $agreement): array
    {
        return [
            'id' => $activity->id,
            'activityDate' => $activity->activity_date->toDateString(),
            'activityType' => $activity->activity_type,
            'summary' => $activity->investor_safe_summary,
            'status' => $activity->status->value,
            'evidence' => $activity->getMedia(FarmActivity::EvidenceCollection)
                ->filter(fn (Media $media): bool => $media->getCustomProperty('visibility') === 'investor_visible')
                ->map(fn (Media $media) => $this->mediaPayload($media, $agreement->team))
                ->values(),
            'agreementId' => $agreement->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function taskPayload(FarmTask $task): array
    {
        return [
            'id' => $task->id,
            'title' => $task->title,
            'activityType' => $task->activity_type,
            'status' => $task->status->value,
            'dueOn' => $task->due_on?->toDateString(),
            'nextActivity' => $task->description,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function planChangePayload(ProductionPlanChange $change): array
    {
        return [
            'id' => $change->id,
            'changeType' => $change->change_type->value,
            'reason' => $change->reason,
            'impact' => $change->impact,
            'summary' => $change->investor_safe_summary,
            'createdAt' => $change->created_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function commentPayload(InvestorComment $comment): array
    {
        return [
            'id' => $comment->id,
            'authorName' => $comment->author->name,
            'body' => $comment->body,
            'subjectLabel' => $this->subjectLabel($comment->subject),
            'createdAt' => $comment->created_at?->toISOString(),
        ];
    }

    /**
     * @return array{id: int, name: string, email: string}
     */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function farmPayload(Farm $farm): array
    {
        return [
            'id' => $farm->id,
            'name' => $farm->name,
            'farmType' => $farm->farm_type->value,
            'productionCycles' => $farm->productionCycles->map(fn ($cycle) => [
                'id' => $cycle->id,
                'name' => $cycle->name,
            ])->values(),
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
            'requiresInvestorApproval' => $category->requires_investor_approval,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mediaPayload(Media $media, Team $team): array
    {
        return [
            'id' => $media->id,
            'fileName' => $media->file_name,
            'name' => $media->name,
            'mimeType' => $media->mime_type,
            'size' => $media->size,
            'caption' => $media->getCustomProperty('caption'),
            'downloadUrl' => route('investor-evidence.show', [$team, $media]),
        ];
    }

    private function subjectLabel(?Model $subject): string
    {
        return match (true) {
            $subject instanceof Expense => $subject->expenseCategory?->name ?? 'Expense',
            $subject instanceof FundingPhase => $subject->name,
            $subject instanceof ProductionPlanChange => $subject->change_type->label(),
            $subject instanceof DistributionRecord => 'Distribution for '.$subject->saleRecord?->buyer_name,
            $subject instanceof SaleRecord => 'Sale to '.$subject->buyer_name,
            $subject instanceof HarvestRecord => $subject->commodity?->name.' harvest',
            default => 'Record',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return [
            'agreementStatuses' => InvestorAgreementStatus::options(),
            'capitalRecoveryRules' => CapitalRecoveryRule::options(),
            'approvalRequestStatuses' => ApprovalRequestStatus::options(),
            'approvalRequestTypes' => ApprovalRequestType::options(),
            'approvalTriggerTypes' => ApprovalTriggerType::options(),
            'investorVisibilityStatuses' => InvestorVisibilityStatus::options(),
        ];
    }
}

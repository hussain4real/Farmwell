<?php

use App\Actions\Finance\EnsureDefaultExpenseCategories;
use App\Enums\ApprovalRequestStatus;
use App\Enums\ApprovalRequestType;
use App\Enums\InvestorAgreementStatus;
use App\Enums\InvestorVisibilityStatus;
use App\Enums\TeamRole;
use App\Models\ApprovalRequest;
use App\Models\AuditEvent;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExternalTransfer;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FarmTask;
use App\Models\FundingPhase;
use App\Models\InvestorAgreement;
use App\Models\InvestorComment;
use App\Models\ProductionCycle;
use App\Models\ProductionPlanChange;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Models\WhatsappIntake;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

function farmwellInvestorContext(): array
{
    $owner = User::factory()->create();
    $investor = User::factory()->create();
    $team = Team::factory()->create(['name' => 'Investor Team']);

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($investor, ['role' => TeamRole::Investor->value]);
    $owner->switchTeam($team);
    $investor->switchTeam($team);

    $farm = Farm::factory()->create(['team_id' => $team->id, 'name' => 'Investor Farm']);
    $cycle = ProductionCycle::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'name' => 'Investor Cycle',
    ]);

    app(EnsureDefaultExpenseCategories::class)->handle($team);
    $category = ExpenseCategory::query()->where('team_id', $team->id)->where('slug', 'tools')->firstOrFail();

    return [$owner, $investor, $team, $farm, $cycle, $category];
}

function farmwellInvestorRoute(string $name, Team $team, array $parameters = []): string
{
    return route($name, ['current_team' => $team, ...$parameters]);
}

test('investor role can be invited and receives portal-only navigation permissions', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $invitedInvestor = User::factory()->create(['email' => 'new-investor@example.com']);
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $owner->switchTeam($team);

    $this
        ->actingAs($owner)
        ->post(route('teams.invitations.store', $team), [
            'email' => 'new-investor@example.com',
            'role' => TeamRole::Investor->value,
        ])
        ->assertRedirect(route('teams.edit', $team));

    $invitation = TeamInvitation::query()->where('email', 'new-investor@example.com')->firstOrFail();

    $this
        ->actingAs($invitedInvestor)
        ->get(route('invitations.accept', $invitation))
        ->assertRedirect(route('dashboard'));

    expect($invitedInvestor->fresh()->teamRole($team))->toBe(TeamRole::Investor)
        ->and($invitedInvestor->fresh()->toTeamPermissions($team)->canViewInvestorPortal)->toBeTrue()
        ->and($invitedInvestor->fresh()->toTeamPermissions($team)->canViewFinance)->toBeFalse()
        ->and($invitedInvestor->fresh()->toTeamPermissions($team)->canViewFarmOperations)->toBeFalse();

    $invitedInvestor->fresh()->switchTeam($team);

    $this
        ->actingAs($invitedInvestor)
        ->get(farmwellInvestorRoute('investor-portal.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('investor-portal/Index')
            ->where('agreements', [])
            ->where('currentTeamPermissions.canViewInvestorPortal', true)
            ->where('currentTeamPermissions.canViewFinance', false)
        );

    $this
        ->actingAs($invitedInvestor)
        ->get(farmwellInvestorRoute('finance.index', $team))
        ->assertForbidden();

    $this
        ->actingAs($invitedInvestor)
        ->get(farmwellInvestorRoute('investors.index', $team))
        ->assertForbidden();
});

test('internal users create agreements documents and approval rules with audits', function () {
    Storage::fake('farmwell_private');

    [$owner, $investor, $team, $farm, $cycle, $category] = farmwellInvestorContext();

    $this
        ->actingAs($owner)
        ->post(farmwellInvestorRoute('investors.agreements.store', $team), [
            'investor_id' => $investor->id,
            'farm_id' => $farm->id,
            'production_cycle_id' => $cycle->id,
            'title' => 'Dry season tomato agreement',
            'status' => InvestorAgreementStatus::Active->value,
            'amount_committed' => '1000000.00',
            'amount_funded' => '500000.00',
            'investor_profit_share_percentage' => 40,
            'farm_profit_share_percentage' => 60,
            'public_notes' => 'Shared operating progress only.',
            'internal_notes' => 'Sensitive bank terms stay internal.',
            'agreement_document' => UploadedFile::fake()->create('agreement.pdf', 64, 'application/pdf'),
        ])
        ->assertRedirect(farmwellInvestorRoute('investors.index', $team));

    $agreement = InvestorAgreement::query()->firstOrFail();
    $document = $agreement->getFirstMedia(InvestorAgreement::DocumentsCollection);

    expect($agreement->team_id)->toBe($team->id)
        ->and($agreement->investor_id)->toBe($investor->id)
        ->and($agreement->farm_id)->toBe($farm->id)
        ->and($agreement->production_cycle_id)->toBe($cycle->id)
        ->and($agreement->amount_committed_minor)->toBe(100_000_000)
        ->and($agreement->capital_recovery_rule->value)->toBe('capital_first')
        ->and($document)->not->toBeNull()
        ->and($document?->disk)->toBe('farmwell_private');

    $this
        ->actingAs($investor)
        ->get(farmwellInvestorRoute('investor-evidence.show', $team, ['media' => $document]))
        ->assertDownload($document?->file_name);

    $this
        ->actingAs($owner)
        ->post(farmwellInvestorRoute('investors.agreements.store', $team), [
            'investor_id' => $investor->id,
            'farm_id' => $farm->id,
            'title' => 'Invalid split',
            'amount_committed' => '100.00',
            'investor_profit_share_percentage' => 50,
            'farm_profit_share_percentage' => 40,
        ])
        ->assertSessionHasErrors('investor_profit_share_percentage');

    $this
        ->actingAs($owner)
        ->post(farmwellInvestorRoute('investors.agreements.documents.store', $team, ['investor_agreement' => $agreement]), [
            'agreement_document' => UploadedFile::fake()->create('signed-agreement.pdf', 64, 'application/pdf'),
        ])
        ->assertRedirect(farmwellInvestorRoute('investors.index', $team));

    $this
        ->actingAs($owner)
        ->patch(farmwellInvestorRoute('investors.approval-settings.update', $team), [
            'currency' => 'NGN',
            'threshold_amount' => '750000.00',
            'required_expense_category_ids' => [$category->id],
            'reason' => 'Investor requested category review.',
        ])
        ->assertRedirect(farmwellInvestorRoute('investors.index', $team));

    expect($category->fresh()->requires_investor_approval)->toBeTrue();

    $this
        ->actingAs($owner)
        ->get(farmwellInvestorRoute('investors.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('investors/Index')
            ->where('agreements.0.title', 'Dry season tomato agreement')
            ->where('agreements.0.internalNotes', 'Sensitive bank terms stay internal.')
            ->where('categories.0.requiresInvestorApproval', true)
            ->where('approvalSettings.expenseThreshold', '750000.00')
        );

    expect(AuditEvent::query()->where('team_id', $team->id)->pluck('action')->all())
        ->toContain(
            'investor_agreement.created',
            'investor_agreement_document.attached',
            'approval_rule.updated',
            'expense_category.investor_approval_updated',
        );
});

test('finance and plan-change writes create investor approval requests and decisions publish approved records', function () {
    Storage::fake('farmwell_private');

    [$owner, $investor, $team, $farm, $cycle, $category] = farmwellInvestorContext();

    $agreement = InvestorAgreement::factory()->create([
        'team_id' => $team->id,
        'investor_id' => $investor->id,
        'farm_id' => $farm->id,
        'production_cycle_id' => $cycle->id,
        'status' => InvestorAgreementStatus::Active,
    ]);
    $budget = Budget::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'production_cycle_id' => $cycle->id,
    ]);
    $line = BudgetLine::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'production_cycle_id' => $cycle->id,
        'budget_id' => $budget->id,
        'expense_category_id' => $category->id,
        'planned_amount_minor' => 10_000,
    ]);

    $this
        ->actingAs($owner)
        ->post(farmwellInvestorRoute('finance.funding-phases.store', $team), [
            'farm_id' => $farm->id,
            'production_cycle_id' => $cycle->id,
            'budget_id' => $budget->id,
            'investor_agreement_id' => $agreement->id,
            'name' => 'Phase one',
            'status' => 'released_externally',
            'externally_released_amount' => '600000.00',
        ])
        ->assertRedirect(farmwellInvestorRoute('finance.funding-phases.index', $team));

    $phase = FundingPhase::query()->firstOrFail();

    expect($phase->investor_agreement_id)->toBe($agreement->id)
        ->and($phase->externally_released_amount_minor)->toBe(60_000_000);

    $this
        ->actingAs($owner)
        ->post(farmwellInvestorRoute('finance.expenses.store', $team), [
            'farm_id' => $farm->id,
            'production_cycle_id' => $cycle->id,
            'budget_id' => $budget->id,
            'budget_line_id' => $line->id,
            'funding_phase_id' => $phase->id,
            'investor_agreement_id' => $agreement->id,
            'expense_category_id' => $category->id,
            'incurred_on' => '2026-06-05',
            'description' => 'Irrigation repair.',
            'amount' => '600000.00',
            'receipts' => [
                UploadedFile::fake()->create('receipt.pdf', 64, 'application/pdf'),
            ],
        ])
        ->assertRedirect(farmwellInvestorRoute('finance.expenses.index', $team));

    $expense = Expense::query()->firstOrFail();
    $receipt = $expense->getFirstMedia(Expense::ReceiptsCollection);

    $this
        ->actingAs($owner)
        ->post(farmwellInvestorRoute('finance.expenses.store', $team), [
            'farm_id' => $farm->id,
            'production_cycle_id' => $cycle->id,
            'investor_agreement_id' => $agreement->id,
            'expense_category_id' => $category->id,
            'incurred_on' => '2026-06-06',
            'description' => 'Small expense without budget line.',
            'amount' => '10.00',
        ])
        ->assertRedirect(farmwellInvestorRoute('finance.expenses.index', $team));

    $this
        ->actingAs($owner)
        ->post(farmwellInvestorRoute('farms.production-cycles.plan-changes.store', $team, [
            'farm' => $farm,
            'production_cycle' => $cycle,
        ]), [
            'change_type' => 'schedule',
            'reason' => 'Delayed seedling delivery.',
            'impact' => 'Transplanting moves by one week.',
            'investor_safe_summary' => 'Timeline moved by one week.',
            'investor_agreement_id' => $agreement->id,
        ])
        ->assertRedirect(farmwellInvestorRoute('farms.index', $team));

    $approvalRequestTypes = ApprovalRequest::query()
        ->where('team_id', $team->id)
        ->pluck('request_type')
        ->map(fn (ApprovalRequestType|string $type): string => $type instanceof ApprovalRequestType ? $type->value : $type)
        ->all();

    expect($approvalRequestTypes)
        ->toContain(
            ApprovalRequestType::FundingRelease->value,
            ApprovalRequestType::Expense->value,
            ApprovalRequestType::BudgetOverrun->value,
            ApprovalRequestType::PlanChange->value,
        )
        ->and($expense->fresh()->investor_visibility_status)->toBe(InvestorVisibilityStatus::PendingApproval);

    $expenseApproval = ApprovalRequest::query()
        ->where('request_type', ApprovalRequestType::Expense)
        ->firstOrFail();

    $this
        ->actingAs($owner)
        ->get(farmwellInvestorRoute('investors.approvals.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('investors/Approvals')
            ->where('approvalRequests.0.investorAgreementId', $agreement->id)
            ->where('permissions.canViewApprovalRequests', true)
        );

    $this
        ->actingAs($investor)
        ->post(farmwellInvestorRoute('investors.approvals.decisions.store', $team, ['approval_request' => $expenseApproval]), [
            'status' => ApprovalRequestStatus::Approved->value,
            'decision_comment' => 'Approved for release.',
        ])
        ->assertRedirect(farmwellInvestorRoute('investor-portal.index', $team));

    expect($expenseApproval->fresh()->status)->toBe(ApprovalRequestStatus::Approved)
        ->and($expense->fresh()->investor_visibility_status)->toBe(InvestorVisibilityStatus::Approved);

    $fundingReleaseApproval = ApprovalRequest::query()
        ->where('request_type', ApprovalRequestType::FundingRelease)
        ->firstOrFail();

    $this
        ->actingAs($owner)
        ->post(farmwellInvestorRoute('investors.approvals.decisions.store', $team, ['approval_request' => $fundingReleaseApproval]), [
            'status' => ApprovalRequestStatus::Rejected->value,
            'decision_comment' => 'Needs a revised budget.',
        ])
        ->assertRedirect(farmwellInvestorRoute('investors.approvals.index', $team));

    $planChangeApproval = ApprovalRequest::query()
        ->where('request_type', ApprovalRequestType::PlanChange)
        ->firstOrFail();

    $this
        ->actingAs($owner)
        ->post(farmwellInvestorRoute('investors.approvals.decisions.store', $team, ['approval_request' => $planChangeApproval]), [
            'status' => ApprovalRequestStatus::ClarificationRequested->value,
            'decision_comment' => 'Clarify the operating impact.',
        ])
        ->assertRedirect(farmwellInvestorRoute('investors.approvals.index', $team));

    $this
        ->actingAs($owner)
        ->get(farmwellInvestorRoute('finance.funding-phases.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('investorAgreements.0.title', $agreement->title)
            ->where('investorAgreements.0.investorName', $investor->name)
        );

    $this
        ->actingAs($owner)
        ->get(farmwellInvestorRoute('finance.expenses.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('investorAgreements.0.title', $agreement->title)
            ->where('investorAgreements.0.currency', $agreement->currency)
        );

    $this
        ->actingAs($owner)
        ->get(farmwellInvestorRoute('finance.external-transfers.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('investorAgreements.0.title', $agreement->title)
        );

    $this
        ->actingAs($owner)
        ->get(farmwellInvestorRoute('farms.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('investorAgreements.0.title', $agreement->title)
            ->where('investorAgreements.0.investorName', $investor->name)
        );

    $this
        ->actingAs($investor)
        ->get(farmwellInvestorRoute('investor-portal.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('investor-portal/Index')
            ->where('agreements.0.internalNotes', null)
            ->where('agreements.0.expenses.0.description', 'Irrigation repair.')
            ->where('agreements.0.expenses.0.receipts.0.fileName', 'receipt.pdf')
            ->where('agreements.0.approvalRequests.0.investorAgreementId', $agreement->id)
        );

    $this
        ->actingAs($investor)
        ->get(farmwellInvestorRoute('investor-evidence.show', $team, ['media' => $receipt]))
        ->assertDownload($receipt?->file_name);
});

test('investor portal denies unrelated inactive and private records while allowing scoped comments', function () {
    Storage::fake('farmwell_private');

    [$owner, $investor, $team, $farm, $cycle, $category] = farmwellInvestorContext();
    $otherInvestor = User::factory()->create();
    $team->members()->attach($otherInvestor, ['role' => TeamRole::Investor->value]);
    $otherInvestor->switchTeam($team);

    $agreement = InvestorAgreement::factory()->create([
        'team_id' => $team->id,
        'investor_id' => $investor->id,
        'farm_id' => $farm->id,
        'production_cycle_id' => $cycle->id,
        'status' => InvestorAgreementStatus::Active,
    ]);
    $inactiveAgreement = InvestorAgreement::factory()->create([
        'team_id' => $team->id,
        'investor_id' => $investor->id,
        'farm_id' => $farm->id,
        'production_cycle_id' => $cycle->id,
        'status' => InvestorAgreementStatus::Suspended,
        'title' => 'Suspended agreement',
    ]);
    Expense::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'production_cycle_id' => $cycle->id,
        'expense_category_id' => $category->id,
        'investor_agreement_id' => $agreement->id,
        'investor_visibility_status' => InvestorVisibilityStatus::Private,
        'description' => 'Private expense.',
    ]);
    $approvedExpense = Expense::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'production_cycle_id' => $cycle->id,
        'expense_category_id' => $category->id,
        'investor_agreement_id' => $agreement->id,
        'investor_visibility_status' => InvestorVisibilityStatus::Approved,
        'description' => 'Approved expense.',
    ]);
    $activity = FarmActivity::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'production_cycle_id' => $cycle->id,
        'investor_safe_summary' => 'Approved progress update.',
    ]);
    $activity
        ->addMedia(UploadedFile::fake()->image('activity.jpg'))
        ->withCustomProperties(['visibility' => 'investor_visible'])
        ->toMediaCollection(FarmActivity::EvidenceCollection, 'farmwell_private');
    $activityMedia = $activity->getFirstMedia(FarmActivity::EvidenceCollection);
    $task = FarmTask::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'production_cycle_id' => $cycle->id,
        'investor_visible' => true,
    ]);
    $planChange = ProductionPlanChange::factory()->create([
        'team_id' => $team->id,
        'production_cycle_id' => $cycle->id,
        'investor_agreement_id' => $agreement->id,
        'investor_visibility_status' => InvestorVisibilityStatus::Approved,
    ]);
    $phase = FundingPhase::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'production_cycle_id' => $cycle->id,
        'investor_agreement_id' => $agreement->id,
        'investor_visibility_status' => InvestorVisibilityStatus::Approved,
    ]);
    $transfer = ExternalTransfer::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'production_cycle_id' => $cycle->id,
        'investor_agreement_id' => $agreement->id,
        'investor_visibility_status' => InvestorVisibilityStatus::Approved,
    ]);
    $approvalRequest = ApprovalRequest::factory()->create([
        'team_id' => $team->id,
        'investor_agreement_id' => $agreement->id,
        'subject_type' => $approvedExpense->getMorphClass(),
        'subject_id' => $approvedExpense->id,
    ]);

    $this
        ->actingAs($investor)
        ->get(farmwellInvestorRoute('investor-portal.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('investor-portal/Index')
            ->has('agreements', 1)
            ->where('agreements.0.title', $agreement->title)
            ->where('agreements.0.expenses.0.description', 'Approved expense.')
            ->missing('agreements.1')
        );

    $this
        ->actingAs($investor)
        ->get(farmwellInvestorRoute('investor-evidence.show', $team, ['media' => $activityMedia]))
        ->assertDownload($activityMedia?->file_name);

    foreach ([
        ['expense', $approvedExpense->id],
        ['funding_phase', $phase->id],
        ['external_transfer', $transfer->id],
        ['activity', $activity->id],
        ['task', $task->id],
        ['plan_change', $planChange->id],
        ['approval_request', $approvalRequest->id],
        [null, null],
    ] as [$subjectType, $subjectId]) {
        $this
            ->actingAs($investor)
            ->post(farmwellInvestorRoute('investor-comments.store', $team, ['investor_agreement' => $agreement]), [
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'body' => 'Can you clarify this update?',
            ])
            ->assertRedirect();
    }

    expect(InvestorComment::query()->where('investor_agreement_id', $agreement->id)->count())->toBe(8);

    $this
        ->actingAs($investor)
        ->get(farmwellInvestorRoute('investor-portal.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('agreements.0.comments.0.body', 'Can you clarify this update?')
            ->where('agreements.0.comments.0.authorName', $investor->name)
        );

    $this
        ->actingAs($otherInvestor)
        ->post(farmwellInvestorRoute('investor-comments.store', $team, ['investor_agreement' => $agreement]), [
            'body' => 'I should not see this.',
        ])
        ->assertForbidden();

    $this
        ->actingAs($investor)
        ->post(farmwellInvestorRoute('investor-comments.store', $team, ['investor_agreement' => $inactiveAgreement]), [
            'body' => 'Inactive agreement.',
        ])
        ->assertForbidden();

    $this
        ->actingAs($otherInvestor)
        ->get(farmwellInvestorRoute('investor-portal.index', $team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('investor-portal/Index')
            ->where('agreements', [])
        );

    $this->actingAs($owner)
        ->post(farmwellInvestorRoute('investor-comments.store', $team, ['investor_agreement' => $agreement]), [
            'subject_type' => 'expense',
            'subject_id' => $approvedExpense->id,
            'body' => 'Internal response.',
        ])
        ->assertRedirect();
});

test('cross-team investor references and private evidence downloads are denied', function () {
    Storage::fake('farmwell_private');

    [$owner, $investor, $team, $farm, $cycle, $category] = farmwellInvestorContext();
    [$otherOwner, $otherInvestor, $otherTeam, $otherFarm, $otherCycle] = farmwellInvestorContext();

    $agreement = InvestorAgreement::factory()->create([
        'team_id' => $team->id,
        'investor_id' => $investor->id,
        'farm_id' => $farm->id,
        'production_cycle_id' => $cycle->id,
        'status' => InvestorAgreementStatus::Active,
    ]);
    $otherAgreement = InvestorAgreement::factory()->create([
        'team_id' => $otherTeam->id,
        'investor_id' => $otherInvestor->id,
        'farm_id' => $otherFarm->id,
        'production_cycle_id' => $otherCycle->id,
        'status' => InvestorAgreementStatus::Active,
    ]);
    $expense = Expense::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'production_cycle_id' => $cycle->id,
        'expense_category_id' => $category->id,
        'investor_agreement_id' => $agreement->id,
        'investor_visibility_status' => InvestorVisibilityStatus::Private,
    ]);
    $expense
        ->addMedia(UploadedFile::fake()->create('private-receipt.pdf', 64, 'application/pdf'))
        ->toMediaCollection(Expense::ReceiptsCollection, 'farmwell_private');
    $media = $expense->getFirstMedia(Expense::ReceiptsCollection);
    $intake = WhatsappIntake::factory()->create(['team_id' => $team->id]);
    $intake
        ->addMedia(UploadedFile::fake()->create('intake.pdf', 64, 'application/pdf'))
        ->toMediaCollection(WhatsappIntake::EvidenceCollection, 'farmwell_private');
    $intakeMedia = $intake->getFirstMedia(WhatsappIntake::EvidenceCollection);

    $this
        ->actingAs($owner)
        ->post(farmwellInvestorRoute('finance.expenses.store', $team), [
            'farm_id' => $farm->id,
            'production_cycle_id' => $cycle->id,
            'investor_agreement_id' => $otherAgreement->id,
            'expense_category_id' => $category->id,
            'incurred_on' => '2026-06-05',
            'description' => 'Cross team.',
            'amount' => '10.00',
        ])
        ->assertSessionHasErrors('investor_agreement_id');

    $this
        ->actingAs($investor)
        ->get(farmwellInvestorRoute('investor-evidence.show', $team, ['media' => $media]))
        ->assertNotFound();

    $this
        ->actingAs($investor)
        ->get(farmwellInvestorRoute('investor-evidence.show', $team, ['media' => $intakeMedia]))
        ->assertNotFound();

    $expense->forceFill(['investor_visibility_status' => InvestorVisibilityStatus::Approved])->save();

    $this
        ->actingAs($otherInvestor)
        ->get(farmwellInvestorRoute('investor-evidence.show', $team, ['media' => $media]))
        ->assertForbidden();

    $this
        ->actingAs($owner)
        ->get(farmwellInvestorRoute('investor-evidence.show', $team, ['media' => $media]))
        ->assertDownload($media?->file_name);

    expect($otherOwner->belongsToTeam($otherTeam))->toBeTrue();
});

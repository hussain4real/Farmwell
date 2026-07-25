<?php

use App\Actions\Investors\BuildInvestorPageData;
use App\Actions\Investors\DetermineInvestorApprovalRequirement;
use App\Enums\ApprovalRequestStatus;
use App\Enums\ApprovalRequestType;
use App\Enums\ApprovalTriggerType;
use App\Enums\CapitalRecoveryRule;
use App\Enums\InvestorAgreementStatus;
use App\Enums\InvestorVisibilityStatus;
use App\Enums\TeamRole;
use App\Http\Requests\Investors\DecideApprovalRequestRequest;
use App\Http\Requests\Investors\StoreInvestorCommentRequest;
use App\Models\ApprovalRequest;
use App\Models\ApprovalRule;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExternalTransfer;
use App\Models\Farm;
use App\Models\FundingPhase;
use App\Models\InvestorAgreement;
use App\Models\InvestorComment;
use App\Models\ProductionCycle;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('investor enums expose labels and options', function () {
    expect(InvestorAgreementStatus::options())->toContain(['value' => 'active', 'label' => 'Active'])
        ->and(InvestorVisibilityStatus::options())->toContain(['value' => 'pending_approval', 'label' => 'Pending approval'])
        ->and(ApprovalRequestStatus::options())->toContain(['value' => 'clarification_requested', 'label' => 'Clarification requested'])
        ->and(ApprovalRequestType::options())->toContain(['value' => 'budget_overrun', 'label' => 'Budget overrun'])
        ->and(ApprovalTriggerType::options())->toContain(['value' => 'budget_overrun', 'label' => 'Budget overrun'])
        ->and(CapitalRecoveryRule::options())->toContain(['value' => 'capital_first', 'label' => 'Capital first']);
});

test('approval requirement routing covers agreement category threshold releases plan changes overruns and below-threshold records', function () {
    $team = Team::factory()->create();
    $investor = User::factory()->create();
    $farm = Farm::factory()->create(['team_id' => $team->id]);
    $category = ExpenseCategory::factory()->create([
        'team_id' => $team->id,
        'requires_investor_approval' => true,
    ]);
    $phase = FundingPhase::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
    ]);
    $agreement = InvestorAgreement::factory()->create([
        'team_id' => $team->id,
        'investor_id' => $investor->id,
        'farm_id' => $farm->id,
    ]);

    ApprovalRule::factory()->create([
        'team_id' => $team->id,
        'investor_agreement_id' => $agreement->id,
        'request_type' => ApprovalRequestType::Expense,
        'trigger_type' => ApprovalTriggerType::Category,
        'expense_category_id' => $category->id,
    ]);

    $determine = app(DetermineInvestorApprovalRequirement::class);

    expect($determine->handle($team, null, ApprovalRequestType::Expense)['required'])->toBeFalse()
        ->and($determine->handle($team, $agreement, ApprovalRequestType::FundingRelease, fundingPhase: $phase)['triggerType'])->toBe(ApprovalTriggerType::FundingRelease)
        ->and($determine->handle($team, $agreement, ApprovalRequestType::PlanChange)['triggerType'])->toBe(ApprovalTriggerType::PlanChange)
        ->and($determine->handle($team, $agreement, ApprovalRequestType::BudgetOverrun)['triggerType'])->toBe(ApprovalTriggerType::BudgetOverrun)
        ->and($determine->handle($team, $agreement, ApprovalRequestType::Expense, category: $category)['rule'])->toBeInstanceOf(ApprovalRule::class)
        ->and($determine->handle($team, $agreement, ApprovalRequestType::Expense, amountMinor: 50_000_000)['triggerType'])->toBe(ApprovalTriggerType::Threshold)
        ->and($determine->handle($team, $agreement, ApprovalRequestType::Expense, amountMinor: 1_000)['required'])->toBeFalse();
});

test('investor models expose casts defaults and relationships', function () {
    $team = Team::factory()->create();
    $creator = User::factory()->create();
    $investor = User::factory()->create();
    $farm = Farm::factory()->create(['team_id' => $team->id]);
    $cycle = ProductionCycle::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
    ]);
    $category = ExpenseCategory::factory()->create(['team_id' => $team->id]);
    $agreement = InvestorAgreement::factory()->create([
        'team_id' => $team->id,
        'investor_id' => $investor->id,
        'farm_id' => $farm->id,
        'production_cycle_id' => $cycle->id,
        'created_by_id' => $creator->id,
    ]);
    $phase = FundingPhase::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'production_cycle_id' => $cycle->id,
        'investor_agreement_id' => $agreement->id,
    ]);
    $rule = ApprovalRule::factory()->create([
        'team_id' => $team->id,
        'investor_agreement_id' => $agreement->id,
        'expense_category_id' => $category->id,
        'funding_phase_id' => $phase->id,
        'created_by_id' => $creator->id,
    ]);
    $expense = Expense::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'production_cycle_id' => $cycle->id,
        'expense_category_id' => $category->id,
        'investor_agreement_id' => $agreement->id,
        'investor_visibility_status' => InvestorVisibilityStatus::Approved,
    ]);
    $transfer = ExternalTransfer::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'production_cycle_id' => $cycle->id,
        'investor_agreement_id' => $agreement->id,
    ]);
    $request = ApprovalRequest::factory()->create([
        'team_id' => $team->id,
        'investor_agreement_id' => $agreement->id,
        'approval_rule_id' => $rule->id,
        'requested_by_id' => $creator->id,
        'decided_by_id' => $investor->id,
        'subject_type' => $expense->getMorphClass(),
        'subject_id' => $expense->id,
        'status' => ApprovalRequestStatus::Approved,
    ]);
    $comment = InvestorComment::factory()->create([
        'team_id' => $team->id,
        'investor_agreement_id' => $agreement->id,
        'author_id' => $investor->id,
        'subject_type' => $request->getMorphClass(),
        'subject_id' => $request->id,
    ]);

    expect($agreement->status)->toBe(InvestorAgreementStatus::Active)
        ->and($agreement->capital_recovery_rule)->toBe(CapitalRecoveryRule::CapitalFirst)
        ->and($agreement->investor_profit_share_percentage)->toBe(40)
        ->and($agreement->farm_profit_share_percentage)->toBe(60)
        ->and($agreement->team->is($team))->toBeTrue()
        ->and($agreement->investor->is($investor))->toBeTrue()
        ->and($agreement->farm->is($farm))->toBeTrue()
        ->and($agreement->productionCycle->is($cycle))->toBeTrue()
        ->and($agreement->createdBy->is($creator))->toBeTrue()
        ->and($agreement->fundingPhases()->count())->toBe(1)
        ->and($agreement->expenses()->count())->toBe(1)
        ->and($agreement->externalTransfers()->count())->toBe(1)
        ->and($agreement->approvalRequests()->count())->toBe(1)
        ->and($agreement->investorComments()->count())->toBe(1)
        ->and($rule->team->is($team))->toBeTrue()
        ->and($rule->investorAgreement->is($agreement))->toBeTrue()
        ->and($rule->expenseCategory->is($category))->toBeTrue()
        ->and($rule->fundingPhase->is($phase))->toBeTrue()
        ->and($rule->createdBy->is($creator))->toBeTrue()
        ->and($rule->request_type)->toBe(ApprovalRequestType::Expense)
        ->and($rule->trigger_type)->toBe(ApprovalTriggerType::Threshold)
        ->and($request->team->is($team))->toBeTrue()
        ->and($request->investorAgreement->is($agreement))->toBeTrue()
        ->and($request->approvalRule->is($rule))->toBeTrue()
        ->and($request->requestedBy->is($creator))->toBeTrue()
        ->and($request->decidedBy->is($investor))->toBeTrue()
        ->and($request->subject->is($expense))->toBeTrue()
        ->and($comment->team->is($team))->toBeTrue()
        ->and($comment->investorAgreement->is($agreement))->toBeTrue()
        ->and($comment->author->is($investor))->toBeTrue()
        ->and($comment->subject->is($request))->toBeTrue()
        ->and($team->investorAgreements()->count())->toBe(1)
        ->and($team->approvalRules()->count())->toBe(1)
        ->and($team->approvalRequests()->count())->toBe(1)
        ->and($team->investorComments()->count())->toBe(1)
        ->and($farm->investorAgreements()->count())->toBe(1)
        ->and($cycle->investorAgreements()->count())->toBe(1)
        ->and($investor->investorAgreements()->count())->toBe(1)
        ->and($investor->decidedApprovalRequests()->count())->toBe(1)
        ->and($expense->investorAgreement->is($agreement))->toBeTrue()
        ->and($phase->investorAgreement->is($agreement))->toBeTrue()
        ->and($transfer->investorAgreement->is($agreement))->toBeTrue();
});

test('investor request authorization guards return false for missing users and cross-team route models', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $otherTeam = Team::factory()->create();
    $farm = Farm::factory()->create(['team_id' => $otherTeam->id]);
    $investor = User::factory()->create();
    $agreement = InvestorAgreement::factory()->create([
        'team_id' => $otherTeam->id,
        'investor_id' => $investor->id,
        'farm_id' => $farm->id,
    ]);
    $approvalRequest = ApprovalRequest::factory()->create([
        'team_id' => $otherTeam->id,
        'investor_agreement_id' => $agreement->id,
    ]);

    $decisionWithoutUser = DecideApprovalRequestRequest::create('/', 'POST');
    $decisionWithoutUser->setRouteResolver(fn () => farmwellInvestorRequestRouteStub([
        'current_team' => $team,
        'approval_request' => $approvalRequest,
    ]));

    $decisionWrongTeam = DecideApprovalRequestRequest::create('/', 'POST');
    $decisionWrongTeam->setUserResolver(fn () => $user);
    $decisionWrongTeam->setRouteResolver(fn () => farmwellInvestorRequestRouteStub([
        'current_team' => $team,
        'approval_request' => $approvalRequest,
    ]));

    $commentWithoutUser = StoreInvestorCommentRequest::create('/', 'POST');
    $commentWithoutUser->setRouteResolver(fn () => farmwellInvestorRequestRouteStub([
        'current_team' => $team,
        'investor_agreement' => $agreement,
    ]));

    $commentWrongTeam = StoreInvestorCommentRequest::create('/', 'POST');
    $commentWrongTeam->setUserResolver(fn () => $user);
    $commentWrongTeam->setRouteResolver(fn () => farmwellInvestorRequestRouteStub([
        'current_team' => $team,
        'investor_agreement' => $agreement,
    ]));

    expect($decisionWithoutUser->authorize())->toBeFalse()
        ->and($decisionWrongTeam->authorize())->toBeFalse()
        ->and($commentWithoutUser->authorize())->toBeFalse()
        ->and($commentWrongTeam->authorize())->toBeFalse();
});

/**
 * @param  array<string, mixed>  $parameters
 */
function farmwellInvestorRequestRouteStub(array $parameters): object
{
    return new class($parameters)
    {
        /**
         * @param  array<string, mixed>  $parameters
         */
        public function __construct(private array $parameters)
        {
            //
        }

        public function parameter(string $key, mixed $default = null): mixed
        {
            return $this->parameters[$key] ?? $default;
        }
    };
}

test('investor safe feed projection excludes internal notes and private records', function () {
    $team = Team::factory()->create();
    $investor = User::factory()->create();
    $team->members()->attach($investor, ['role' => TeamRole::Investor->value]);
    $investor->switchTeam($team);

    $farm = Farm::factory()->create(['team_id' => $team->id]);
    $category = ExpenseCategory::factory()->create(['team_id' => $team->id]);
    $agreement = InvestorAgreement::factory()->create([
        'team_id' => $team->id,
        'investor_id' => $investor->id,
        'farm_id' => $farm->id,
        'internal_notes' => 'Bank details and private terms.',
        'public_notes' => 'Public operating terms.',
    ]);
    Expense::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'expense_category_id' => $category->id,
        'investor_agreement_id' => $agreement->id,
        'investor_visibility_status' => InvestorVisibilityStatus::Private,
        'description' => 'Hidden expense.',
    ]);
    Expense::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'expense_category_id' => $category->id,
        'investor_agreement_id' => $agreement->id,
        'investor_visibility_status' => InvestorVisibilityStatus::Approved,
        'description' => 'Visible expense.',
    ]);

    $portal = app(BuildInvestorPageData::class)->portal($team, $investor);

    expect($portal['agreements'][0]['internalNotes'])->toBeNull()
        ->and($portal['agreements'][0]['publicNotes'])->toBe('Public operating terms.')
        ->and($portal['agreements'][0]['expenses'])->toHaveCount(1)
        ->and($portal['agreements'][0]['expenses'][0]['description'])->toBe('Visible expense.');
});

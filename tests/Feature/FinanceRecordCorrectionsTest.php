<?php

use App\Actions\Finance\RecordExpense;
use App\Ai\Tools\CorrectExpenseTool;
use App\Ai\Tools\GetFinanceSummaryTool;
use App\Ai\Tools\ListPendingApprovalsTool;
use App\Ai\Tools\ReplyToClarificationTool;
use App\Ai\Tools\UpdateFundingRequestTool;
use App\Enums\ApprovalRequestStatus;
use App\Enums\ApprovalRequestType;
use App\Enums\ExpenseStatus;
use App\Enums\FundingPhaseStatus;
use App\Enums\InvestorVisibilityStatus;
use App\Enums\TeamRole;
use App\Models\ApprovalRequest;
use App\Models\AuditEvent;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\Expense;
use App\Models\Farm;
use App\Models\FundingPhase;
use App\Models\InvestorAgreement;
use App\Models\InvestorComment;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Tools\Request as ToolRequest;

beforeEach(function () {
    $this->withoutVite();

    $this->team = Team::factory()->create();
    $this->owner = User::factory()->create();
    $this->investor = User::factory()->create();
    $this->operator = User::factory()->create();
    $this->team->members()->attach([
        $this->owner->id => ['role' => TeamRole::Owner],
        $this->investor->id => ['role' => TeamRole::Investor],
        $this->operator->id => ['role' => TeamRole::Operator],
    ]);
    $this->farm = Farm::factory()->for($this->team)->create();
    $this->agreement = InvestorAgreement::factory()->for($this->team)->for($this->farm)->for($this->investor, 'investor')->create();
});

function fundingRequestForCorrection(InvestorAgreement $agreement): ApprovalRequest
{
    $phase = FundingPhase::factory()->for($agreement->team)->for($agreement->farm)->for($agreement)->create([
        'name' => 'Irrigation',
        'status' => FundingPhaseStatus::Requested,
        'planned_amount_minor' => 123_000_00,
        'requested_amount_minor' => 123_000_00,
        'approved_amount_minor' => 0,
        'externally_released_amount_minor' => 0,
    ]);

    return ApprovalRequest::factory()->for($agreement->team)->for($agreement)->for($phase, 'subject')->create([
        'request_type' => ApprovalRequestType::FundingRelease,
        'status' => ApprovalRequestStatus::ClarificationRequested,
        'requested_amount_minor' => $phase->requested_amount_minor,
        'decision_comment' => 'What does the amount cover?',
        'decided_by_id' => $agreement->investor_id,
        'decided_at' => now(),
    ]);
}

test('funding revisions update the same approval and stale investor decisions are rejected', function () {
    $approval = fundingRequestForCorrection($this->agreement);
    $phase = $approval->subject;
    $updateUrl = route('finance.funding-phases.update', [$this->team, $phase]);
    $decisionUrl = route('investors.approvals.decisions.store', [$this->team, $approval]);
    $oldToken = $approval->reviewToken();

    $otherApproval = fundingRequestForCorrection(InvestorAgreement::factory()->create());
    $this->actingAs($this->owner)->patch(route('finance.funding-phases.update', [$this->team, $otherApproval->subject]), [
        'requested_amount' => '1.00',
    ])->assertNotFound();

    $this->actingAs($this->operator)->patch($updateUrl, ['requested_amount' => '125000.00'])->assertForbidden();
    $this->actingAs($this->owner)->patch($updateUrl, ['requested_amount' => '0.00'])->assertSessionHasErrors('requested_amount');
    $changes = ['requested_amount' => '125000.50', 'name' => 'Irrigation pipes', 'milestone' => 'Installation'];
    $this->actingAs($this->owner)->patch($updateUrl, $changes)->assertRedirect()->assertSessionHasNoErrors();
    $this->patch($updateUrl, $changes)->assertRedirect()->assertSessionHasNoErrors();

    expect($approval->fresh()->requested_amount_minor)->toBe(125_000_50)
        ->and($approval->fresh()->decision_comment)->toBe('What does the amount cover?')
        ->and($phase->fresh()->planned_amount_minor)->toBe(125_000_50)
        ->and($phase->approvalRequests()->count())->toBe(1);

    $this->actingAs($this->investor)->post($decisionUrl, [
        'status' => 'approved', 'review_token' => $oldToken,
    ])->assertSessionHasErrors('status');
    $this->actingAs($this->investor)->post($decisionUrl, [
        'status' => 'approved', 'review_token' => $approval->fresh()->reviewToken(),
    ])->assertRedirect()->assertSessionHasNoErrors();

    $this->actingAs($this->owner)->patch($updateUrl, [
        'requested_amount' => '130000.00',
    ])->assertSessionHasErrors('requested_amount');
    expect($phase->fresh()->approved_amount_minor)->toBe(125_000_50)
        ->and(AuditEvent::query()->where('action', 'funding_phase.updated')->count())->toBe(1);
});

test('an expense correction keeps the original expense and reopens investor review', function () {
    $expense = Expense::factory()->for($this->team)->for($this->farm)->for($this->agreement)->create([
        'description' => 'PVC pipes, fuel and workmanship',
        'amount_minor' => 15_000_00,
        'investor_visibility_status' => InvestorVisibilityStatus::Approved,
    ]);
    $oldApproval = ApprovalRequest::factory()->for($this->team)->for($this->agreement)->for($expense, 'subject')->create([
        'request_type' => ApprovalRequestType::Expense,
        'status' => ApprovalRequestStatus::Approved,
        'requested_amount_minor' => 15_000_00,
    ]);
    $url = route('finance.expenses.update', [$this->team, $expense]);
    $change = ['amount' => '16900.75', 'reason' => 'Checked receipt against the original entry.'];

    $this->actingAs($this->operator)->patch($url, $change)->assertForbidden();
    $this->actingAs($this->owner)->patch($url, ['amount' => '16900.75'])->assertSessionHasErrors('reason');
    $this->actingAs($this->owner)->patch($url, $change)->assertRedirect()->assertSessionHasNoErrors();
    $this->actingAs($this->owner)->patch($url, $change)->assertRedirect()->assertSessionHasNoErrors();

    $this->assertDatabaseCount('expenses', 1);
    expect($expense->fresh()->amount_minor)->toBe(16_900_75)
        ->and($expense->fresh()->investor_visibility_status)->toBe(InvestorVisibilityStatus::PendingApproval)
        ->and($oldApproval->fresh()->status)->toBe(ApprovalRequestStatus::Cancelled)
        ->and($expense->approvalRequests()->where('status', ApprovalRequestStatus::Pending)->sole()->requested_amount_minor)->toBe(16_900_75);
    $audit = AuditEvent::query()->where('action', 'expense.corrected')->sole();
    expect($audit->old_values['amount_minor'])->toBe(15_000_00)
        ->and($audit->new_values['amount_minor'])->toBe(16_900_75)
        ->and($audit->metadata['reason'])->toBe($change['reason']);

    $expense->update(['status' => ExpenseStatus::Reconciled]);
    $this->actingAs($this->owner)->patch($url, [...$change, 'amount' => '16500.00'])->assertSessionHasErrors('amount');
    expect($expense->fresh()->amount_minor)->toBe(16_900_75);
});

test('farm team clarification replies stay on the request and are visible to the investor', function () {
    $approval = fundingRequestForCorrection($this->agreement);
    $url = route('investors.approvals.replies.store', [$this->team, $approval]);
    $reply = ['body' => 'The request covers pipes and installation.'];

    $this->actingAs($this->investor)->post($url, $reply)->assertForbidden();
    $this->actingAs($this->owner)->get(route('investors.approvals.index', $this->team))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('approvalRequests.0.canReplyToClarification', true));

    $this->actingAs($this->owner)->post($url, $reply)->assertRedirect()->assertSessionHasNoErrors();
    $this->actingAs($this->owner)->post($url, $reply)->assertRedirect()->assertSessionHasNoErrors();

    $this->assertDatabaseCount('investor_comments', 1);
    expect($approval->fresh()->status)->toBe(ApprovalRequestStatus::ClarificationRequested)
        ->and($approval->clarificationReplies()->sole()->body)->toBe($reply['body']);

    $this->actingAs($this->investor)->get(route('investor-portal.index', $this->team))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('agreements.0.approvalRequests.0.decisionComment', 'What does the amount cover?')
            ->where('agreements.0.approvalRequests.0.clarificationReplies.0.body', $reply['body']));

    $approval->update(['status' => ApprovalRequestStatus::Approved]);
    $this->actingAs($this->owner)->post($url, $reply)->assertSessionHasErrors('body');
});

test('assistant tools can update the original records and reply using the open clarification', function () {
    $approval = fundingRequestForCorrection($this->agreement);
    $phase = $approval->subject;
    $expense = Expense::factory()->for($this->team)->for($this->farm)->create();
    $read = new ListPendingApprovalsTool($this->team, $this->owner);

    $requests = json_decode($read->handle(new ToolRequest(['approval_request_id' => $approval->id])), true);
    expect($requests['approval_requests'][0]['decision_comment'])->toBe('What does the amount cover?');

    $cases = [
        [UpdateFundingRequestTool::class, ['funding_phase_id' => $phase->id, 'requested_amount' => '123888.00'], 'updated'],
        [CorrectExpenseTool::class, ['expense_id' => $expense->id, 'amount' => '1500.25', 'reason' => 'Invoice total'], 'corrected'],
        [ReplyToClarificationTool::class, ['approval_request_id' => $approval->id, 'body' => 'Pipes, fuel and installation.'], 'replied'],
    ];

    foreach ($cases as [$toolClass, $arguments, $successKey]) {
        $tool = new $toolClass($this->team, $this->owner);
        $input = new ToolRequest($arguments);

        expect($tool->shouldRequestApproval($input))->toBeInstanceOf(Approval::class)
            ->and(json_decode((new $toolClass($this->team, $this->operator))->handle($input), true))->toHaveKey('error')
            ->and(json_decode($tool->handle($input), true))->toHaveKey($successKey, true);
    }

    expect($approval->fresh()->requested_amount_minor)->toBe(123_888_00)
        ->and($expense->fresh()->amount_minor)->toBe(150_025)
        ->and(InvestorComment::query()->sole()->subject->is($approval))->toBeTrue();
});

test('expense search finds older records in the current workspace in both the page and assistant', function () {
    $oldExpense = Expense::factory()->for($this->team)->for($this->farm)->create([
        'incurred_on' => '2026-01-01', 'description' => 'PVC pipes, fuel and workmanship',
    ]);
    Expense::factory()->for($this->team)->for($this->farm)->count(25)->create([
        'incurred_on' => '2026-11-01', 'description' => 'Recent feed purchase',
    ]);
    Expense::factory()->create(['description' => $oldExpense->description]);

    $this->actingAs($this->owner)->get(route('finance.expenses.index', $this->team))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('expenses', 25));
    $this->get(route('finance.expenses.index', ['current_team' => $this->team, 'search' => 'PVC pipes']))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('expenses', 1)
            ->where('expenses.0.id', $oldExpense->id)
            ->where('expenses.0.canCorrect', true));

    $tool = new GetFinanceSummaryTool($this->team, $this->owner);
    $result = json_decode($tool->handle(new ToolRequest(['record_search' => 'PVC pipes'])), true);
    expect(array_column($result['recent_expenses'], 'id'))->toBe([$oldExpense->id]);

    $result = json_decode($tool->handle(new ToolRequest(['record_search' => (string) $oldExpense->id])), true);
    expect(array_column($result['recent_expenses'], 'id'))->toBe([$oldExpense->id]);
});

test('correcting an expense under clarification preserves the request and its discussion', function () {
    $expense = Expense::factory()->for($this->team)->for($this->farm)->for($this->agreement)->create([
        'amount_minor' => 25_000_00, 'investor_visibility_status' => InvestorVisibilityStatus::ClarificationRequested,
    ]);
    $approval = ApprovalRequest::factory()->for($this->team)->for($this->agreement)->for($expense, 'subject')->create([
        'request_type' => ApprovalRequestType::Expense,
        'status' => ApprovalRequestStatus::ClarificationRequested,
        'decision_comment' => 'Can you check the amount?',
        'requested_amount_minor' => $expense->amount_minor,
    ]);
    $token = $approval->reviewToken();
    $this->actingAs($this->owner)->patch(route('finance.expenses.update', [$this->team, $expense]), [
        'amount' => '23750.00', 'reason' => 'Corrected the sum of three line items.',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $this->actingAs($this->owner)->post(route('investors.approvals.replies.store', [$this->team, $approval]), [
        'body' => 'The three line items total 23,750.',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($expense->approvalRequests()->sole()->is($approval))->toBeTrue()
        ->and($approval->fresh()->requested_amount_minor)->toBe(23_750_00)
        ->and($approval->fresh()->decision_comment)->toBe('Can you check the amount?')
        ->and($approval->fresh()->reviewToken())->not->toBe($token)
        ->and($approval->clarificationReplies()->count())->toBe(1);
});

test('an expense correction preserves an open overrun discussion until the overrun clears', function () {
    $budget = Budget::factory()->for($this->team)->for($this->farm)->create();
    $line = BudgetLine::factory()->for($budget)->create(['planned_amount_minor' => 20_000_00]);
    $expense = Expense::factory()->for($this->team)->for($this->farm)->for($this->agreement)
        ->for($budget)->for($line)->for($line->expenseCategory)->create([
            'amount_minor' => 25_000_00, 'investor_visibility_status' => InvestorVisibilityStatus::ClarificationRequested,
        ]);
    $approval = ApprovalRequest::factory()->for($this->team)->for($this->agreement)->for($expense, 'subject')->create([
        'request_type' => ApprovalRequestType::BudgetOverrun,
        'status' => ApprovalRequestStatus::ClarificationRequested,
        'decision_comment' => 'Can you explain the overrun?',
        'requested_amount_minor' => 5_000_00,
    ]);
    $updateUrl = route('finance.expenses.update', [$this->team, $expense]);
    $replyUrl = route('investors.approvals.replies.store', [$this->team, $approval]);

    $this->actingAs($this->owner)->post($replyUrl, ['body' => 'I am checking the receipt.'])
        ->assertRedirect()->assertSessionHasNoErrors();
    $this->patch($updateUrl, ['amount' => '23000.00', 'reason' => 'Updated the amount from the receipt.'])
        ->assertRedirect()->assertSessionHasNoErrors();

    $overrunRequests = $expense->approvalRequests()->where('request_type', ApprovalRequestType::BudgetOverrun);
    expect($overrunRequests->sole()->is($approval))->toBeTrue()
        ->and($approval->fresh()->requested_amount_minor)->toBe(3_000_00)
        ->and($approval->fresh()->status)->toBe(ApprovalRequestStatus::ClarificationRequested)
        ->and($approval->fresh()->decision_comment)->toBe('Can you explain the overrun?');

    $this->post($replyUrl, ['body' => 'The corrected overrun is 3,000.'])->assertRedirect()->assertSessionHasNoErrors();
    expect($approval->clarificationReplies()->count())->toBe(2);

    $this->patch($updateUrl, ['amount' => '20000.00', 'reason' => 'Removed a line listed on a different receipt.'])
        ->assertRedirect()->assertSessionHasNoErrors();
    expect($overrunRequests->sole()->status)->toBe(ApprovalRequestStatus::Cancelled)
        ->and($approval->clarificationReplies()->count())->toBe(2);
});

test('assistant finance edits require a change and return validation errors without mutating records', function () {
    $approval = fundingRequestForCorrection($this->agreement);
    $expense = Expense::factory()->for($this->team)->for($this->farm)->create();

    $funding = new UpdateFundingRequestTool($this->team, $this->owner);
    $expenseCorrection = new CorrectExpenseTool($this->team, $this->owner);
    $reply = new ReplyToClarificationTool($this->team, $this->owner);

    $phaseId = $approval->subject_id;
    $unchangedFunding = json_decode($funding->handle(new ToolRequest(['funding_phase_id' => $phaseId])), true);
    $unchangedExpense = json_decode($expenseCorrection->handle(new ToolRequest(['expense_id' => $expense->id, 'reason' => 'Receipt review'])), true);
    expect($unchangedFunding['error'])->toBe('Provide at least one funding request detail to change.')
        ->and($unchangedExpense['error'])->toBe('Provide at least one expense detail to correct.');

    $badFunding = json_decode($funding->handle(new ToolRequest(['funding_phase_id' => $phaseId, 'requested_amount' => '0'])), true);
    $badExpense = json_decode($expenseCorrection->handle(new ToolRequest(['expense_id' => $expense->id, 'amount' => '25'])), true);
    $emptyReply = json_decode($reply->handle(new ToolRequest(['approval_request_id' => $approval->id, 'body' => ''])), true);

    expect($badFunding['fields'])->toHaveKey('requested_amount')
        ->and($badExpense['fields'])->toHaveKey('reason')
        ->and($emptyReply['fields'])->toHaveKey('body');
    expect($approval->fresh()->requested_amount_minor)->toBe(123_000_00)
        ->and($expense->fresh()->amount_minor)->toBe($expense->amount_minor)
        ->and($approval->clarificationReplies()->count())->toBe(0);
});

test('assistant corrections and clarification replies reject records from another workspace', function () {
    $approval = fundingRequestForCorrection(InvestorAgreement::factory()->create());
    $expense = Expense::factory()->create();

    $cases = [
        [new UpdateFundingRequestTool($this->team, $this->owner), ['funding_phase_id' => $approval->subject_id, 'requested_amount' => '100.00']],
        [new CorrectExpenseTool($this->team, $this->owner), ['expense_id' => $expense->id, 'amount' => '1500.00', 'reason' => 'Invoice']],
        [new ReplyToClarificationTool($this->team, $this->owner), ['approval_request_id' => $approval->id, 'body' => 'See the receipt.']],
    ];

    foreach ($cases as [$tool, $arguments]) {
        $result = json_decode($tool->handle(new ToolRequest($arguments)), true);
        expect($result)->toBe(['error' => 'Record not found in this workspace.']);
    }
});

test('correcting a private expense updates the open overrun on another expense in the budget line', function () {
    $budget = Budget::factory()->for($this->team)->for($this->farm)->create();
    $line = BudgetLine::factory()->for($budget)->create(['planned_amount_minor' => 10_000]);
    $privateExpense = Expense::factory()->for($this->team)->for($this->farm)
        ->for($budget)->for($line)->for($line->expenseCategory)->create([
            'amount_minor' => 10_000, 'investor_agreement_id' => null,
        ]);
    $investorExpense = Expense::factory()->for($this->team)->for($this->farm)->for($this->agreement)
        ->for($budget)->for($line)->for($line->expenseCategory)->create([
            'amount_minor' => 5_000, 'investor_visibility_status' => InvestorVisibilityStatus::ClarificationRequested,
        ]);
    $approval = ApprovalRequest::factory()->for($this->team)->for($this->agreement)->for($investorExpense, 'subject')->create([
        'request_type' => ApprovalRequestType::BudgetOverrun,
        'status' => ApprovalRequestStatus::ClarificationRequested,
        'decision_comment' => 'Why did this line exceed its budget?',
        'requested_amount_minor' => 5_000,
    ]);
    $decidedApproval = ApprovalRequest::factory()->for($this->team)->for($this->agreement)->for($investorExpense, 'subject')->create([
        'request_type' => ApprovalRequestType::BudgetOverrun, 'status' => ApprovalRequestStatus::Approved,
        'requested_amount_minor' => 5_000, 'approved_amount_minor' => 5_000,
    ]);
    $otherLine = BudgetLine::factory()->for($budget)->create(['planned_amount_minor' => 10_000]);
    $unrelatedExpense = Expense::factory()->for($this->team)->for($this->farm)->for($this->agreement)
        ->for($budget)->for($otherLine, 'budgetLine')->for($otherLine->expenseCategory)->create(['amount_minor' => 15_000]);
    $unrelatedApproval = ApprovalRequest::factory()->for($this->team)->for($this->agreement)->for($unrelatedExpense, 'subject')->create([
        'request_type' => ApprovalRequestType::BudgetOverrun, 'requested_amount_minor' => 5_000,
    ]);

    $correctionUrl = route('finance.expenses.update', [$this->team, $privateExpense]);
    $replyUrl = route('investors.approvals.replies.store', [$this->team, $approval]);
    $decisionUrl = route('investors.approvals.decisions.store', [$this->team, $approval]);
    $previousToken = $approval->reviewToken();

    $this->actingAs($this->owner)->post($replyUrl, ['body' => 'I am checking the line items.'])
        ->assertRedirect()->assertSessionHasNoErrors();
    $this->patch($correctionUrl, ['amount' => '75.00', 'reason' => 'Reconciled the private receipt.'])
        ->assertRedirect()->assertSessionHasNoErrors();

    expect($approval->fresh()->requested_amount_minor)->toBe(2_500)
        ->and($approval->fresh()->status)->toBe(ApprovalRequestStatus::ClarificationRequested)
        ->and($approval->fresh()->decision_comment)->toBe('Why did this line exceed its budget?')
        ->and($approval->clarificationReplies()->count())->toBe(1);

    $this->actingAs($this->investor)->post($decisionUrl, ['status' => 'approved', 'review_token' => $previousToken])
        ->assertSessionHasErrors('status');
    $this->actingAs($this->owner)->patch($correctionUrl, ['amount' => '50.00', 'reason' => 'Removed a duplicate line item.'])
        ->assertRedirect()->assertSessionHasNoErrors();
    $this->actingAs($this->investor)->post($decisionUrl, ['status' => 'approved'])->assertSessionHasErrors('status');

    expect($approval->fresh()->status)->toBe(ApprovalRequestStatus::Cancelled)
        ->and($approval->clarificationReplies()->count())->toBe(1)
        ->and($unrelatedApproval->fresh()->status)->toBe(ApprovalRequestStatus::Pending)
        ->and($unrelatedApproval->fresh()->requested_amount_minor)->toBe(5_000)
        ->and($decidedApproval->fresh()->status)->toBe(ApprovalRequestStatus::Approved)
        ->and($decidedApproval->fresh()->approved_amount_minor)->toBe(5_000)
        ->and($privateExpense->approvalRequests()->count())->toBe(0);
});

test('clearing a budget overrun restores the related expense visibility from its remaining approvals or original sharing choice', function (
    InvestorVisibilityStatus $recordedVisibility,
    ?ApprovalRequestStatus $remainingApproval,
    InvestorVisibilityStatus $expectedVisibility,
) {
    $budget = Budget::factory()->for($this->team)->for($this->farm)->create();
    $line = BudgetLine::factory()->for($budget)->create(['planned_amount_minor' => 10_000]);
    $privateExpense = Expense::factory()->for($this->team)->for($this->farm)
        ->for($budget)->for($line)->for($line->expenseCategory)->create(['amount_minor' => 10_000]);
    $expense = app(RecordExpense::class)->handle($this->team, $this->owner, [
        'farm_id' => $this->farm->id,
        'budget_id' => $budget->id,
        'budget_line_id' => $line->id,
        'expense_category_id' => $line->expense_category_id,
        'investor_agreement_id' => $this->agreement->id,
        'amount_minor' => 5_000,
        'description' => 'Investor expense on the same budget line',
        'incurred_on' => '2026-10-15',
        'investor_visibility_status' => $recordedVisibility->value,
    ]);

    $overrun = $expense->approvalRequests()->sole();
    expect($overrun->request_type)->toBe(ApprovalRequestType::BudgetOverrun)
        ->and($expense->investor_visibility_status)->toBe(InvestorVisibilityStatus::PendingApproval);
    if ($remainingApproval !== null) {
        ApprovalRequest::factory()->for($this->team)->for($this->agreement)->for($expense, 'subject')->create([
            'request_type' => ApprovalRequestType::Expense, 'status' => $remainingApproval,
        ]);
    }

    $this->actingAs($this->owner)->patch(route('finance.expenses.update', [$this->team, $privateExpense]), [
        'amount' => '50.00', 'reason' => 'Removed a duplicate charge.',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($overrun->fresh()->status)->toBe(ApprovalRequestStatus::Cancelled)
        ->and($expense->fresh()->investor_visibility_status)->toBe($expectedVisibility);

    $this->actingAs($this->investor)->get(route('investor-portal.index', $this->team))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('agreements.0.expenses', $expectedVisibility === InvestorVisibilityStatus::Approved ? 1 : 0));
})->with([
    'approved for sharing when recorded' => [InvestorVisibilityStatus::Approved, null, InvestorVisibilityStatus::Approved],
    'recorded as private' => [InvestorVisibilityStatus::Private, null, InvestorVisibilityStatus::Private],
    'expense approval remains pending' => [InvestorVisibilityStatus::Approved, ApprovalRequestStatus::Pending, InvestorVisibilityStatus::PendingApproval],
    'expense clarification remains open' => [InvestorVisibilityStatus::Approved, ApprovalRequestStatus::ClarificationRequested, InvestorVisibilityStatus::ClarificationRequested],
    'expense was rejected' => [InvestorVisibilityStatus::Approved, ApprovalRequestStatus::Rejected, InvestorVisibilityStatus::Rejected],
]);

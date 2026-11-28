<?php

use App\Actions\Finance\RecordExpense;
use App\Ai\Agents\FarmwellAssistant;
use App\Ai\Tools\AskUserInputTool;
use App\Ai\Tools\CorrectExpenseTool;
use App\Ai\Tools\CreateFundingPhaseTool;
use App\Ai\Tools\DecideApprovalRequestTool;
use App\Ai\Tools\GetFinanceSummaryTool;
use App\Ai\Tools\GetInvestorSummaryTool;
use App\Ai\Tools\GetWorkspaceOverviewTool;
use App\Ai\Tools\ListPendingApprovalsTool;
use App\Ai\Tools\RecordDailyLogTool;
use App\Ai\Tools\RecordExpenseTool;
use App\Ai\Tools\RecordFarmActivityTool;
use App\Ai\Tools\RecordFundingReleaseTool;
use App\Ai\Tools\ReplyToClarificationTool;
use App\Ai\Tools\UpdateAgreementFundingTool;
use App\Ai\Tools\UpdateFundingRequestTool;
use App\Enums\ApprovalRequestStatus;
use App\Enums\FarmTaskStatus;
use App\Enums\FarmType;
use App\Enums\FundingPhaseStatus;
use App\Enums\PlatformRole;
use App\Enums\ProductionCycleStatus;
use App\Enums\TeamRole;
use App\Models\ApprovalRequest;
use App\Models\AuditEvent;
use App\Models\DistributionRecord;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FarmTask;
use App\Models\FundingPhase;
use App\Models\InventoryItem;
use App\Models\InvestorAgreement;
use App\Models\ProductionCycle;
use App\Models\ProductionLog;
use App\Models\Team;
use App\Models\User;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Approvals\PendingApproval;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Exceptions\ApprovalMismatchException;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Tools\Request as ToolRequest;

function attachTeamMember(Team $team, TeamRole $role = TeamRole::Owner): User
{
    $user = User::factory()->create();
    $team->members()->attach($user, ['role' => $role->value]);
    $user->switchTeam($team);

    return $user;
}

/**
 * @return array<int, array<string, mixed>>
 */
function parseSsePayloads(TestResponse $response): array
{
    $body = $response->streamedContent();
    $events = [];

    foreach (preg_split("/\r?\n\r?\n/", trim($body)) ?: [] as $frame) {
        foreach (explode("\n", $frame) as $line) {
            if (! str_starts_with($line, 'data:')) {
                continue;
            }

            $raw = trim(substr($line, 5));
            if ($raw === '' || $raw === '[DONE]') {
                continue;
            }

            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $events[] = $decoded;
            }
        }
    }

    return $events;
}

test('assistant routes enforce workspace plan and block read-only platform admin sessions', function () {
    $starterTeam = Team::factory()->starter()->create();
    $starterOwner = attachTeamMember($starterTeam, TeamRole::Owner);

    $this->actingAs($starterOwner);
    $this->getJson(route('assistant.index', $starterTeam))->assertForbidden();
    $this->postJson(route('assistant.messages.store', $starterTeam), ['message' => 'Hello'])->assertForbidden();
    $this->postJson(route('assistant.approvals.store', [$starterTeam, Str::uuid()->toString()]), [
        'decisions' => ['call_1' => ['action' => 'approve']],
    ])->assertForbidden();
    $this->deleteJson(route('assistant.conversations.destroy', [$starterTeam, Str::uuid()->toString()]))->assertForbidden();

    $proTeam = Team::factory()->pro()->create();
    $admin = User::factory()->create(['platform_role' => PlatformRole::SuperAdmin]);

    $this->actingAs($admin);
    $this->post(route('platform.access.store'), [
        'team_id' => $proTeam->id,
        'reason' => 'Audit workspace',
    ])->assertRedirect(route('dashboard', $proTeam));

    $this->getJson(route('assistant.index', $proTeam))->assertForbidden();
    $this->postJson(route('assistant.messages.store', $proTeam), ['message' => 'Hello'])->assertForbidden();
    $this->postJson(route('assistant.approvals.store', [$proTeam, Str::uuid()->toString()]), [
        'decisions' => ['call_1' => ['action' => 'approve']],
    ])->assertForbidden();
    $this->deleteJson(route('assistant.conversations.destroy', [$proTeam, Str::uuid()->toString()]))->assertForbidden();
});

test('assistant instructions and tools adapt strictly to user role', function () {
    $team = Team::factory()->create(['name' => 'Golden Acre']);
    $owner = attachTeamMember($team, TeamRole::Owner);
    $operator = attachTeamMember($team, TeamRole::Operator);
    $investor = attachTeamMember($team, TeamRole::Investor);
    $otherInvestor = attachTeamMember($team, TeamRole::Investor);

    $farm = Farm::factory()->for($team)->create(['name' => 'Pond North', 'farm_type' => FarmType::Aquaculture]);
    ExpenseCategory::factory()->for($team)->create(['name' => 'Fish Feed']);

    InvestorAgreement::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'investor_id' => $investor->id,
        'title' => 'Investor Visible Agreement',
    ]);
    InvestorAgreement::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'investor_id' => $otherInvestor->id,
        'title' => 'Private Other Investor Agreement',
    ]);

    $ownerAssistant = new FarmwellAssistant($team, $owner);
    $ownerTools = array_map('get_class', [...$ownerAssistant->tools()]);
    expect($ownerTools)->toEqualCanonicalizing([
        AskUserInputTool::class,
        GetWorkspaceOverviewTool::class,
        GetFinanceSummaryTool::class,
        GetInvestorSummaryTool::class,
        ListPendingApprovalsTool::class,
        RecordExpenseTool::class,
        CorrectExpenseTool::class,
        CreateFundingPhaseTool::class,
        RecordFundingReleaseTool::class,
        ReplyToClarificationTool::class,
        UpdateAgreementFundingTool::class,
        UpdateFundingRequestTool::class,
        RecordDailyLogTool::class,
        RecordFarmActivityTool::class,
    ]);

    $operatorAssistant = new FarmwellAssistant($team, $operator);
    expect(array_map('get_class', [...$operatorAssistant->tools()]))->toEqualCanonicalizing([
        AskUserInputTool::class,
        GetWorkspaceOverviewTool::class,
        RecordDailyLogTool::class,
        RecordFarmActivityTool::class,
    ])->and($operatorAssistant->instructions())
        ->not->toContain('Investor Visible Agreement')
        ->not->toContain('Fish Feed');

    $investorAssistant = new FarmwellAssistant($team, $investor);
    $investorTools = [...$investorAssistant->tools()];
    expect(array_map('get_class', $investorTools))->toEqualCanonicalizing([
        AskUserInputTool::class,
        GetInvestorSummaryTool::class,
        ListPendingApprovalsTool::class,
        DecideApprovalRequestTool::class,
    ])->and($investorAssistant->instructions())
        ->toContain('Investor Visible Agreement')
        ->not->toContain('Private Other Investor Agreement')
        ->not->toContain('Fish Feed');

    $schemaFactory = new JsonSchemaTypeFactory;
    foreach ([...$ownerAssistant->tools(), ...$investorTools] as $tool) {
        expect($tool)->toBeInstanceOf(Tool::class)
            ->and($tool->description())->toBeString()->not->toBeEmpty()
            ->and($tool->schema($schemaFactory))->toBeArray();
    }

    $this->actingAs($owner)
        ->getJson(route('assistant.index', $team))
        ->assertOk()
        ->assertJsonPath('starterPrompts.0', 'What is our current funding balance and recent spend?')
        ->assertJsonPath('starterPrompts.1', 'Request a funding release for this farm');

    expect($ownerAssistant->instructions())
        ->toContain('Approve and Decline buttons')
        ->toContain('AskUserInputTool');

    $this->actingAs($operator)
        ->getJson(route('assistant.index', $team))
        ->assertOk()
        ->assertJsonPath('starterPrompts.0', 'Give me a quick overview of active cycles, stock, and open tasks');

    $this->actingAs($investor)
        ->getJson(route('assistant.index', $team))
        ->assertOk()
        ->assertJsonPath('starterPrompts.0', 'Summarize my investment balance and recorded funding');
});

test('read tools enforce authorization and return scoped workspace, finance, and investor data', function () {
    $team = Team::factory()->create();
    $owner = attachTeamMember($team, TeamRole::Owner);
    $operator = attachTeamMember($team, TeamRole::Operator);
    $investor = attachTeamMember($team, TeamRole::Investor);
    $otherInvestor = attachTeamMember($team, TeamRole::Investor);

    $farm = Farm::factory()->for($team)->create(['name' => 'Layer House', 'farm_type' => FarmType::Poultry]);
    $cycle = ProductionCycle::factory()->for($farm)->create([
        'team_id' => $team->id,
        'status' => ProductionCycleStatus::Active,
        'current_stock_count' => 1200,
        'initial_stock_count' => 1250,
    ]);
    InventoryItem::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'name' => 'Starter Mash',
        'current_quantity' => 3,
        'reorder_level' => 5,
    ]);
    FarmTask::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'production_cycle_id' => $cycle->id,
        'title' => 'Water line flush',
        'status' => FarmTaskStatus::Planned,
    ]);

    $category = ExpenseCategory::factory()->for($team)->create(['name' => 'Feed']);
    $agreement = InvestorAgreement::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'investor_id' => $investor->id,
        'title' => 'Layer Capital Deal',
        'amount_committed_minor' => 500_000_00,
        'amount_funded_minor' => 250_000_00,
    ]);
    $otherAgreement = InvestorAgreement::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'investor_id' => $otherInvestor->id,
        'title' => 'Other Investor Deal',
    ]);

    FundingPhase::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'investor_agreement_id' => $agreement->id,
        'status' => FundingPhaseStatus::ReleasedExternally,
        'externally_released_amount_minor' => 250_000_00,
        'approved_amount_minor' => 250_000_00,
        'requested_amount_minor' => 250_000_00,
    ]);
    app(RecordExpense::class)->handle($team, $owner, [
        'farm_id' => $farm->id,
        'expense_category_id' => $category->id,
        'investor_agreement_id' => $agreement->id,
        'incurred_on' => now()->toDateString(),
        'description' => 'Maize delivery',
        'amount_minor' => 50_000_00,
        'investor_visibility_status' => 'approved',
    ]);
    DistributionRecord::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'investor_agreement_id' => $agreement->id,
        'capital_recovered_minor' => 25_000_00,
    ]);

    ApprovalRequest::factory()->create([
        'team_id' => $team->id,
        'investor_agreement_id' => $agreement->id,
        'status' => ApprovalRequestStatus::Pending,
        'requested_amount_minor' => 100_000_00,
    ]);
    ApprovalRequest::factory()->create([
        'team_id' => $team->id,
        'investor_agreement_id' => $otherAgreement->id,
        'status' => ApprovalRequestStatus::Pending,
    ]);

    $overview = json_decode((string) (new GetWorkspaceOverviewTool($team, $operator))->handle(new ToolRequest(['farm_id' => $farm->id])), true);
    expect($overview['farms'])->toHaveCount(1)
        ->and($overview['cycles'])->toHaveCount(1)
        ->and($overview['low_stock_items'][0]['name'])->toBe('Starter Mash')
        ->and($overview['open_tasks'][0]['title'])->toBe('Water line flush');

    $deniedOverview = json_decode((string) (new GetWorkspaceOverviewTool($team, $investor))->handle(new ToolRequest), true);
    expect($deniedOverview)->toHaveKey('error');

    $finance = json_decode((string) (new GetFinanceSummaryTool($team, $owner))->handle(new ToolRequest(['farm_id' => $farm->id])), true);
    expect($finance['totals']['released'])->toBe('250000.00')
        ->and($finance['totals']['spent'])->toBe('50000.00')
        ->and($finance['totals']['balance'])->toBe('200000.00')
        ->and($finance['recent_expenses'])->toHaveCount(1)
        ->and($finance['funding_phases'])->toHaveCount(1);

    expect(json_decode((string) (new GetFinanceSummaryTool($team, $operator))->handle(new ToolRequest), true))->toHaveKey('error');

    $ownerInvestors = json_decode((string) (new GetInvestorSummaryTool($team, $owner))->handle(new ToolRequest), true);
    expect($ownerInvestors['agreements'])->toHaveCount(2);

    $scopedInvestor = json_decode((string) (new GetInvestorSummaryTool($team, $investor))->handle(new ToolRequest), true);
    expect($scopedInvestor['agreements'])->toHaveCount(1)
        ->and($scopedInvestor['agreements'][0]['title'])->toBe('Layer Capital Deal')
        ->and($scopedInvestor['agreements'][0]['capital_recovered'])->toBe('25000.00')
        ->and($scopedInvestor['agreements'][0]['balance'])->toBe('200000.00');

    expect(json_decode((string) (new GetInvestorSummaryTool($team, $operator))->handle(new ToolRequest), true))->toHaveKey('error');

    $investorApprovals = json_decode((string) (new ListPendingApprovalsTool($team, $investor))->handle(new ToolRequest), true);
    expect($investorApprovals['approval_requests'])->toHaveCount(1)
        ->and($investorApprovals['approval_requests'][0]['agreement_id'])->toBe($agreement->id);

    expect(json_decode((string) (new ListPendingApprovalsTool($team, $operator))->handle(new ToolRequest), true))->toHaveKey('error');
});

test('write tools require approval, validate authorization and inputs, write records, and log assistant audits', function () {
    $team = Team::factory()->pro()->create();
    $otherTeam = Team::factory()->pro()->create();
    $owner = attachTeamMember($team, TeamRole::Owner);
    $operator = attachTeamMember($team, TeamRole::Operator);
    $investor = attachTeamMember($team, TeamRole::Investor);

    $farm = Farm::factory()->for($team)->create(['farm_type' => FarmType::Poultry]);
    $otherFarm = Farm::factory()->for($otherTeam)->create();
    $cycle = ProductionCycle::factory()->for($farm)->create(['team_id' => $team->id]);
    $otherCycle = ProductionCycle::factory()->for($otherFarm)->create(['team_id' => $otherTeam->id]);
    $category = ExpenseCategory::factory()->for($team)->create(['name' => 'Vaccines']);
    $inventoryItem = InventoryItem::factory()->create(['team_id' => $team->id, 'farm_id' => $farm->id]);
    $otherInventoryItem = InventoryItem::factory()->create(['team_id' => $otherTeam->id, 'farm_id' => $otherFarm->id]);

    $agreement = InvestorAgreement::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'investor_id' => $investor->id,
        'amount_funded_minor' => 100_000_00,
    ]);
    $otherAgreement = InvestorAgreement::factory()->create([
        'team_id' => $otherTeam->id,
        'farm_id' => $otherFarm->id,
    ]);

    // 1. RecordExpenseTool
    $expenseTool = new RecordExpenseTool($team, $owner);
    expect($expenseTool->shouldRequestApproval(new ToolRequest(['amount' => '1500.50', 'incurred_on' => '2026-11-27', 'description' => 'Vaccine'])))->toBeInstanceOf(Approval::class);
    expect(json_decode((string) (new RecordExpenseTool($team, $operator))->handle(new ToolRequest), true))->toHaveKey('error');
    expect(json_decode((string) $expenseTool->handle(new ToolRequest(['amount' => ['bad'], 'incurred_on' => '2026-02-30', 'description' => ''])), true))->toHaveKey('error');
    expect(json_decode((string) $expenseTool->handle(new ToolRequest(['farm_id' => $otherFarm->id, 'expense_category_id' => $category->id, 'amount' => '100', 'incurred_on' => '2026-11-27', 'description' => 'Test'])), true))->toHaveKey('error');
    expect(json_decode((string) $expenseTool->handle(new ToolRequest(['farm_id' => $farm->id, 'expense_category_id' => $category->id, 'production_cycle_id' => $otherCycle->id, 'amount' => '100', 'incurred_on' => '2026-11-27', 'description' => 'Test'])), true))->toHaveKey('error');
    expect(json_decode((string) $expenseTool->handle(new ToolRequest(['farm_id' => $farm->id, 'expense_category_id' => $category->id, 'investor_agreement_id' => $otherAgreement->id, 'amount' => '100', 'incurred_on' => '2026-11-27', 'description' => 'Test'])), true))->toHaveKey('error');

    $recordedExpense = json_decode((string) $expenseTool->handle(new ToolRequest([
        'farm_id' => $farm->id,
        'expense_category_id' => $category->id,
        'production_cycle_id' => $cycle->id,
        'investor_agreement_id' => $agreement->id,
        'amount' => '1500.50',
        'incurred_on' => '2026-11-27',
        'description' => 'Lasota booster',
        'vendor' => 'VetCare',
        'investor_visibility_status' => 'invalid-status',
    ])), true);
    expect($recordedExpense['recorded'])->toBeTrue()
        ->and(Expense::query()->find($recordedExpense['expense_id'])?->vendor)->toBe('VetCare');

    // 2. CreateFundingPhaseTool
    $phaseTool = new CreateFundingPhaseTool($team, $owner);
    expect($phaseTool->shouldRequestApproval(new ToolRequest(['name' => 'Phase 2', 'requested_amount' => '2000'])))->toBeInstanceOf(Approval::class);
    expect(json_decode((string) (new CreateFundingPhaseTool($team, $operator))->handle(new ToolRequest), true))->toHaveKey('error');
    expect(json_decode((string) $phaseTool->handle(new ToolRequest(['name' => '', 'requested_amount' => '-10'])), true))->toHaveKey('error');
    expect(json_decode((string) $phaseTool->handle(new ToolRequest(['farm_id' => $farm->id, 'investor_agreement_id' => $otherAgreement->id, 'name' => 'P2', 'requested_amount' => '2000'])), true))->toHaveKey('error');
    expect(json_decode((string) $phaseTool->handle(new ToolRequest(['farm_id' => $farm->id, 'investor_agreement_id' => $agreement->id, 'production_cycle_id' => $otherCycle->id, 'name' => 'P2', 'requested_amount' => '2000'])), true))->toHaveKey('error');
    expect(json_decode((string) $phaseTool->handle(new ToolRequest(['farm_id' => $farm->id, 'investor_agreement_id' => $agreement->id, 'name' => 'P2', 'requested_amount' => '2000', 'expected_on' => 12345])), true))->toHaveKey('error');

    $createdPhase = json_decode((string) $phaseTool->handle(new ToolRequest([
        'farm_id' => $farm->id,
        'investor_agreement_id' => $agreement->id,
        'production_cycle_id' => $cycle->id,
        'name' => 'Brooding Tranche',
        'requested_amount' => '25000.00',
        'expected_on' => '2026-12-01',
    ])), true);
    expect($createdPhase['created'])->toBeTrue();
    $phase = FundingPhase::query()->findOrFail($createdPhase['funding_phase_id']);
    $phaseApproval = ApprovalRequest::query()->where('subject_id', $phase->id)->firstOrFail();

    // 3. DecideApprovalRequestTool (Investor approves the funding phase request)
    $decideTool = new DecideApprovalRequestTool($team, $investor);
    expect($decideTool->shouldRequestApproval(new ToolRequest(['approval_request_id' => $phaseApproval->id, 'decision' => 'approved'])))->toBeInstanceOf(Approval::class);
    expect(json_decode((string) (new DecideApprovalRequestTool($team, $owner))->handle(new ToolRequest), true))->toHaveKey('error');
    expect(json_decode((string) $decideTool->handle(new ToolRequest(['approval_request_id' => $phaseApproval->id, 'decision' => 'invalid'])), true))->toHaveKey('error');
    expect(json_decode((string) (new DecideApprovalRequestTool($otherTeam, $investor))->handle(new ToolRequest(['approval_request_id' => $phaseApproval->id, 'decision' => 'approved'])), true))->toHaveKey('error');
    expect(json_decode((string) $decideTool->handle(new ToolRequest(['approval_request_id' => 999999, 'decision' => 'approved'])), true))->toHaveKey('error');
    expect(json_decode((string) $decideTool->handle(new ToolRequest(['approval_request_id' => $phaseApproval->id, 'decision' => 'approved', 'approved_amount' => '0'])), true))->toHaveKey('error');

    $decisionResult = json_decode((string) $decideTool->handle(new ToolRequest([
        'approval_request_id' => $phaseApproval->id,
        'decision' => 'approved',
        'approved_amount' => '25000.00',
        'comment' => 'Approved via assistant',
    ])), true);
    expect($decisionResult['decided'])->toBeTrue()
        ->and($decisionResult['status'])->toBe('approved');
    expect(json_decode((string) $decideTool->handle(new ToolRequest([
        'approval_request_id' => $phaseApproval->id,
        'decision' => 'rejected',
    ])), true))->toHaveKey('error');

    // 4. RecordFundingReleaseTool
    $releaseTool = new RecordFundingReleaseTool($team, $owner);
    expect($releaseTool->shouldRequestApproval(new ToolRequest(['funding_phase_id' => $phase->id, 'amount' => '25000.00'])))->toBeInstanceOf(Approval::class);
    expect(json_decode((string) (new RecordFundingReleaseTool($team, $operator))->handle(new ToolRequest), true))->toHaveKey('error');
    expect(json_decode((string) $releaseTool->handle(new ToolRequest(['amount' => '0', 'released_on' => 'bad'])), true))->toHaveKey('error');
    expect(json_decode((string) $releaseTool->handle(new ToolRequest(['funding_phase_id' => 999999, 'amount' => '100', 'released_on' => '2026-11-27'])), true))->toHaveKey('error');
    expect(json_decode((string) $releaseTool->handle(new ToolRequest(['funding_phase_id' => $phase->id, 'amount' => '999999.00', 'released_on' => '2026-11-27'])), true))->toHaveKey('error');

    $releaseResult = json_decode((string) $releaseTool->handle(new ToolRequest([
        'funding_phase_id' => $phase->id,
        'amount' => '25000.00',
        'released_on' => '2026-11-27',
    ])), true);
    expect($releaseResult['released'])->toBeTrue()
        ->and($agreement->fresh()->amount_funded_minor)->toBe(125_000_00);

    // 5. UpdateAgreementFundingTool
    $fundingTool = new UpdateAgreementFundingTool($team, $owner);
    expect($fundingTool->shouldRequestApproval(new ToolRequest(['investor_agreement_id' => $agreement->id, 'amount_funded' => '150000.00'])))->toBeInstanceOf(Approval::class);
    expect(json_decode((string) (new UpdateAgreementFundingTool($team, $operator))->handle(new ToolRequest), true))->toHaveKey('error');
    expect(json_decode((string) $fundingTool->handle(new ToolRequest(['investor_agreement_id' => $agreement->id, 'amount_funded' => '-5'])), true))->toHaveKey('error');
    expect(json_decode((string) $fundingTool->handle(new ToolRequest(['investor_agreement_id' => $otherAgreement->id, 'amount_funded' => '150000.00'])), true))->toHaveKey('error');

    $updatedFunding = json_decode((string) $fundingTool->handle(new ToolRequest([
        'investor_agreement_id' => $agreement->id,
        'amount_funded' => '150000.00',
    ])), true);
    expect($updatedFunding['updated'])->toBeTrue()
        ->and($agreement->fresh()->amount_funded_minor)->toBe(150_000_00);

    // 6. RecordDailyLogTool
    $dailyLogTool = new RecordDailyLogTool($team, $operator);
    expect($dailyLogTool->shouldRequestApproval(new ToolRequest(['farm_id' => $farm->id, 'log_date' => '2026-11-27'])))->toBeInstanceOf(Approval::class);
    expect(json_decode((string) (new RecordDailyLogTool($team, $investor))->handle(new ToolRequest), true))->toHaveKey('error');
    expect(json_decode((string) $dailyLogTool->handle(new ToolRequest(['log_date' => 'not-a-date'])), true))->toHaveKey('error');
    expect(json_decode((string) $dailyLogTool->handle(new ToolRequest(['farm_id' => $otherFarm->id, 'log_date' => '2026-11-27'])), true))->toHaveKey('error');
    expect(json_decode((string) $dailyLogTool->handle(new ToolRequest(['farm_id' => $farm->id, 'production_cycle_id' => $otherCycle->id, 'log_date' => '2026-11-27'])), true))->toHaveKey('error');
    expect(json_decode((string) $dailyLogTool->handle(new ToolRequest(['farm_id' => $farm->id, 'inventory_item_id' => $otherInventoryItem->id, 'log_date' => '2026-11-27'])), true))->toHaveKey('error');

    $logResult = json_decode((string) $dailyLogTool->handle(new ToolRequest([
        'farm_id' => $farm->id,
        'production_cycle_id' => $cycle->id,
        'inventory_item_id' => $inventoryItem->id,
        'log_date' => '2026-11-27',
        'feed_used_kg' => 12.5,
        'eggs_collected' => 180,
        'eggs_damaged' => 2,
        'notes' => 'Healthy morning lay',
    ])), true);
    expect($logResult['recorded'])->toBeTrue()
        ->and(ProductionLog::query()->find($logResult['production_log_id'])?->eggs_collected)->toBe(180);

    // 7. RecordFarmActivityTool
    $activityTool = new RecordFarmActivityTool($team, $operator);
    expect($activityTool->shouldRequestApproval(new ToolRequest(['activity_type' => 'Vaccination', 'activity_date' => '2026-11-27'])))->toBeInstanceOf(Approval::class);
    expect(json_decode((string) (new RecordFarmActivityTool($team, $investor))->handle(new ToolRequest), true))->toHaveKey('error');
    expect(json_decode((string) $activityTool->handle(new ToolRequest(['activity_date' => '2026-11-27', 'activity_type' => '', 'description' => ''])), true))->toHaveKey('error');
    expect(json_decode((string) $activityTool->handle(new ToolRequest(['farm_id' => $otherFarm->id, 'activity_date' => '2026-11-27', 'activity_type' => 'Feeding', 'description' => 'Done'])), true))->toHaveKey('error');
    expect(json_decode((string) $activityTool->handle(new ToolRequest(['farm_id' => $farm->id, 'production_cycle_id' => $otherCycle->id, 'activity_date' => '2026-11-27', 'activity_type' => 'Feeding', 'description' => 'Done'])), true))->toHaveKey('error');

    $activityResult = json_decode((string) $activityTool->handle(new ToolRequest([
        'farm_id' => $farm->id,
        'production_cycle_id' => $cycle->id,
        'activity_date' => '2026-11-27',
        'activity_type' => 'Vaccination',
        'description' => 'Completed drinking water dose',
        'status' => 'invalid-status',
        'investor_safe_summary' => 'Scheduled vaccination completed.',
    ])), true);
    expect($activityResult['recorded'])->toBeTrue()
        ->and(FarmActivity::query()->find($activityResult['farm_activity_id'])?->investor_safe_summary)->toBe('Scheduled vaccination completed.');

    expect(AuditEvent::query()->where('team_id', $team->id)->where('action', 'like', 'assistant.%')->pluck('action')->all())
        ->toEqualCanonicalizing([
            'assistant.expense_recorded',
            'assistant.funding_phase_requested',
            'assistant.approval_decided',
            'assistant.funding_phase_released',
            'assistant.agreement_funding_updated',
            'assistant.daily_log_recorded',
            'assistant.farm_activity_recorded',
        ]);
});

test('assistant streams messages, handles pending approvals, isolates conversations per membership, and deletes chats', function () {
    $team = Team::factory()->pro()->create();
    $otherTeam = Team::factory()->pro()->create();
    $owner = attachTeamMember($team, TeamRole::Owner);
    $otherMember = attachTeamMember($team, TeamRole::Operator);
    $outsider = attachTeamMember($otherTeam, TeamRole::Owner);

    $pendingApproval = new PendingApproval(
        id: 'call_expense_1',
        tool: 'RecordExpenseTool',
        arguments: ['farm_id' => 1, 'amount' => '5000.00', 'description' => 'Layer feed'],
        reason: 'Record expense of 5000.00: Layer feed',
    );

    $pendingApprovalEdit = new PendingApproval(
        id: 'call_expense_2',
        tool: 'RecordExpenseTool',
        arguments: ['farm_id' => 1, 'amount' => '5000.00', 'description' => 'Layer feed'],
        reason: 'Record expense of 5000.00: Layer feed',
    );
    $pendingApprovalReject = new PendingApproval(
        id: 'call_expense_3',
        tool: 'RecordExpenseTool',
        arguments: ['farm_id' => 1, 'amount' => '5000.00', 'description' => 'Layer feed'],
        reason: 'Record expense of 5000.00: Layer feed',
    );

    FarmwellAssistant::fake([
        AgentResponse::fakeWithPendingApprovals([$pendingApproval]),
        'Recorded your 5000.00 expense.',
        AgentResponse::fakeWithPendingApprovals([$pendingApprovalEdit]),
        'Edited and recorded your expense.',
        AgentResponse::fakeWithPendingApprovals([$pendingApprovalReject]),
        'Cancelled cleanly.',
        fn () => throw new ApprovalMismatchException('Approvals expired', new Collection([$pendingApproval])),
    ]);

    $this->actingAs($owner);

    $streamResponse = $this->postJson(route('assistant.messages.store', $team), [
        'message' => 'Record a 5000 feed expense',
    ])->assertOk();

    $events = parseSsePayloads($streamResponse);
    $approvalEvent = collect($events)->firstWhere('type', 'tool_approval_request');
    $conversationEvent = collect($events)->firstWhere('type', 'conversation');

    expect($approvalEvent)->not->toBeNull()
        ->and($approvalEvent['approvals'][0]['id'])->toBe('call_expense_1')
        ->and($conversationEvent['conversation_id'])->toBeString();

    $conversationId = $conversationEvent['conversation_id'];

    // Blocked while an approval is pending
    $this->postJson(route('assistant.messages.store', $team), [
        'conversation_id' => $conversationId,
        'message' => 'What else?',
    ])->assertStatus(409)
        ->assertJsonPath('pendingApprovals.0.id', 'call_expense_1');

    // History returns messages & pending approval
    $this->getJson(route('assistant.index', ['current_team' => $team, 'conversation_id' => $conversationId]))
        ->assertOk()
        ->assertJsonPath('activeConversationId', $conversationId)
        ->assertJsonPath('pendingApprovals.0.id', 'call_expense_1');

    // Cross-member in same team & outsider in other team both get 404
    $this->actingAs($otherMember)
        ->getJson(route('assistant.index', ['current_team' => $team, 'conversation_id' => $conversationId]))
        ->assertNotFound();

    $this->actingAs($outsider)
        ->postJson(route('assistant.approvals.store', [$otherTeam, $conversationId]), [
            'decisions' => ['call_expense_1' => ['action' => 'approve']],
        ])->assertNotFound();

    // Owner resolves with approve, then resets a pending approval in DB to exercise edit & reject & error streams cleanly
    $this->actingAs($owner);
    $approveStream = $this->postJson(route('assistant.approvals.store', [$team, $conversationId]), [
        'decisions' => ['call_expense_1' => ['action' => 'approve']],
    ])->assertOk();
    expect(collect(parseSsePayloads($approveStream))->pluck('type')->all())->toContain('text_delta', 'conversation');

    // Queue pending approval for edit flow (with explicit assistant_model configured)
    expect(config('ai.assistant_model'))->toBe('gpt-6.2-sol')
        ->and(config('ai.summary_model'))->toBe('gpt-6.2-sol');
    config(['ai.assistant_model' => 'gpt-4o-mini']);
    $editPending = parseSsePayloads(
        $this->postJson(route('assistant.messages.store', $team), [
            'conversation_id' => $conversationId,
            'message' => 'Edit expense test',
        ])->assertOk(),
    );
    expect(collect($editPending)->firstWhere('type', 'tool_approval_request')['approvals'][0]['id'])->toBe('call_expense_2');

    $editStream = $this->postJson(route('assistant.approvals.store', [$team, $conversationId]), [
        'decisions' => ['call_expense_2' => ['action' => 'edit', 'arguments' => ['amount' => '6000.00']]],
    ])->assertOk();
    expect(collect(parseSsePayloads($editStream))->pluck('type')->all())->toContain('text_delta');

    // Queue pending approval for reject flow
    $rejectPending = parseSsePayloads(
        $this->postJson(route('assistant.messages.store', $team), [
            'conversation_id' => $conversationId,
            'message' => 'Reject expense test',
        ])->assertOk(),
    );
    expect(collect($rejectPending)->firstWhere('type', 'tool_approval_request')['approvals'][0]['id'])->toBe('call_expense_3');

    $rejectStream = $this->postJson(route('assistant.approvals.store', [$team, $conversationId]), [
        'decisions' => ['call_expense_3' => ['action' => 'reject', 'reason' => 'No longer needed']],
    ])->assertOk();
    expect(collect(parseSsePayloads($rejectStream))->pluck('type')->all())->toContain('text_delta');

    // History returns tool_calls & answered state
    $membership = $team->memberships()->where('user_id', $owner->id)->firstOrFail();
    $askInputResult = (new AskUserInputTool($team, $owner))->handle(new ToolRequest([
        'title' => 'Funding Release Details',
        'description' => 'Matched MDSxclusive Farms',
        'submit_label' => 'Prepare release',
        'fields' => [
            'not-an-array',
            ['name' => '', 'label' => 'Missing name', 'type' => 'text'],
            ['name' => 'phase_name', 'label' => 'Phase name', 'type' => 'text', 'placeholder' => 'Phase 1', 'hint' => 'Brief title'],
            ['name' => 'amount', 'label' => 'Requested amount', 'type' => 'currency'],
            ['name' => 'category', 'label' => 'Category', 'type' => 'radio', 'options' => [
                'bad-opt',
                ['value' => '', 'label' => 'Empty value'],
                ['value' => 'seed', 'label' => 'Seeds & Fertilizer', 'hint' => 'Pre-planting inputs'],
            ]],
            ['name' => 'unknown_ctrl', 'label' => 'Extra', 'type' => 'bogus', 'required' => false, 'default_value' => 'N/A'],
        ],
    ]));

    ConversationMessage::query()->create([
        'id' => Str::uuid7()->toString(),
        'conversation_id' => $conversationId,
        'participant_type' => $membership->getMorphClass(),
        'participant_id' => $membership->getKey(),
        'agent' => FarmwellAssistant::class,
        'role' => 'assistant',
        'content' => 'Fill out the details below to continue.',
        'attachments' => [],
        'steps' => [[
            'text' => 'Fill out the details below to continue.',
            'tool_calls' => [
                [
                    'id' => 'call_input_1',
                    'name' => 'AskUserInputTool',
                    'arguments' => ['title' => 'Funding Release Details'],
                    'result' => $askInputResult,
                ],
                [
                    'id' => 'call_read_1',
                    'name' => 'GetFinanceSummaryTool',
                    'arguments' => [],
                    'result' => '{"currency":"NGN"}',
                ],
            ],
            'reasoning' => '',
        ]],
        'usage' => [],
        'meta' => [],
        'status' => 'completed',
    ]);

    $history = $this->getJson(route('assistant.index', ['current_team' => $team, 'conversation_id' => $conversationId]))
        ->assertOk()
        ->json();
    expect(collect($history['messages'])->pluck('tool_calls')->flatten(1)->isNotEmpty())->toBeTrue();
    $formMessage = collect($history['messages'])->firstWhere('input_form.ui_type', 'input_form');
    expect($formMessage)->not->toBeNull()
        ->and($formMessage['input_form']['title'])->toBe('Funding Release Details')
        ->and($formMessage['input_form']['fields'])->toHaveCount(4)
        ->and($formMessage['input_form']['fields'][2]['options'][0]['hint'])->toBe('Pre-planting inputs')
        ->and($formMessage['input_form']['fields'][3]['type'])->toBe('text')
        ->and($formMessage['input_form']['fields'][3]['required'])->toBeFalse()
        ->and(collect($formMessage['tool_calls'])->pluck('name')->all())->toBe(['GetFinanceSummaryTool']);

    $mismatchStream = $this->postJson(route('assistant.messages.store', $team), [
        'message' => 'Trigger mismatch',
    ])->assertOk();
    expect(collect(parseSsePayloads($mismatchStream))->firstWhere('type', 'approval_mismatch')['message'])
        ->toBe('Approvals expired');

    FarmwellAssistant::fake([
        fn () => throw new RuntimeException('Upstream error'),
    ]);
    $errorStream = $this->postJson(route('assistant.messages.store', $team), [
        'message' => 'Trigger failure',
    ])->assertOk();
    expect(collect(parseSsePayloads($errorStream))->firstWhere('type', 'error')['message'])
        ->toContain('could not complete');

    // Destroy deletes conversation & messages
    $this->deleteJson(route('assistant.conversations.destroy', [$team, $conversationId]))
        ->assertNoContent();
    expect(Conversation::query()->find($conversationId))->toBeNull();
});

test('impersonating a same-team member scopes conversations and allows switching and deleting their chats', function () {
    $team = Team::factory()->create();
    $admin = User::factory()->create(['platform_role' => PlatformRole::SuperAdmin]);
    $team->members()->attach($admin, ['role' => TeamRole::Investor->value]);
    $admin->switchTeam($team);

    $owner = attachTeamMember($team, TeamRole::Owner);

    FarmwellAssistant::fake([
        'Admin summary response.',
        'Owner chat one response.',
        'Owner chat two response.',
    ]);

    $this->actingAs($admin);
    $adminEvents = parseSsePayloads(
        $this->postJson(route('assistant.messages.store', $team), [
            'message' => 'Admin investment balance',
        ])->assertOk(),
    );
    $adminConversationId = collect($adminEvents)->firstWhere('type', 'conversation')['conversation_id'];

    $this->post(route('platform.impersonate.store'), [
        'user_id' => $owner->id,
        'reason' => 'Verify owner assistant history and deletion',
    ])->assertRedirect(route('dashboard', $team));

    expect(auth()->id())->toBe($owner->id);

    // Impersonated owner does not see or delete admin's same-team conversation
    $this->getJson(route('assistant.index', $team))
        ->assertOk()
        ->assertJsonPath('conversations', [])
        ->assertJsonPath('activeConversationId', null);

    $this->getJson(route('assistant.index', ['current_team' => $team, 'conversation_id' => $adminConversationId]))
        ->assertNotFound();

    $this->deleteJson(route('assistant.conversations.destroy', [$team, $adminConversationId]))
        ->assertNotFound();
    expect(Conversation::query()->find($adminConversationId))->not->toBeNull();

    // Create two conversations as impersonated owner, switch between them, and delete one
    $ownerChatOneId = collect(parseSsePayloads(
        $this->postJson(route('assistant.messages.store', $team), [
            'message' => 'First owner question',
        ])->assertOk(),
    ))->firstWhere('type', 'conversation')['conversation_id'];

    $ownerChatTwoId = collect(parseSsePayloads(
        $this->postJson(route('assistant.messages.store', $team), [
            'message' => 'Second owner question',
        ])->assertOk(),
    ))->firstWhere('type', 'conversation')['conversation_id'];

    $this->getJson(route('assistant.index', ['current_team' => $team, 'conversation_id' => $ownerChatOneId]))
        ->assertOk()
        ->assertJsonPath('activeConversationId', $ownerChatOneId)
        ->assertJsonCount(2, 'conversations');

    $deleteResponse = $this->deleteJson(route('assistant.conversations.destroy', [$team, $ownerChatOneId]))
        ->assertNoContent();
    expect($deleteResponse->getContent())->toBe('');
    expect(Conversation::query()->find($ownerChatOneId))->toBeNull()
        ->and(Conversation::query()->find($ownerChatTwoId))->not->toBeNull();

    $this->getJson(route('assistant.index', $team))
        ->assertOk()
        ->assertJsonCount(1, 'conversations')
        ->assertJsonPath('activeConversationId', $ownerChatTwoId);

    // Stopping impersonation restores admin's own conversation intact and deletable
    $this->delete(route('platform.impersonate.destroy'))
        ->assertRedirect(route('platform.index'));

    $this->getJson(route('assistant.index', $team))
        ->assertOk()
        ->assertJsonCount(1, 'conversations')
        ->assertJsonPath('activeConversationId', $adminConversationId);

    $this->deleteJson(route('assistant.conversations.destroy', [$team, $adminConversationId]))
        ->assertNoContent();
    expect(Conversation::query()->find($adminConversationId))->toBeNull();
});

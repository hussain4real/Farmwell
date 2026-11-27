<?php

use App\Actions\Ai\BackfillAiUsageEvents;
use App\Actions\Ai\CalculateAiUsageCost;
use App\Actions\Ai\SyncModelsDevPricing;
use App\Ai\Agents\FarmwellAssistant;
use App\Ai\Agents\InternalFarmSummary;
use App\Ai\Agents\InvestorProgressSummary;
use App\Enums\AiUsageFeature;
use App\Enums\PlatformRole;
use App\Enums\TeamRole;
use App\Jobs\GenerateAiSummary;
use App\Listeners\RecordAiUsage;
use App\Models\AiModelPrice;
use App\Models\AiSummary;
use App\Models\AiUsageEvent;
use App\Models\AuditEvent;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\InvestorAgreement;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Ai\Ai;
use Laravel\Ai\Events\AgentFailed;
use Laravel\Ai\Events\AgentPrompted;
use Laravel\Ai\Events\PromptingAgent;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\TextUsage;

use function Laravel\Ai\agent;

beforeEach(function () {
    $this->withoutVite();
});

test('models.dev sync upserts normalized pricing, handles invalid payloads, and retroactively reprices unpriced events', function () {
    $unpricedEvent = AiUsageEvent::factory()->create([
        'provider' => 'openai',
        'model' => 'gpt-6.2-sol',
        'input_tokens' => 200_000,
        'uncached_input_tokens' => 150_000,
        'cache_read_tokens' => 50_000,
        'output_tokens' => 40_000,
        'total_tokens' => 240_000,
        'input_cost_microdollars' => 0,
        'output_cost_microdollars' => 0,
        'cache_cost_microdollars' => 0,
        'cost_microdollars' => 0,
        'pricing_status' => 'unpriced',
    ]);

    $stillUnpriced = AiUsageEvent::factory()->create([
        'provider' => 'openai',
        'model' => 'custom-local-model',
        'pricing_status' => 'unpriced',
        'cost_microdollars' => 0,
    ]);

    Http::fake([
        'https://models.dev/api.json' => Http::sequence()
            ->push([
                'openai' => [
                    'models' => [
                        'gpt-6.2-sol' => [
                            'id' => 'gpt-6.2-sol',
                            'name' => 'GPT-6.2 Sol',
                            'canonical_model_id' => 'openai/gpt-6.2-sol',
                            'limit' => ['context' => 1_050_000, 'output' => 128_000],
                            'cost' => [
                                'input' => 2,
                                'output' => 10,
                                'cache_read' => 0.1,
                                'cache_write' => 2.5,
                                'tiers' => [
                                    [
                                        'input' => 4,
                                        'output' => 15,
                                        'cache_read' => 0.2,
                                        'cache_write' => 5,
                                        'tier' => ['type' => 'context', 'size' => 272_000],
                                    ],
                                ],
                            ],
                        ],
                        'broken-entry' => 'not-an-array',
                        'no-cost' => ['id' => 'no-cost', 'name' => 'Freebie'],
                    ],
                ],
                'google' => [
                    'models' => [
                        'gemini-2.5-pro' => [
                            'name' => 'Gemini 2.5 Pro',
                            'limit' => ['context' => 1_048_576, 'output' => 65_536],
                            'cost' => [
                                'input' => 1.25,
                                'output' => 10,
                                'cache_read' => 0.31,
                            ],
                        ],
                    ],
                ],
                'amazon-bedrock' => [
                    'models' => [
                        'claude-sonnet-4-5' => [
                            'id' => 'claude-sonnet-4-5',
                            'name' => 'Claude Sonnet 4.5',
                            'cost' => [
                                'input' => 3,
                                'output' => 15,
                                'reasoning' => 4.5,
                            ],
                        ],
                    ],
                ],
                'missing-models-node' => ['label' => 'Empty provider'],
            ])
            ->push('not-json', 200),
    ]);

    $this->artisan('ai:sync-model-prices')
        ->expectsOutputToContain('Synced 3 models from models.dev and repriced 1 usage events.')
        ->assertExitCode(0);

    expect(AiModelPrice::query()->count())->toBe(3)
        ->and(AiModelPrice::query()->where('provider', 'gemini')->where('model_id', 'gemini-2.5-pro')->exists())->toBeTrue()
        ->and(AiModelPrice::query()->where('provider', 'bedrock')->where('model_id', 'claude-sonnet-4-5')->exists())->toBeTrue();

    $unpricedEvent->refresh();
    // 150,000 uncached * 2 = 300,000; 50,000 * 0.1 = 5,000; 40,000 * 10 = 400,000 => 705,000 microdollars ($0.705)
    expect($unpricedEvent->pricing_status)->toBe('priced')
        ->and($unpricedEvent->input_cost_microdollars)->toBe(300_000)
        ->and($unpricedEvent->cache_cost_microdollars)->toBe(5_000)
        ->and($unpricedEvent->output_cost_microdollars)->toBe(400_000)
        ->and($unpricedEvent->cost_microdollars)->toBe(705_000)
        ->and($stillUnpriced->fresh()->pricing_status)->toBe('unpriced');

    expect(fn () => app(SyncModelsDevPricing::class)->handle())->toThrow(RuntimeException::class);
});

test('cost calculator supports cache tokens, reasoning rates, context tiers, and date/canonical fallbacks', function () {
    $calculator = app(CalculateAiUsageCost::class);

    AiModelPrice::factory()->create([
        'provider' => 'openai',
        'model_id' => 'gpt-6.2-sol',
        'canonical_id' => 'openai/gpt-6.2-sol',
        'input_cost_per_million' => 2.0,
        'output_cost_per_million' => 10.0,
        'cache_read_cost_per_million' => 0.1,
        'cache_write_cost_per_million' => 2.5,
        'tiers' => [
            'ignored-non-array',
            ['tier' => ['type' => 'other', 'size' => 100]],
            ['input' => 4.0, 'output' => 15.0, 'cache_read' => 0.2, 'cache_write' => 5.0, 'tier' => ['type' => 'context', 'size' => 272_000]],
        ],
    ]);

    AiModelPrice::factory()->create([
        'provider' => 'anthropic',
        'model_id' => 'claude-sonnet-4-5',
        'canonical_id' => 'anthropic/claude-sonnet-4-5',
        'input_cost_per_million' => 3.0,
        'output_cost_per_million' => 15.0,
        'cache_read_cost_per_million' => null,
        'cache_write_cost_per_million' => null,
        'reasoning_cost_per_million' => 5.0,
    ]);

    expect($calculator->resolvePrice('openai', '   '))->toBeNull();

    // Base context (< 272k) with cache read & cache write
    $base = $calculator->calculate('openai', 'gpt-6.2-sol', 100_000, 10_000, 40_000, 10_000);
    // Uncached: 50,000 * 2 = 100,000; Cache: 40,000*0.1 + 10,000*2.5 = 29,000; Output: 10,000*10 = 100,000
    expect($base['pricing_status'])->toBe('priced')
        ->and($base['uncached_input_tokens'])->toBe(50_000)
        ->and($base['input_cost_microdollars'])->toBe(100_000)
        ->and($base['cache_cost_microdollars'])->toBe(29_000)
        ->and($base['output_cost_microdollars'])->toBe(100_000)
        ->and($base['cost_microdollars'])->toBe(229_000)
        ->and($base['estimated_cache_savings_microdollars'])->toBe(76_000);

    // High context (> 272k) switches to tier pricing (input=4, output=15, cache_read=0.2)
    $tiered = $calculator->calculate('openai', 'gpt-6.2-sol', 300_000, 10_000, 50_000);
    // Uncached: 250,000 * 4 = 1,000,000; Cache: 50,000 * 0.2 = 10,000; Output: 10,000 * 15 = 150,000
    expect($tiered['input_cost_microdollars'])->toBe(1_000_000)
        ->and($tiered['cache_cost_microdollars'])->toBe(10_000)
        ->and($tiered['output_cost_microdollars'])->toBe(150_000)
        ->and($tiered['cost_microdollars'])->toBe(1_160_000);

    // Date-suffix stripping + distinct reasoning token rate
    $dated = $calculator->calculate('anthropic', 'claude-sonnet-4-5-20250929', 10_000, 4_000, 0, 0, 1_000);
    // Output: (3,000 * 15) + (1,000 * 5) = 50,000
    expect($dated['pricing_status'])->toBe('priced')
        ->and($dated['output_cost_microdollars'])->toBe(50_000);

    // Provider fallback by model_id when provider alias differs (e.g. openrouter / custom)
    expect($calculator->resolvePrice('custom-proxy', 'gpt-6.2-sol'))->not->toBeNull();
});

test('telemetry listener automatically records assistant streams with impersonation, summary jobs, failures, and anonymous calls', function () {
    AiModelPrice::factory()->create([
        'provider' => 'openai',
        'model_id' => 'gpt-6.2-sol',
        'input_cost_per_million' => 2.0,
        'output_cost_per_million' => 10.0,
    ]);

    $team = Team::factory()->create();
    $admin = User::factory()->create(['platform_role' => PlatformRole::SuperAdmin]);
    $owner = User::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $owner->switchTeam($team);

    $farm = Farm::factory()->for($team)->create();
    FarmActivity::factory()->for($team)->for($farm)->create([
        'activity_date' => now()->toDateString(),
        'investor_safe_summary' => 'Completed planting',
    ]);
    $agreement = InvestorAgreement::factory()->create(['team_id' => $team->id, 'farm_id' => $farm->id]);
    $summary = AiSummary::factory()->create([
        'team_id' => $team->id,
        'investor_agreement_id' => $agreement->id,
        'requested_by_id' => $owner->id,
        'type' => 'investor_progress',
    ]);

    // 1. Impersonated FarmwellAssistant streamed call (configured model gpt-6.2-sol)
    FarmwellAssistant::fake(['Balanced budget looks healthy.']);
    $this->actingAs($admin)
        ->post(route('platform.impersonate.store'), [
            'user_id' => $owner->id,
            'reason' => 'Verify AI usage tracking during impersonation',
        ])->assertRedirect(route('dashboard', $team));

    $stream = $this->postJson(route('assistant.messages.store', $team), [
        'message' => 'What is our balance?',
    ])->assertOk();
    $stream->streamedContent();

    $assistantEvent = AiUsageEvent::query()->where('feature', AiUsageFeature::Assistant->value)->sole();
    expect($assistantEvent->team_id)->toBe($team->id)
        ->and($assistantEvent->user_id)->toBe($owner->id)
        ->and($assistantEvent->impersonator_id)->toBe($admin->id)
        ->and($assistantEvent->model)->toBe('gpt-6.2-sol')
        ->and($assistantEvent->is_streamed)->toBeTrue()
        ->and($assistantEvent->status)->toBe('completed');

    // Also cover AssistantController::assistantModel() returning null branch cleanly
    config(['ai.assistant_model' => null]);
    FarmwellAssistant::fake(['Fallback model reply.']);
    $this->postJson(route('assistant.messages.store', $team), [
        'message' => 'Check fallback model',
    ])->assertOk()->streamedContent();
    config(['ai.assistant_model' => 'gpt-6.2-sol']);

    // 2. Queued summary jobs for investor_progress & weekly (with InternalFarmSummary context)
    InvestorProgressSummary::fake(['Investor draft']);
    (new GenerateAiSummary($summary->id))->handle();
    expect(AiUsageEvent::query()->where('feature', AiUsageFeature::SummaryInvestorProgress->value)->exists())->toBeTrue();

    $weeklySummary = AiSummary::factory()->create([
        'team_id' => $team->id,
        'farm_id' => $farm->id,
        'investor_agreement_id' => null,
        'requested_by_id' => $owner->id,
        'type' => 'weekly',
    ]);
    InternalFarmSummary::fake(['Weekly draft']);
    (new GenerateAiSummary($weeklySummary->id))->handle();
    expect(AiUsageEvent::query()->where('feature', AiUsageFeature::SummaryWeekly->value)->exists())->toBeTrue();
    expect(AiUsageFeature::fromSummaryType('investor_progress'))->toBe(AiUsageFeature::SummaryInvestorProgress)
        ->and(AiUsageFeature::fromSummaryType('unknown')->value)->toBe('other');

    // 3. Anonymous agent without HasUsageContext or bound HTTP request -> recorded under Feature::Other
    app()->forgetInstance('request');
    $fakeAgent = agent(instructions: 'Say hi');
    $fakeAgent::fake(['Hi there']);
    $fakeAgent->prompt('Hello');
    expect(AiUsageEvent::query()->where('feature', AiUsageFeature::Other->value)->exists())->toBeTrue();

    // 4. Starting ring-buffer cap (>200 startedAt entries) & AgentFailed recording
    $recorder = app(RecordAiUsage::class);
    $provider = Ai::textProviderFor($fakeAgent, 'openai');
    $prompt = new AgentPrompt($fakeAgent, 'Hello', [], $provider, 'gpt-6.2-sol');
    for ($i = 0; $i < 205; $i++) {
        $recorder->handleStarting(new PromptingAgent('cap-'.$i, $prompt));
    }
    $recorder->handleFailed(new AgentFailed('cap-204', $prompt, new RuntimeException('Upstream timeout failure')));

    $failedEvent = AiUsageEvent::query()->where('invocation_id', 'cap-204')->sole();
    expect($failedEvent->status)->toBe('failed')
        ->and($failedEvent->error_message)->toBe('Upstream timeout failure')
        ->and($failedEvent->duration_ms)->not->toBeNull();

    // 5. Persistence exceptions are safely reported and never crash the caller
    Schema::drop('ai_usage_events');
    $recorder->handleCompleted(new AgentPrompted(
        'err-completed',
        $prompt,
        new AgentResponse('err-completed', 'ok', new TextUsage(10, 5), new Meta('openai', 'gpt-6.2-sol')),
    ));
    $recorder->handleFailed(new AgentFailed('err-failed', $prompt, new RuntimeException('Broken')));
    Schema::create('ai_usage_events', function (Blueprint $table): void {
        $table->id();
    });
    expect(true)->toBeTrue();
});

test('backfill imports stored assistant conversation messages and stays idempotent', function () {
    AiModelPrice::factory()->create([
        'provider' => 'openai',
        'model_id' => 'gpt-6.2-sol',
        'input_cost_per_million' => 2.0,
        'output_cost_per_million' => 10.0,
    ]);

    $team = Team::factory()->create();
    $owner = User::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $membership = $team->memberships()->where('user_id', $owner->id)->firstOrFail();

    $conversationId = (string) Str::uuid7();
    Conversation::query()->create([
        'id' => $conversationId,
        'participant_type' => $membership->getMorphClass(),
        'participant_id' => $membership->getKey(),
        'title' => 'Finance Check',
    ]);

    ConversationMessage::query()->create([
        'id' => (string) Str::uuid7(),
        'conversation_id' => $conversationId,
        'participant_type' => $membership->getMorphClass(),
        'participant_id' => $membership->getKey(),
        'agent' => FarmwellAssistant::class,
        'role' => 'user',
        'content' => 'What is the balance?',
        'attachments' => [],
        'steps' => [],
        'usage' => [],
        'meta' => [],
        'status' => 'completed',
    ]);

    $assistantMsgId = (string) Str::uuid7();
    ConversationMessage::query()->create([
        'id' => $assistantMsgId,
        'conversation_id' => $conversationId,
        'participant_type' => $membership->getMorphClass(),
        'participant_id' => $membership->getKey(),
        'agent' => FarmwellAssistant::class,
        'role' => 'assistant',
        'content' => 'Balance is N50,000.',
        'attachments' => [],
        'steps' => [
            ['content' => '', 'tool_calls' => [['id' => 'c1', 'name' => 'GetFinanceSummaryTool', 'arguments' => []]], 'reasoning' => '', 'replay_blocks' => [], 'provider_tool_calls' => []],
            ['content' => 'Balance is N50,000.', 'tool_calls' => [], 'reasoning' => '', 'replay_blocks' => [], 'provider_tool_calls' => []],
        ],
        'usage' => [
            'input_tokens' => 5000,
            'output_tokens' => 1000,
            'cache_read_input_tokens' => 0,
            'cache_write_input_tokens' => 0,
            'reasoning_tokens' => 0,
        ],
        'meta' => ['provider' => 'openai', 'model' => 'gpt-6.2-sol'],
        'status' => 'completed',
    ]);

    $fallbackMsgId = (string) Str::uuid7();
    ConversationMessage::query()->create([
        'id' => $fallbackMsgId,
        'conversation_id' => $conversationId,
        'participant_type' => $membership->getMorphClass(),
        'participant_id' => $membership->getKey(),
        'agent' => 'App\\Ai\\Agents\\LegacyAgent',
        'role' => 'assistant',
        'content' => '',
        'attachments' => [],
        'steps' => [],
        'usage' => [],
        'meta' => ['error' => 'Provider timeout'],
        'status' => 'failed',
    ]);

    $this->artisan('ai:backfill-usage')
        ->expectsOutputToContain('Imported 2 historical AI usage events (0 skipped).')
        ->assertExitCode(0);

    $event = AiUsageEvent::query()->where('invocation_id', 'backfill:msg:'.$assistantMsgId)->sole();
    expect($event->team_id)->toBe($team->id)
        ->and($event->user_id)->toBe($owner->id)
        ->and($event->steps_count)->toBe(2)
        ->and($event->tool_calls_count)->toBe(1)
        ->and($event->cost_microdollars)->toBe(20_000); // 5000*2 + 1000*10

    $fallbackEvent = AiUsageEvent::query()->where('invocation_id', 'backfill:msg:'.$fallbackMsgId)->sole();
    expect($fallbackEvent->feature)->toBe(AiUsageFeature::Other)
        ->and($fallbackEvent->model)->toBe('gpt-6.2-sol')
        ->and($fallbackEvent->error_message)->toBe('Provider timeout');

    // Running backfill again skips already imported rows cleanly
    expect(app(BackfillAiUsageEvents::class)->handle())->toBe(['imported' => 0, 'skipped' => 2]);
});

test('platform admin ai usage dashboard enforces authorization, aggregates charts and breakdowns, syncs, backfills, and exports csv', function () {
    AiModelPrice::factory()->create([
        'provider' => 'openai',
        'model_id' => 'gpt-6.2-sol',
        'display_name' => 'GPT-6.2 Sol',
        'input_cost_per_million' => 2.0,
        'output_cost_per_million' => 10.0,
        'cache_read_cost_per_million' => 0.1,
    ]);
    AiModelPrice::factory()->create([
        'provider' => 'anthropic',
        'model_id' => 'claude-no-cache-rate',
        'input_cost_per_million' => 3.0,
        'output_cost_per_million' => 15.0,
        'cache_read_cost_per_million' => null,
    ]);

    $superAdmin = User::factory()->create(['platform_role' => PlatformRole::SuperAdmin]);
    $regularUser = User::factory()->create(['name' => '=EvilUser']);
    $team = Team::factory()->create(['name' => '+FormulaTeam']);
    $team->members()->attach($regularUser, ['role' => TeamRole::Owner->value]);

    // Non-super-admin is blocked on all routes
    $this->actingAs($regularUser);
    $this->get(route('platform.ai-usage.index'))->assertForbidden();
    $this->get(route('platform.ai-usage.export'))->assertForbidden();
    $this->post(route('platform.ai-usage.sync-pricing'))->assertForbidden();
    $this->post(route('platform.ai-usage.backfill'))->assertForbidden();

    // Seed events across current window & previous window
    AiUsageEvent::factory()->create([
        'team_id' => $team->id,
        'user_id' => $regularUser->id,
        'impersonator_id' => $superAdmin->id,
        'feature' => AiUsageFeature::Assistant,
        'provider' => 'openai',
        'model' => 'gpt-6.2-sol',
        'status' => 'completed',
        'input_tokens' => 20_000,
        'uncached_input_tokens' => 10_000,
        'cache_read_tokens' => 10_000,
        'output_tokens' => 2_000,
        'total_tokens' => 22_000,
        'input_cost_microdollars' => 20_000,
        'cache_cost_microdollars' => 1_000,
        'output_cost_microdollars' => 20_000,
        'cost_microdollars' => 41_000,
        'pricing_status' => 'priced',
        'duration_ms' => 500,
        'occurred_at' => now(),
    ]);

    AiUsageEvent::factory()->create([
        'team_id' => null,
        'user_id' => null,
        'feature' => AiUsageFeature::SummaryWeekly,
        'provider' => 'openai',
        'model' => 'unpriced-experimental',
        'status' => 'failed',
        'input_tokens' => 0,
        'output_tokens' => 0,
        'total_tokens' => 0,
        'cost_microdollars' => 0,
        'pricing_status' => 'unpriced',
        'duration_ms' => null,
        'error_message' => 'Rate limited',
        'occurred_at' => now()->subDays(2),
    ]);

    AiUsageEvent::factory()->create([
        'team_id' => $team->id,
        'user_id' => $regularUser->id,
        'feature' => AiUsageFeature::Assistant,
        'provider' => 'anthropic',
        'model' => 'claude-no-cache-rate',
        'cache_read_tokens' => 500,
        'pricing_status' => 'priced',
        'occurred_at' => now()->subDay(),
    ]);

    // Previous 7d window event for delta calculation
    AiUsageEvent::factory()->create([
        'team_id' => $team->id,
        'user_id' => $regularUser->id,
        'feature' => AiUsageFeature::Assistant,
        'provider' => 'openai',
        'model' => 'gpt-6.2-sol',
        'cost_microdollars' => 20_500,
        'occurred_at' => now()->subDays(9),
    ]);

    $this->actingAs($superAdmin);

    // Default range ('30d') when omitted + empty result state when filtered to 'paused' across 'all'
    $this->get(route('platform.ai-usage.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('filters.range', '30d'));

    $this->get(route('platform.ai-usage.index', ['range' => 'all', 'status' => 'paused']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.totalCalls', 0)
            ->where('daily', [])
            ->where('byUser', [])
            ->where('byWorkspace', []));

    $this->get(route('platform.ai-usage.index', ['range' => '7d']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('platform/AiUsage')
            ->where('filters.range', '7d')
            ->where('summary.totalCalls', 3)
            ->where('summary.failedCalls', 1)
            ->where('summary.totalCostMicrodollars', 48_000)
            ->where('summary.previousCostMicrodollars', 20_500)
            ->where('summary.unpricedCalls', 1)
            ->has('daily', 7)
            ->has('byFeature', 2)
            ->has('byModel', 3)
            ->has('byUser', 2)
            ->has('byWorkspace', 2)
            ->where('recentCalls.total', 3));

    // Range 'all' + secondary filters cover all query branches cleanly
    $this->get(route('platform.ai-usage.index', [
        'range' => 'all',
        'team_id' => $team->id,
        'user_id' => $regularUser->id,
        'feature' => AiUsageFeature::Assistant->value,
        'model' => 'gpt-6.2-sol',
        'status' => 'completed',
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.totalCalls', 2)
            ->where('summary.previousCostMicrodollars', null));

    // CSV Export neutralizes formula injection characters
    $csvResponse = $this->get(route('platform.ai-usage.export', ['range' => '7d']))
        ->assertOk();
    $csvContent = $csvResponse->streamedContent();
    expect($csvContent)
        ->toContain('Timestamp (UTC)')
        ->toContain("'=EvilUser")
        ->toContain("'+FormulaTeam")
        ->toContain('0.041000');

    // Sync pricing route (success + error handling)
    Http::fake([
        'https://models.dev/api.json' => Http::sequence()
            ->push([
                'openai' => [
                    'models' => [
                        'unpriced-experimental' => [
                            'id' => 'unpriced-experimental',
                            'name' => 'Experimental',
                            'cost' => ['input' => 1, 'output' => 2],
                        ],
                    ],
                ],
            ])
            ->push([], 500),
    ]);
    $this->post(route('platform.ai-usage.sync-pricing'))->assertRedirect();
    expect(AuditEvent::query()->where('action', 'platform.ai_pricing_synced')->count())->toBe(1);

    $this->post(route('platform.ai-usage.sync-pricing'))->assertRedirect();
    expect(AuditEvent::query()->where('action', 'platform.ai_pricing_synced')->count())->toBe(1);

    // Backfill POST route records audit event
    $this->post(route('platform.ai-usage.backfill'))->assertRedirect();
    expect(AuditEvent::query()->where('action', 'platform.ai_usage_backfilled')->exists())->toBeTrue();
});

<?php

namespace App\Http\Controllers;

use App\Actions\AiSummaries\InvestorSummaryEligibility;
use App\Actions\Audit\RecordAuditEvent;
use App\Enums\InvestorAgreementStatus;
use App\Enums\WhatsappIntakeStatus;
use App\Http\Requests\ReviewAiSummaryRequest;
use App\Http\Requests\StoreAiSummaryRequest;
use App\Jobs\GenerateAiSummary;
use App\Models\AiSummary;
use App\Models\Farm;
use App\Models\InvestorAgreement;
use App\Models\Team;
use App\Models\User;
use App\Models\WhatsappIntake;
use App\Support\SummaryMarkdown;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AiSummaryController extends Controller
{
    public function index(Request $request, Team $currentTeam, InvestorSummaryEligibility $eligibility): Response
    {
        $canManageInvestors = Gate::allows('manageInvestorAgreements', $currentTeam);
        $canManageOperations = Gate::allows('manageFarmOperations', $currentTeam);
        abort_unless($canManageInvestors || $canManageOperations, 403);

        return Inertia::render('ai-summaries/Index', [
            'canManageOperations' => $canManageOperations,
            'canManage' => true,
            'canManageInvestors' => $canManageInvestors,
            'agreements' => InvestorAgreement::query()->where('team_id', $currentTeam->id)
                ->when(! $canManageInvestors, fn ($query) => $query->whereRaw('1 = 0'))
                ->whereIn('status', [InvestorAgreementStatus::Active->value, InvestorAgreementStatus::Completed->value])
                ->orderBy('title')->get()->map(fn (InvestorAgreement $agreement): array => [
                    'id' => $agreement->id, 'label' => $agreement->title, 'farm_id' => $agreement->farm_id, 'cycle_id' => $agreement->production_cycle_id,
                ]),
            'farms' => $canManageOperations ? Farm::query()->where('team_id', $currentTeam->id)->with('productionCycles')->orderBy('name')->get()->map(fn (Farm $farm): array => [
                'id' => $farm->id, 'label' => $farm->name, 'cycles' => $farm->productionCycles->map(fn ($cycle): array => ['id' => $cycle->id, 'label' => $cycle->name])->all(),
            ]) : [],
            'intakes' => WhatsappIntake::query()->where('team_id', $currentTeam->id)->where('review_status', WhatsappIntakeStatus::Pending->value)
                ->when(! $canManageOperations, fn ($query) => $query->whereRaw('1 = 0'))
                ->latest()->get(['id', 'farm_id', 'production_cycle_id', 'source_date'])->map(fn (WhatsappIntake $intake): array => [
                    'id' => $intake->id, 'farm_id' => $intake->farm_id, 'cycle_id' => $intake->production_cycle_id, 'date' => $intake->source_date?->toDateString(),
                ]),
            'summaries' => AiSummary::query()->where('team_id', $currentTeam->id)
                ->when(! $canManageOperations, fn ($query) => $query->where('type', 'investor_progress'))
                ->when(! $canManageInvestors, fn ($query) => $query->where('type', '!=', 'investor_progress'))
                ->with(['investorAgreement', 'farm'])->latest()->get()->map(fn (AiSummary $summary): array => [
                    'id' => $summary->id, 'status' => $summary->status, 'type' => $summary->type,
                    'source_snapshot' => $summary->source_snapshot,
                    'publication_current' => $summary->status === 'published' && ($summary->investorAgreement !== null && $eligibility->isCurrent($summary, $summary->investorAgreement)),
                    'generated_text' => $summary->generated_text, 'final_text' => $summary->final_text,
                    'generated_html' => SummaryMarkdown::render($summary->generated_text), 'final_html' => SummaryMarkdown::render($summary->final_text),
                    'created_at' => $summary->created_at->toDateTimeString(),
                    'agreement_label' => $summary->investorAgreement?->title ?? $summary->farm?->name ?? __('Unavailable farm'),
                ]),
        ]);
    }

    public function store(StoreAiSummaryRequest $request, Team $currentTeam, RecordAuditEvent $audit): RedirectResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        DB::transaction(function () use ($request, $currentTeam, $user, $audit): void {
            $summary = AiSummary::create([
                'team_id' => $currentTeam->id,
                'investor_agreement_id' => $request->validated('agreement_id'),
                'farm_id' => $request->validated('farm_id'),
                'production_cycle_id' => $request->validated('production_cycle_id'),
                'requested_by_id' => $user->id,
                'status' => 'queued',
                'type' => $request->validated('type') ?? 'investor_progress',
                'whatsapp_intake_id' => $request->validated('whatsapp_intake_id'),
                'input_text' => $request->validated('input_text'),
            ]);
            $audit->handle($currentTeam, $user, 'ai_summary.requested', $summary);
            GenerateAiSummary::dispatch($summary->id)->afterCommit();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Summary generation queued.')]);

        return to_route('ai-summaries.index', ['current_team' => $currentTeam]);
    }

    public function review(ReviewAiSummaryRequest $request, Team $currentTeam, int $aiSummary, RecordAuditEvent $audit): RedirectResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        DB::transaction(function () use ($request, $currentTeam, $aiSummary, $user, $audit): void {
            $summary = AiSummary::query()->where('team_id', $currentTeam->id)->lockForUpdate()->findOrFail($aiSummary);
            Gate::authorize($summary->type === 'investor_progress' ? 'manageInvestorAgreements' : 'manageFarmOperations', $currentTeam);
            if ($summary->status !== 'draft') {
                throw ValidationException::withMessages(['action' => __('Only drafts can be reviewed.')]);
            }
            $approved = $request->validated('action') === 'approve';
            $summary->update([
                'status' => $approved ? 'approved' : 'rejected',
                'final_text' => $approved ? $request->validated('final_text') : null,
                'reviewed_by_id' => $user->id,
                'reviewed_at' => now(),
            ]);
            $audit->handle($currentTeam, $user, 'ai_summary.reviewed', $summary, newValues: ['status' => $summary->status]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Summary reviewed.')]);

        return to_route('ai-summaries.index', ['current_team' => $currentTeam]);
    }

    public function publish(Request $request, Team $currentTeam, int $aiSummary, InvestorSummaryEligibility $eligibility, RecordAuditEvent $audit): RedirectResponse
    {
        Gate::authorize('manageInvestorAgreements', $currentTeam);
        $user = $request->user();
        assert($user instanceof User);

        DB::transaction(function () use ($currentTeam, $aiSummary, $user, $eligibility, $audit): void {
            $summary = AiSummary::query()->where('team_id', $currentTeam->id)->lockForUpdate()->findOrFail($aiSummary);
            $agreement = InvestorAgreement::query()->where('team_id', $currentTeam->id)->lockForUpdate()->findOrFail($summary->investor_agreement_id);
            if ($summary->status !== 'approved' || ! is_string($summary->final_text) || trim($summary->final_text) === '' || ! $eligibility->isCurrent($summary, $agreement, lock: true)) {
                throw ValidationException::withMessages(['publication' => __('Only a reviewed progress summary with current, eligible sources can be published. Generate a new draft if its sources or agreement changed.')]);
            }
            $summary->update(['status' => 'published', 'published_by_id' => $user->id, 'published_at' => now()]);
            $audit->handle($currentTeam, $user, 'ai_summary.published', $summary);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Summary published to the assigned investor.')]);

        return to_route('ai-summaries.index', ['current_team' => $currentTeam]);
    }

    public function withdraw(Request $request, Team $currentTeam, int $aiSummary, RecordAuditEvent $audit): RedirectResponse
    {
        Gate::authorize('manageInvestorAgreements', $currentTeam);
        $user = $request->user();
        assert($user instanceof User);

        DB::transaction(function () use ($currentTeam, $aiSummary, $user, $audit): void {
            $summary = AiSummary::query()->where('team_id', $currentTeam->id)->lockForUpdate()->findOrFail($aiSummary);
            if ($summary->status !== 'published') {
                throw ValidationException::withMessages(['publication' => __('Only published summaries can be withdrawn.')]);
            }
            $summary->update(['status' => 'approved', 'published_by_id' => null, 'published_at' => null]);
            $audit->handle($currentTeam, $user, 'ai_summary.withdrawn', $summary);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Summary withdrawn from the investor portal.')]);

        return to_route('ai-summaries.index', ['current_team' => $currentTeam]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Actions\Ai\BackfillAiUsageEvents;
use App\Actions\Ai\BuildAiUsageAnalytics;
use App\Actions\Ai\SyncModelsDevPricing;
use App\Actions\Audit\RecordAuditEvent;
use App\Enums\AiUsageFeature;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class PlatformAiUsageController extends Controller
{
    public function index(Request $request, BuildAiUsageAnalytics $analytics): Response
    {
        $filters = $this->validateFilters($request);

        return Inertia::render('platform/AiUsage', $analytics->handle($filters));
    }

    public function syncPricing(Request $request, SyncModelsDevPricing $sync, RecordAuditEvent $audit): RedirectResponse
    {
        try {
            $result = $sync->handle();

            $audit->handle(
                team: null,
                actor: $request->user(),
                action: 'platform.ai_pricing_synced',
                reason: sprintf(
                    'Synced %d models from models.dev (%d repriced).',
                    $result['synced_models'],
                    $result['repriced_events'],
                ),
                metadata: $result,
            );

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => sprintf(
                    'Synced %d models from models.dev and repriced %d calls.',
                    $result['synced_models'],
                    $result['repriced_events'],
                ),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Could not sync pricing from models.dev right now. Please try again.',
            ]);
        }

        return back();
    }

    public function backfill(Request $request, BackfillAiUsageEvents $backfill, RecordAuditEvent $audit): RedirectResponse
    {
        $result = $backfill->handle();

        $audit->handle(
            team: null,
            actor: $request->user(),
            action: 'platform.ai_usage_backfilled',
            reason: sprintf(
                'Backfilled %d historical AI assistant turns (%d skipped).',
                $result['imported'],
                $result['skipped'],
            ),
            metadata: $result,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => sprintf(
                'Imported %d historical AI turns (%d already recorded).',
                $result['imported'],
                $result['skipped'],
            ),
        ]);

        return back();
    }

    public function export(Request $request, BuildAiUsageAnalytics $analytics): StreamedResponse
    {
        $filters = $this->validateFilters($request);
        $filename = 'farmwell-ai-usage-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($analytics, $filters): void {
            $handle = fopen('php://output', 'w');
            assert($handle !== false);

            foreach ($analytics->exportRows($filters) as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array{range?: string, team_id?: int|null, user_id?: int|null, feature?: string|null, model?: string|null, status?: string|null}
     */
    private function validateFilters(Request $request): array
    {
        /** @var array{range?: string, team_id?: int|null, user_id?: int|null, feature?: string|null, model?: string|null, status?: string|null} $validated */
        $validated = $request->validate([
            'range' => ['nullable', Rule::in(['7d', '30d', '90d', 'all'])],
            'team_id' => ['nullable', 'integer'],
            'user_id' => ['nullable', 'integer'],
            'feature' => ['nullable', Rule::enum(AiUsageFeature::class)],
            'model' => ['nullable', 'string', 'max:128'],
            'status' => ['nullable', Rule::in(['completed', 'paused', 'failed'])],
        ]);

        return $validated;
    }
}

<?php

namespace App\Http\Controllers\FarmOperations;

use App\Actions\FarmOperations\DeleteProductionLog;
use App\Actions\FarmOperations\RecordProductionLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\FarmOperations\StoreProductionLogRequest;
use App\Models\Farm;
use App\Models\ProductionCycle;
use App\Models\ProductionLog;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ProductionLogController extends Controller
{
    public function store(
        StoreProductionLogRequest $request,
        Team $currentTeam,
        Farm $farm,
        ProductionCycle $productionCycle,
        RecordProductionLog $recordProductionLog,
    ): RedirectResponse|JsonResponse {
        $user = $request->user();
        assert($user instanceof User);

        /** @var array<int, UploadedFile> $files */
        $files = $request->file('evidence', []);
        $caption = $request->validated('evidence_caption');

        $log = $recordProductionLog->handle(
            team: $currentTeam,
            farm: $farm,
            actor: $user,
            attributes: $request->logAttributes(),
            cycle: $productionCycle,
            evidence: $files,
            evidenceCaption: is_string($caption) ? $caption : null,
        );

        if ($request->expectsJson()) {
            return response()->json([
                'id' => $log->id,
                'message' => __('Daily production log recorded.'),
            ], 201);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Daily production log recorded.')]);

        return back();
    }

    public function destroy(
        Request $request,
        Team $currentTeam,
        Farm $farm,
        ProductionCycle $productionCycle,
        ProductionLog $productionLog,
        DeleteProductionLog $deleteProductionLog,
    ): RedirectResponse {
        Gate::authorize('manageFarmOperations', $currentTeam);
        abort_unless($productionLog->production_cycle_id === $productionCycle->id && $productionCycle->farm_id === $farm->id, 404);

        $user = $request->user();
        assert($user instanceof User);

        $deleteProductionLog->handle($currentTeam, $productionLog, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Production log removed.')]);

        return back();
    }
}

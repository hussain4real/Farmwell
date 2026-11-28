<?php

namespace App\Http\Controllers\FarmOperations;

use App\Actions\Harvests\RecordQuickEggSale;
use App\Http\Controllers\Controller;
use App\Http\Requests\Harvests\StoreQuickEggSaleRequest;
use App\Models\Farm;
use App\Models\ProductionCycle;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class QuickEggSaleController extends Controller
{
    public function __invoke(
        StoreQuickEggSaleRequest $request,
        Team $currentTeam,
        Farm $farm,
        ProductionCycle $productionCycle,
        RecordQuickEggSale $recordQuickEggSale,
    ): RedirectResponse {
        abort_unless($productionCycle->farm_id === $farm->id, 404);

        $user = $request->user();
        assert($user instanceof User);

        $recordQuickEggSale->handle($currentTeam, $farm, $productionCycle, $user, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Egg crate sale recorded and cycle revenue updated.')]);

        return back();
    }
}

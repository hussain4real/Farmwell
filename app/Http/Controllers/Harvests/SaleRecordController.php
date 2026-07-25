<?php

namespace App\Http\Controllers\Harvests;

use App\Actions\Harvests\RecordSale;
use App\Http\Controllers\Controller;
use App\Http\Requests\Harvests\StoreSaleRecordRequest;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class SaleRecordController extends Controller
{
    public function store(StoreSaleRecordRequest $request, Team $currentTeam, RecordSale $recordSale): RedirectResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        $recordSale->handle(
            team: $currentTeam,
            actor: $user,
            attributes: $request->saleAttributes(),
            evidence: $request->evidenceFiles(),
            caption: $request->evidenceCaption(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Sale recorded and recovery recalculated.')]);

        return to_route('harvests.index', ['current_team' => $currentTeam]);
    }
}

<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\BuildFinancePageData;
use App\Actions\Finance\RecordExternalTransfer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreExternalTransferRequest;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ExternalTransferController extends Controller
{
    public function index(Request $request, Team $currentTeam, BuildFinancePageData $pageData): Response
    {
        Gate::authorize('viewFinance', $currentTeam);

        $user = $request->user();
        assert($user instanceof User);

        return Inertia::render('finance/ExternalTransfers', $pageData->externalTransfers($currentTeam, $user));
    }

    public function store(StoreExternalTransferRequest $request, Team $currentTeam, RecordExternalTransfer $recordExternalTransfer): RedirectResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        $recordExternalTransfer->handle(
            team: $currentTeam,
            actor: $user,
            attributes: $request->transferAttributes(),
            proof: $request->proofFile(),
            caption: $request->proofCaption(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('External transfer recorded.')]);

        return to_route('finance.external-transfers.index', ['current_team' => $currentTeam]);
    }
}

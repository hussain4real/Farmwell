<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\ReconcileExternalTransfer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreTransferReconciliationRequest;
use App\Models\ExternalTransfer;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class TransferReconciliationController extends Controller
{
    public function store(
        StoreTransferReconciliationRequest $request,
        Team $currentTeam,
        ExternalTransfer $externalTransfer,
        ReconcileExternalTransfer $reconcileExternalTransfer,
    ): RedirectResponse {
        $user = $request->user();
        assert($user instanceof User);

        $reconcileExternalTransfer->handle(
            team: $currentTeam,
            actor: $user,
            transfer: $externalTransfer,
            attributes: $request->reconciliationAttributes(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Transfer reconciled.')]);

        return to_route('finance.external-transfers.index', ['current_team' => $currentTeam]);
    }
}

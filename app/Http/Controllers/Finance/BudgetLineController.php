<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\CreateBudgetLine;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreBudgetLineRequest;
use App\Models\Budget;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class BudgetLineController extends Controller
{
    public function store(StoreBudgetLineRequest $request, Team $currentTeam, Budget $budget, CreateBudgetLine $createBudgetLine): RedirectResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        $createBudgetLine->handle($currentTeam, $user, $budget, $request->lineAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Budget line added.')]);

        return to_route('finance.budgets.index', ['current_team' => $currentTeam]);
    }
}

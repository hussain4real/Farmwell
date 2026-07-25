<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\BuildFinancePageData;
use App\Actions\Finance\CreateBudget;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreBudgetRequest;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BudgetController extends Controller
{
    public function index(Request $request, Team $currentTeam, BuildFinancePageData $pageData): Response
    {
        Gate::authorize('viewFinance', $currentTeam);

        $user = $request->user();
        assert($user instanceof User);

        return Inertia::render('finance/Budgets', $pageData->budgets($currentTeam, $user));
    }

    public function store(StoreBudgetRequest $request, Team $currentTeam, CreateBudget $createBudget): RedirectResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        $createBudget->handle($currentTeam, $user, $request->budgetAttributes(), $request->budgetLines());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Budget created.')]);

        return to_route('finance.budgets.index', ['current_team' => $currentTeam]);
    }
}

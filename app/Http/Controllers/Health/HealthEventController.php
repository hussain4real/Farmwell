<?php

namespace App\Http\Controllers\Health;

use App\Actions\Health\BuildHealthPageData;
use App\Actions\Health\CreateHealthEvent;
use App\Actions\Health\UpdateHealthEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Health\StoreHealthEventRequest;
use App\Http\Requests\Health\UpdateHealthEventRequest;
use App\Models\HealthEvent;
use App\Models\Team;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class HealthEventController extends Controller
{
    public function index(Request $request, Team $currentTeam, BuildHealthPageData $pageData): Response
    {
        Gate::authorize('viewFieldWork', $currentTeam);

        $user = $request->user();
        assert($user instanceof User);

        return Inertia::render('health/Index', $pageData->handle($currentTeam, $user));
    }

    public function store(
        StoreHealthEventRequest $request,
        Team $currentTeam,
        CreateHealthEvent $createHealthEvent,
    ): RedirectResponse {
        $user = $request->user();
        assert($user instanceof User);

        $validated = $request->validated();
        $cost = $validated['cost'] ?? null;
        unset($validated['cost']);
        $validated['cost_minor'] = $cost !== null && $cost !== '' ? Money::toMinorUnit($cost) : 0;

        $createHealthEvent->handle($currentTeam, $user, $validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Health event scheduled.')]);

        return back();
    }

    public function update(
        UpdateHealthEventRequest $request,
        Team $currentTeam,
        HealthEvent $healthEvent,
        UpdateHealthEvent $updateHealthEvent,
    ): RedirectResponse {
        $user = $request->user();
        assert($user instanceof User);

        $validated = $request->validated();
        $quantityUsed = isset($validated['inventory_quantity_used']) && $validated['inventory_quantity_used'] !== null
            ? (float) $validated['inventory_quantity_used']
            : null;
        unset($validated['inventory_quantity_used']);

        if (array_key_exists('cost', $validated)) {
            $cost = $validated['cost'];
            unset($validated['cost']);
            if ($cost !== null && $cost !== '') {
                $validated['cost_minor'] = Money::toMinorUnit($cost);
            }
        }

        $updateHealthEvent->handle($currentTeam, $healthEvent, $user, $validated, $quantityUsed);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Health event updated.')]);

        return back();
    }
}

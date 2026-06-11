<?php

namespace App\Http\Controllers\Investors;

use App\Actions\Finance\ResolveTeamFinanceCurrency;
use App\Actions\Investors\CreateInvestorAgreement;
use App\Http\Controllers\Controller;
use App\Http\Requests\Investors\StoreInvestorAgreementRequest;
use App\Models\Team;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Inertia\Inertia;

class InvestorAgreementController extends Controller
{
    public function store(
        StoreInvestorAgreementRequest $request,
        Team $currentTeam,
        ResolveTeamFinanceCurrency $resolveTeamFinanceCurrency,
        CreateInvestorAgreement $createInvestorAgreement,
    ): RedirectResponse {
        $user = $request->user();
        assert($user instanceof User);

        $validated = $request->validated();
        $currency = Money::normalizeCurrency(
            is_string($validated['currency'] ?? null) ? $validated['currency'] : null,
            $resolveTeamFinanceCurrency->handle($currentTeam),
        );

        $document = $request->file('agreement_document');
        assert($document instanceof UploadedFile || $document === null);

        $createInvestorAgreement->handle(
            team: $currentTeam,
            actor: $user,
            attributes: [
                ...Arr::except($validated, ['amount_committed', 'amount_funded', 'agreement_document', 'document_caption']),
                'currency' => $currency,
                'amount_committed_minor' => Money::toMinorUnit((string) $validated['amount_committed']),
                'amount_funded_minor' => Money::toMinorUnit((string) ($validated['amount_funded'] ?? '0')),
            ],
            document: $document,
            caption: is_string($validated['document_caption'] ?? null) ? $validated['document_caption'] : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Investor agreement created.')]);

        return to_route('investors.index', ['current_team' => $currentTeam]);
    }
}

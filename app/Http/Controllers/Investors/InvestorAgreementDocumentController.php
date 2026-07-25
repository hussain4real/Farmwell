<?php

namespace App\Http\Controllers\Investors;

use App\Actions\Investors\AttachInvestorAgreementDocument;
use App\Http\Controllers\Controller;
use App\Http\Requests\Investors\AttachInvestorAgreementDocumentRequest;
use App\Models\InvestorAgreement;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;

class InvestorAgreementDocumentController extends Controller
{
    public function store(
        AttachInvestorAgreementDocumentRequest $request,
        Team $currentTeam,
        InvestorAgreement $investorAgreement,
        AttachInvestorAgreementDocument $attachInvestorAgreementDocument,
    ): RedirectResponse {
        $user = $request->user();
        assert($user instanceof User);

        $document = $request->file('agreement_document');
        assert($document instanceof UploadedFile);

        $caption = $request->validated('document_caption');

        $attachInvestorAgreementDocument->handle(
            team: $currentTeam,
            agreement: $investorAgreement,
            actor: $user,
            document: $document,
            caption: is_string($caption) ? $caption : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Agreement document attached.')]);

        return to_route('investors.index', ['current_team' => $currentTeam]);
    }
}

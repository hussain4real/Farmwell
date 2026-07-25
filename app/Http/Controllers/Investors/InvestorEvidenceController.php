<?php

namespace App\Http\Controllers\Investors;

use App\Enums\InvestorAgreementStatus;
use App\Enums\InvestorVisibilityStatus;
use App\Http\Controllers\Controller;
use App\Models\DistributionRecord;
use App\Models\Expense;
use App\Models\ExternalTransfer;
use App\Models\FarmActivity;
use App\Models\HarvestRecord;
use App\Models\InvestorAgreement;
use App\Models\SaleRecord;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class InvestorEvidenceController extends Controller
{
    public function show(Request $request, Team $currentTeam, Media $media): BinaryFileResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        $subject = $media->model;

        abort_if(! $subject instanceof Model, 404);
        abort_unless((int) $subject->getAttribute('team_id') === $currentTeam->id, 404);

        if ($user->can('viewInvestorAgreements', $currentTeam) || $user->can('viewFinance', $currentTeam)) {
            return response()->download($media->getPath(), $media->file_name);
        }

        abort_unless($user->can('viewInvestorPortal', $currentTeam), 403);
        abort_unless($this->isInvestorVisible($currentTeam, $user, $media, $subject), 404);

        return response()->download($media->getPath(), $media->file_name);
    }

    private function isInvestorVisible(Team $team, User $user, Media $media, Model $subject): bool
    {
        if ($subject instanceof InvestorAgreement) {
            return $subject->investor_id === $user->id
                && in_array($subject->status, [InvestorAgreementStatus::Active, InvestorAgreementStatus::Completed], true);
        }

        if ($subject instanceof Expense || $subject instanceof ExternalTransfer || $subject instanceof HarvestRecord) {
            return $subject->investor_visibility_status === InvestorVisibilityStatus::Approved
                && $this->hasVisibleAgreement($team, $user, $subject);
        }

        if ($subject instanceof SaleRecord) {
            return $this->hasVisibleAgreement($team, $user, $subject)
                && (
                    $subject->investor_visibility_status === InvestorVisibilityStatus::Approved
                    || $subject->distributionRecord?->investor_visibility_status === InvestorVisibilityStatus::Approved
                );
        }

        if ($subject instanceof DistributionRecord) {
            return $subject->investor_visibility_status === InvestorVisibilityStatus::Approved
                && $this->hasVisibleAgreement($team, $user, $subject);
        }

        if ($subject instanceof FarmActivity) {
            return $media->getCustomProperty('visibility') === 'investor_visible'
                && $subject->investor_safe_summary !== null
                && InvestorAgreement::query()
                    ->where('team_id', $team->id)
                    ->where('investor_id', $user->id)
                    ->where('farm_id', $subject->farm_id)
                    ->whereIn('status', [InvestorAgreementStatus::Active->value, InvestorAgreementStatus::Completed->value])
                    ->where(function ($query) use ($subject): void {
                        $query->whereNull('production_cycle_id')
                            ->orWhere('production_cycle_id', $subject->production_cycle_id);
                    })
                    ->exists();
        }

        return false;
    }

    private function hasVisibleAgreement(Team $team, User $user, Model $subject): bool
    {
        return InvestorAgreement::query()
            ->where('team_id', $team->id)
            ->where('id', (int) $subject->getAttribute('investor_agreement_id'))
            ->where('investor_id', $user->id)
            ->whereIn('status', [InvestorAgreementStatus::Active->value, InvestorAgreementStatus::Completed->value])
            ->exists();
    }
}

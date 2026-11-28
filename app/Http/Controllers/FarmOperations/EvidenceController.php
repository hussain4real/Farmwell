<?php

namespace App\Http\Controllers\FarmOperations;

use App\Actions\Media\RenderMediaVariant;
use App\Http\Controllers\Controller;
use App\Models\FarmActivity;
use App\Models\ProductionLog;
use App\Models\Team;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Response;

class EvidenceController extends Controller
{
    /**
     * Serve private evidence or cached image variants after team authorization.
     */
    public function show(Request $request, Team $currentTeam, Media $media, RenderMediaVariant $renderVariant): Response
    {
        Gate::authorize('viewFieldWork', $currentTeam);

        $subject = $media->model;

        abort_if(! $subject instanceof Model, 404);
        abort_unless((int) $subject->getAttribute('team_id') === $currentTeam->id, 404);
        abort_unless($subject instanceof FarmActivity || $subject instanceof ProductionLog || Gate::allows('viewFarmOperations', $currentTeam), 403);

        if ($request->boolean('thumb') || $request->boolean('preview')) {
            abort_unless(in_array($media->mime_type, FarmActivity::InvestorPhotoMimeTypes, true), 404);

            return $renderVariant->toResponse(
                $request,
                $media,
                $request->boolean('thumb') ? RenderMediaVariant::VariantThumb : RenderMediaVariant::VariantPreview,
            );
        }

        return $media->toResponse($request);
    }
}

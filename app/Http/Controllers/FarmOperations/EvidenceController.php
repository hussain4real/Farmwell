<?php

namespace App\Http\Controllers\FarmOperations;

use App\Http\Controllers\Controller;
use App\Models\FarmActivity;
use App\Models\Team;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EvidenceController extends Controller
{
    /**
     * Download private evidence after team authorization.
     */
    public function show(Request $request, Team $currentTeam, Media $media): StreamedResponse
    {
        Gate::authorize('viewFieldWork', $currentTeam);

        $subject = $media->model;

        abort_if(! $subject instanceof Model, 404);
        abort_unless((int) $subject->getAttribute('team_id') === $currentTeam->id, 404);
        abort_unless($subject instanceof FarmActivity || Gate::allows('viewFarmOperations', $currentTeam), 403);

        return $media->toResponse($request);
    }
}

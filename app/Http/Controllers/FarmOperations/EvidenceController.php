<?php

namespace App\Http\Controllers\FarmOperations;

use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EvidenceController extends Controller
{
    /**
     * Download private evidence after team authorization.
     */
    public function show(Team $currentTeam, Media $media): BinaryFileResponse
    {
        Gate::authorize('viewFarmOperations', $currentTeam);

        $subject = $media->model;

        abort_if(! $subject instanceof Model, 404);
        abort_unless((int) $subject->getAttribute('team_id') === $currentTeam->id, 404);

        return response()->download($media->getPath(), $media->file_name);
    }
}

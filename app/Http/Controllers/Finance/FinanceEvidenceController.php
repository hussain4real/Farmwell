<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceEvidenceController extends Controller
{
    public function show(Request $request, Team $currentTeam, Media $media): StreamedResponse
    {
        Gate::authorize('viewFinance', $currentTeam);

        $subject = $media->model;

        abort_if(! $subject instanceof Model, 404);
        abort_unless((int) $subject->getAttribute('team_id') === $currentTeam->id, 404);

        return $media->toResponse($request);
    }
}

<?php

namespace App\Http\Controllers\FarmOperations;

use App\Actions\FarmOperations\BuildCycleSummaryExport;
use App\Http\Controllers\Controller;
use App\Models\Farm;
use App\Models\ProductionCycle;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Spatie\LaravelPdf\Facades\Pdf;

class CycleSummaryExportController extends Controller
{
    public function __invoke(
        Request $request,
        Team $currentTeam,
        Farm $farm,
        ProductionCycle $productionCycle,
        BuildCycleSummaryExport $buildCycleSummaryExport,
    ): Response {
        Gate::authorize('viewFieldWork', $currentTeam);
        abort_unless($productionCycle->farm_id === $farm->id, 404);

        $user = $request->user();
        assert($user instanceof User);

        $summary = $buildCycleSummaryExport->handle($currentTeam, $farm, $productionCycle, $user);
        $html = view('reports.cycle-summary', $summary)->render();
        $pdf = base64_decode(Pdf::html($html)->driver('dompdf')->base64());
        $filename = Str::slug($farm->name.'-'.$productionCycle->name).'-summary.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}

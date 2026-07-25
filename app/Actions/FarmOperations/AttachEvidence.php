<?php

namespace App\Actions\FarmOperations;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\HasMedia;

class AttachEvidence
{
    /**
     * Attach private evidence files to a media-library model.
     *
     * @param  array<int, UploadedFile>  $files
     */
    public function handle(
        HasMedia $model,
        array $files,
        ?User $uploader,
        ?string $caption,
        string $visibility = 'private',
        ?CarbonInterface $capturedOn = null,
    ): void {
        foreach ($files as $file) {
            $model
                ->addMedia($file)
                ->withCustomProperties([
                    'caption' => $caption,
                    'visibility' => $visibility,
                    'captured_on' => $capturedOn?->toDateString(),
                    'uploaded_by_id' => $uploader?->id,
                ])
                ->toMediaCollection('evidence', 'farmwell_private');
        }
    }
}

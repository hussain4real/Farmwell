<?php

namespace App\Actions\Harvests;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\HasMedia;

class AttachHarvestEvidence
{
    /**
     * @param  array<int, UploadedFile>  $files
     */
    public function handle(
        HasMedia $model,
        array $files,
        string $collection,
        ?User $uploader,
        ?string $caption = null,
    ): void {
        foreach ($files as $file) {
            $model
                ->addMedia($file)
                ->withCustomProperties([
                    'caption' => $caption,
                    'visibility' => 'private',
                    'uploaded_by_id' => $uploader?->id,
                ])
                ->toMediaCollection($collection, 'farmwell_private');
        }
    }
}

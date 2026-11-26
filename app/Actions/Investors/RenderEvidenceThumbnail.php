<?php

namespace App\Actions\Investors;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class RenderEvidenceThumbnail
{
    private const int MaxWidth = 480;

    private const int JpegQuality = 75;

    public function handle(Media $media): ?string
    {
        $key = "investor-evidence-thumb:{$media->id}:{$media->size}";

        $cached = Cache::get($key);
        if (is_string($cached)) {
            return $cached;
        }

        $bytes = $this->render($media);

        if ($bytes !== null) {
            Cache::put($key, $bytes, now()->addHours(12));
        }

        return $bytes;
    }

    private function render(Media $media): ?string
    {
        $raw = Storage::disk($media->disk)->get($media->getPathRelativeToRoot());
        if ($raw === null) {
            return null;
        }

        $source = @imagecreatefromstring($raw);
        if ($source === false) {
            return null;
        }

        $target = imagesx($source) > self::MaxWidth
            ? (imagescale($source, self::MaxWidth) ?: $source)
            : $source;

        ob_start();
        imagejpeg($target, null, self::JpegQuality);
        $jpeg = ob_get_clean();

        return is_string($jpeg) && $jpeg !== '' ? $jpeg : null;
    }
}

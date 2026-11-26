<?php

namespace App\Actions\Media;

use GdImage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class RenderMediaVariant
{
    public const string VariantThumb = 'thumb';

    public const string VariantPreview = 'preview';

    /**
     * @var array<string, array{maxWidth: int, quality: int}>
     */
    private const array Variants = [
        self::VariantThumb => ['maxWidth' => 480, 'quality' => 75],
        self::VariantPreview => ['maxWidth' => 1600, 'quality' => 82],
    ];

    public function handle(Media $media, string $variant = self::VariantThumb): ?string
    {
        $config = self::Variants[$variant] ?? self::Variants[self::VariantThumb];
        $key = "media-variant:{$variant}:{$media->id}:{$media->size}";

        $cached = Cache::get($key);
        if (is_string($cached)) {
            return $cached;
        }

        $bytes = $this->render($media, $config['maxWidth'], $config['quality']);

        if ($bytes !== null) {
            Cache::put($key, $bytes, now()->addHours(12));
        }

        return $bytes;
    }

    private function render(Media $media, int $maxWidth, int $quality): ?string
    {
        $raw = Storage::disk($media->disk)->get($media->getPathRelativeToRoot());
        if ($raw === null) {
            return null;
        }

        $source = @imagecreatefromstring($raw);
        if ($source === false) {
            return null;
        }

        $oriented = $media->mime_type === 'image/jpeg' ? $this->orientImage($source, $raw) : $source;
        $target = imagesx($oriented) > $maxWidth
            ? (imagescale($oriented, $maxWidth) ?: $oriented)
            : $oriented;

        ob_start();
        imagejpeg($target, null, $quality);
        $jpeg = ob_get_clean();

        return is_string($jpeg) && $jpeg !== '' ? $jpeg : null;
    }

    private function orientImage(GdImage $image, string $raw): GdImage
    {
        $stream = fopen('php://temp', 'r+b');
        assert(is_resource($stream));
        fwrite($stream, $raw);
        rewind($stream);

        $exif = @exif_read_data($stream);
        fclose($stream);

        $orientation = is_array($exif) ? ($exif['Orientation'] ?? null) : null;

        $angle = match ($orientation) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => null,
        };

        if ($angle === null) {
            return $image;
        }

        return imagerotate($image, $angle, 0) ?: $image;
    }
}

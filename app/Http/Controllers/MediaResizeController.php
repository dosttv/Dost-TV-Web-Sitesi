<?php

namespace App\Http\Controllers;

use App\Support\ResponsiveImage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves resized WebP variants of public-disk images (see ResponsiveImage).
 * Registered without the web middleware group so responses carry no cookies
 * and can be cached by Cloudflare / browsers for a year (uploads use unique
 * ULID filenames, so a path never changes content).
 */
class MediaResizeController extends Controller
{
    private const QUALITY = 80;

    private const CACHE_HEADERS = [
        'Cache-Control' => 'public, max-age=31536000, immutable',
        'Content-Type' => 'image/webp',
    ];

    public function __invoke(int $width, string $path): Response
    {
        if (! in_array($width, ResponsiveImage::WIDTHS, true) || ! ResponsiveImage::isResizable($path)) {
            abort(404);
        }

        $disk = Storage::disk('public');
        $variantPath = ResponsiveImage::variantPath($path, $width);

        $cached = $disk->get($variantPath);
        if ($cached !== null) {
            return response($cached, 200, self::CACHE_HEADERS);
        }

        $original = $disk->get($path);
        if ($original === null) {
            abort(404);
        }

        try {
            $webp = $this->resizeToWebp($original, $width);
        } catch (\Throwable $e) {
            Log::warning("Image resize failed for [{$path}] at {$width}px: {$e->getMessage()}");

            return redirect()->away($disk->url($path), 302);
        }

        $disk->put($variantPath, $webp);

        return response($webp, 200, self::CACHE_HEADERS);
    }

    private function resizeToWebp(string $binary, int $width): string
    {
        // Large uploads (e.g. 4000px PNGs) need more than the default limit to decode.
        ini_set('memory_limit', '512M');

        $source = @imagecreatefromstring($binary);
        if ($source === false) {
            throw new \RuntimeException('Unsupported or corrupt image data.');
        }

        $srcWidth = imagesx($source);
        $srcHeight = imagesy($source);
        $targetWidth = min($width, $srcWidth);
        $targetHeight = max(1, (int) round($srcHeight * $targetWidth / $srcWidth));

        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $srcWidth, $srcHeight);
        imagedestroy($source);

        ob_start();
        imagewebp($target, null, self::QUALITY);
        $webp = (string) ob_get_clean();
        imagedestroy($target);

        if ($webp === '') {
            throw new \RuntimeException('WebP encoding failed.');
        }

        return $webp;
    }
}

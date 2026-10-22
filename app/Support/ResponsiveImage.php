<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Builds URLs for resized WebP variants of images stored on the public disk.
 * Uploaded originals are often multi-megabyte PNGs; the /img route
 * (MediaResizeController) generates each width once, stores it on the disk
 * and serves it with long-lived cache headers so Cloudflare can cache it.
 */
class ResponsiveImage
{
    public const WIDTHS = [160, 320, 480, 640, 960, 1280, 1920];

    public const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    /**
     * Whether $path is a public-disk image we can resize (not an external URL,
     * Livewire temp upload, etc.).
     */
    public static function isResizable(?string $path): bool
    {
        if (blank($path) || str_contains($path, '..') || str_contains($path, '://')) {
            return false;
        }

        if (str_starts_with($path, 'livewire-file:') || str_starts_with($path, 'tmp/')) {
            return false;
        }

        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::EXTENSIONS, true);
    }

    /**
     * URL of the original file, or of an external image as-is.
     */
    public static function originalUrl(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        if (str_starts_with($path, 'http') || str_starts_with($path, 'livewire-file:') || str_starts_with($path, 'tmp/')) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }

    public static function url(?string $path, int $width): ?string
    {
        if (! static::isResizable($path)) {
            return static::originalUrl($path);
        }

        return route('media.resize', ['width' => static::nearestWidth($width), 'path' => ltrim($path, '/')]);
    }

    /**
     * srcset string for the given widths, or null when the image can't be resized.
     */
    public static function srcset(?string $path, array $widths): ?string
    {
        if (! static::isResizable($path)) {
            return null;
        }

        return collect($widths)
            ->map(fn (int $width) => static::url($path, $width).' '.static::nearestWidth($width).'w')
            ->implode(', ');
    }

    public static function nearestWidth(int $width): int
    {
        foreach (self::WIDTHS as $allowed) {
            if ($allowed >= $width) {
                return $allowed;
            }
        }

        return end(self::WIDTHS);
    }

    /**
     * Storage key where the resized variant of $path is cached.
     */
    public static function variantPath(string $path, int $width): string
    {
        $info = pathinfo(ltrim($path, '/'));
        $dir = ($info['dirname'] ?? '.') === '.' ? '' : $info['dirname'].'/';

        return "_resized/w{$width}/{$dir}{$info['filename']}.webp";
    }
}

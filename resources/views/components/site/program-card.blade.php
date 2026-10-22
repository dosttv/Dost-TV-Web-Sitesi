@props([
    'title' => '',
    'coverImage' => null,
    'categories' => [],
    'linkUrl' => null,
    'preview' => false,
    'cardRadius' => null,
    'showTitle' => true,
    'cardRatio' => 'default',
])

@php
    $imageUrl = null;
    $imageSrcset = null;
    if ($coverImage) {
        if (is_string($coverImage)) {
            // Resized WebP variants instead of the multi-MB original upload.
            $imageUrl = \App\Support\ResponsiveImage::url($coverImage, 480);
            $imageSrcset = \App\Support\ResponsiveImage::srcset($coverImage, [320, 480, 640, 960]);
        } elseif (is_object($coverImage) && method_exists($coverImage, 'temporaryUrl')) {
            try {
                $imageUrl = $coverImage->temporaryUrl();
            } catch (\Throwable $e) {
                $imageUrl = null;
            }
        }
    }
    $radiusPx = \App\Services\Home\HomepageBlockRegistry::resolveCardRadiusPx($cardRadius);
    $aspectClass = match ($cardRatio) {
        '16:9' => 'aspect-[16/9]',
        '4:3' => 'aspect-[4/3]',
        '1:1' => 'aspect-square',
        '3:4' => 'aspect-[3/4]',
        default => 'aspect-[3/4]',
    };
@endphp

<div class="group block w-full">
    <div class="{{ $aspectClass }} overflow-hidden ring-1 ring-white/10" style="background-color: var(--color-surface, #0f172a); border-radius: {{ $radiusPx }}px;">
        @if ($imageUrl)
            <img src="{{ $imageUrl }}" @if ($imageSrcset) srcset="{{ $imageSrcset }}" sizes="(max-width: 639px) 60vw, (max-width: 1023px) 40vw, 360px" @endif alt="{{ $title }}" width="300" height="400" loading="lazy" decoding="async" class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
        @else
            <div class="flex h-full w-full items-center justify-center text-4xl font-black text-slate-700">
                {{ mb_substr($title ?: 'P', 0, 1) }}
            </div>
        @endif
    </div>
    @if ($showTitle)
        <p class="dost-card-title mt-2.5 font-semibold text-white group-hover:opacity-85 transition-opacity truncate">{{ $title ?: 'Program Adı' }}</p>
        @if (!empty($categories))
            <p class="dost-card-meta text-xs text-slate-400 truncate">{{ is_array($categories) ? implode(', ', $categories) : $categories }}</p>
        @endif
    @endif
</div>

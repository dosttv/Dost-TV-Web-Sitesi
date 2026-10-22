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
    if ($coverImage) {
        if (is_string($coverImage)) {
            if (str_starts_with($coverImage, 'http') || str_starts_with($coverImage, 'livewire-file:') || str_starts_with($coverImage, 'tmp/')) {
                $imageUrl = $coverImage;
            } else {
                $imageUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($coverImage);
            }
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
            <img src="{{ $imageUrl }}" alt="{{ $title }}" width="300" height="400" loading="lazy" decoding="async" class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
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

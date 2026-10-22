@props([
    'episode' => null,
    'title' => null,
    'programName' => null,
    'thumbnail' => null,
    'linkUrl' => null,
    'duration' => null,
    'cardRadius' => null,
    'showTitle' => true,
])

@php
    $title = $episode?->title ?? $title ?? 'Bölüm Adı';
    $programName = $episode?->program?->name ?? $programName ?? '';
    $thumbnailUrl = $episode?->thumbnail_url ?? null;

    if (! $thumbnailUrl && $thumbnail) {
        if (str_starts_with($thumbnail, 'http')) {
            $thumbnailUrl = $thumbnail;
        } else {
            $thumbnailUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($thumbnail);
        }
    }

    if (! $linkUrl && $episode) {
        $linkUrl = $episode->public_url;
    }

    $radiusPx = \App\Services\Home\HomepageBlockRegistry::resolveCardRadiusPx($cardRadius);
@endphp

<div class="group relative flex flex-col overflow-hidden ring-1 ring-white/10 transition duration-300 hover:-translate-y-1" style="background-color: var(--color-surface, #0f172a); border-radius: {{ $radiusPx }}px;">
    <div class="aspect-video w-full overflow-hidden relative" style="aspect-ratio: 16 / 9; width: 100%; max-width: 100%; overflow: hidden; position: relative; background-color: var(--color-bg, #030712); border-top-left-radius: {{ $radiusPx }}px; border-top-right-radius: {{ $radiusPx }}px;">
        @if ($thumbnailUrl)
            <img src="{{ $thumbnailUrl }}" alt="{{ $title }}" width="640" height="360" loading="lazy" decoding="async" class="h-full w-full object-cover transition duration-500 group-hover:scale-105" style="width: 100%; height: 100%; max-width: 100%; object-fit: cover; display: block; aspect-ratio: 16 / 9;">
        @else
            <div class="flex h-full w-full items-center justify-center text-slate-700">
                <svg class="h-10 w-10 text-slate-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
        @endif

        @if ($duration)
            <span class="absolute bottom-2 right-2 rounded bg-black/85 px-2 py-0.5 text-[10px] font-medium text-slate-200">
                {{ $duration }}
            </span>
        @endif

        <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition duration-300 bg-black/35">
            <div class="flex h-12 w-12 items-center justify-center rounded-full text-white shadow-lg transform group-hover:scale-110 transition" style="background-color: var(--color-accent, #f43f5e);">
                <svg class="h-6 w-6 fill-current" viewBox="0 0 24 24">
                    <path d="M8 5v14l11-7z"/>
                </svg>
            </div>
        </div>
    </div>

    @if ($showTitle)
        <div class="flex flex-1 flex-col p-3.5">
            @if ($programName)
                <p class="dost-card-meta text-[11px] font-semibold tracking-wide uppercase mb-1 truncate" style="color: var(--color-accent, #f43f5e);">{{ $programName }}</p>
            @endif
            <h4 class="dost-card-title line-clamp-2 text-sm font-medium text-slate-200 group-hover:text-white transition">
                @if ($linkUrl)
                    <a href="{{ $linkUrl }}" class="focus:outline-none">
                        <span class="absolute inset-0" aria-hidden="true"></span>
                        {{ $title }}
                    </a>
                @else
                    {{ $title }}
                @endif
            </h4>
        </div>
    @else
        @if ($linkUrl)
            <a href="{{ $linkUrl }}" class="absolute inset-0" aria-label="{{ $title }}"></a>
        @endif
    @endif
</div>

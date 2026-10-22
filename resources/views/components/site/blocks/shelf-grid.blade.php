@props([
    'items' => collect(),
    'displayVariant' => 'grid',
    'desktopColumns' => 4,
    'rowCount' => 1,
    'showArrows' => true,
    'gapSize' => 'md',
    'cardComponent' => 'site.program-card',
    'id' => null,
    'block' => [],
])

@php
    $id = 'shelf-' . ($id ?: \Illuminate\Support\Str::random(8));

    if (! empty($block)) {
        $responsive = \App\Services\Home\HomepageBlockRegistry::resolveResponsiveSettings($block);
    } else {
        $responsive = \App\Services\Home\HomepageBlockRegistry::resolveResponsiveSettings([
            'display_variant' => $displayVariant,
            'desktop_columns' => $desktopColumns,
            'row_count' => $rowCount,
            'show_arrows' => $showArrows,
            'gap_size' => $gapSize,
        ]);
    }

    $desktopCols = $responsive['desktop']['columns'];
    $desktopVariant = $responsive['desktop']['display_variant'];
    $desktopGapPx = \App\Services\Home\HomepageBlockRegistry::resolveGapPx($responsive['desktop']['gap_size']);
    $desktopShowArrows = $responsive['desktop']['show_arrows'];

    $tabletCols = $responsive['tablet']['columns'];
    $tabletVariant = $responsive['tablet']['display_variant'];
    $tabletGapPx = \App\Services\Home\HomepageBlockRegistry::resolveGapPx($responsive['tablet']['gap_size']);
    $tabletShowArrows = $responsive['tablet']['show_arrows'];

    $mobileCols = $responsive['mobile']['columns'];
    $mobileVariant = $responsive['mobile']['display_variant'];
    $mobileGapPx = \App\Services\Home\HomepageBlockRegistry::resolveGapPx($responsive['mobile']['gap_size']);
    $mobileShowArrows = $responsive['mobile']['show_arrows'];

    $rows = max(1, min((int) ($block['row_count'] ?? $rowCount), 3));
    $resolvedCardRadius = $block['card_radius'] ?? $block['card_radius_custom'] ?? null;
    $showProgramTitles = isset($block['show_program_titles']) ? (bool) $block['show_program_titles'] : true;
    $showVideoTitles = isset($block['show_video_titles']) ? (bool) $block['show_video_titles'] : true;

    $resolvedCardWidthPx = \App\Services\Home\HomepageBlockRegistry::resolveCardWidthPx($block['card_width'] ?? null, $block['card_width_custom'] ?? null);
    $resolvedCardRatio = $block['card_ratio'] ?? 'default';
@endphp

@if ($items->isEmpty())
    <div class="rounded-xl border border-dashed border-slate-800 p-8 text-center text-xs text-slate-500">
        Bu bölümde henüz içerik bulunmuyor.
    </div>
@else
    {{-- Dynamic Component CSS Output BEFORE DOM Render to Prevent CLS/FOUC --}}
    <style>
        #{{ $id }} .carousel-scrollbar-hidden {
            scrollbar-width: none !important;
            -ms-overflow-style: none !important;
        }
        #{{ $id }} .carousel-scrollbar-hidden::-webkit-scrollbar {
            display: none !important;
            width: 0 !important;
            height: 0 !important;
        }

        /* Mobil Viewport (< 640px) */
        @media (max-width: 639px) {
            #{{ $id }} .shelf-container {
                @if (in_array($mobileVariant, ['horizontal_carousel', 'shelf', 'carousel'], true))
                    display: grid !important;
                    grid-auto-flow: column !important;
                    grid-template-rows: repeat({{ $rows }}, minmax(0, 1fr)) !important;
                    grid-auto-columns: calc((100% - {{ max(0, $mobileCols - 1) * $mobileGapPx }}px) / {{ $mobileCols }}) !important;
                    gap: {{ $mobileGapPx }}px !important;
                    overflow-x: auto !important;
                    -webkit-overflow-scrolling: touch !important;
                    overscroll-behavior-x: contain !important;
                    scroll-snap-type: x mandatory !important;
                @else
                    display: grid !important;
                    grid-template-columns: repeat({{ $mobileCols }}, minmax(0, 1fr)) !important;
                    gap: {{ $mobileGapPx }}px !important;
                    overflow-x: hidden !important;
                    scroll-snap-type: none !important;
                @endif
            }
            #{{ $id }} .dost-card-title {
                font-size: {{ $responsive['mobile']['card_title_size'] ?? 14 }}px !important;
            }
            #{{ $id }} .dost-card-meta {
                font-size: {{ $responsive['mobile']['meta_size'] ?? 11 }}px !important;
            }
            #{{ $id }} .shelf-arrow-left,
            #{{ $id }} .shelf-arrow-right {
                display: {{ $mobileShowArrows ? 'flex' : 'none' }} !important;
            }
        }

        /* Tablet Viewport (640px - 1023px) */
        @media (min-width: 640px) and (max-width: 1023px) {
            #{{ $id }} .shelf-container {
                @if (in_array($tabletVariant, ['horizontal_carousel', 'shelf', 'carousel'], true))
                    display: grid !important;
                    grid-auto-flow: column !important;
                    grid-template-rows: repeat({{ $rows }}, minmax(0, 1fr)) !important;
                    grid-auto-columns: calc((100% - {{ max(0, $tabletCols - 1) * $tabletGapPx }}px) / {{ $tabletCols }}) !important;
                    gap: {{ $tabletGapPx }}px !important;
                    overflow-x: auto !important;
                    scroll-snap-type: x mandatory !important;
                @else
                    display: grid !important;
                    grid-template-columns: repeat({{ $tabletCols }}, minmax(0, 1fr)) !important;
                    gap: {{ $tabletGapPx }}px !important;
                    overflow-x: visible !important;
                    scroll-snap-type: none !important;
                @endif
            }
            #{{ $id }} .dost-card-title {
                font-size: {{ $responsive['tablet']['card_title_size'] ?? 15 }}px !important;
            }
            #{{ $id }} .dost-card-meta {
                font-size: {{ $responsive['tablet']['meta_size'] ?? 12 }}px !important;
            }
            #{{ $id }} .shelf-arrow-left,
            #{{ $id }} .shelf-arrow-right {
                display: {{ $tabletShowArrows ? 'flex' : 'none' }} !important;
            }
        }

        /* Masaüstü Viewport (>= 1024px) */
        @media (min-width: 1024px) {
            #{{ $id }} .shelf-container {
                @if (in_array($desktopVariant, ['horizontal_carousel', 'shelf', 'carousel'], true))
                    display: grid !important;
                    grid-auto-flow: column !important;
                    grid-template-rows: repeat({{ $rows }}, minmax(0, 1fr)) !important;
                    @if ($resolvedCardWidthPx)
                        grid-auto-columns: {{ $resolvedCardWidthPx }}px !important;
                    @else
                        grid-auto-columns: calc((100% - {{ max(0, $desktopCols - 1) * $desktopGapPx }}px) / {{ $desktopCols }}) !important;
                    @endif
                    gap: {{ $desktopGapPx }}px !important;
                    overflow-x: auto !important;
                    scroll-snap-type: x mandatory !important;
                @else
                    display: grid !important;
                    @if ($resolvedCardWidthPx)
                        grid-template-columns: repeat(auto-fill, minmax({{ $resolvedCardWidthPx }}px, {{ $resolvedCardWidthPx }}px)) !important;
                        justify-content: {{ ($block['card_alignment'] ?? 'left') === 'center' ? 'center' : 'start' }} !important;
                    @else
                        grid-template-columns: repeat({{ $desktopCols }}, minmax(0, 1fr)) !important;
                    @endif
                    gap: {{ $desktopGapPx }}px !important;
                    overflow-x: visible !important;
                    scroll-snap-type: none !important;
                @endif
            }
            #{{ $id }} .dost-card-title {
                font-size: {{ $responsive['desktop']['card_title_size'] ?? 16 }}px !important;
            }
            #{{ $id }} .dost-card-meta {
                font-size: {{ $responsive['desktop']['meta_size'] ?? 13 }}px !important;
            }
            #{{ $id }} .shelf-arrow-left,
            #{{ $id }} .shelf-arrow-right {
                display: {{ $desktopShowArrows ? 'flex' : 'none' }} !important;
            }
        }
    </style>

    <div id="{{ $id }}"
         x-data="{
             canScrollLeft: false,
             canScrollRight: true,
             _ticking: false,

             updateNav() {
                 if (this._ticking) return;
                 this._ticking = true;
                 requestAnimationFrame(() => {
                     const el = this.$refs.sliderContainer;
                     if (el) {
                         this.canScrollLeft = el.scrollLeft > 4;
                         this.canScrollRight = el.scrollLeft + el.clientWidth < el.scrollWidth - 4;
                     }
                     this._ticking = false;
                 });
             },

             scrollLeft() {
                 const el = this.$refs.sliderContainer;
                 if (!el) return;
                 el.scrollBy({
                     left: -(el.clientWidth * 0.8),
                     behavior: 'smooth'
                 });
             },

             scrollRight() {
                 const el = this.$refs.sliderContainer;
                 if (!el) return;
                 el.scrollBy({
                     left: el.clientWidth * 0.8,
                     behavior: 'smooth'
                 });
             }
         }"
         x-init="$nextTick(() => updateNav())"
         class="relative group/shelf w-full">

        {{-- Sol Navigasyon Oku --}}
        <button type="button"
                @click="scrollLeft()"
                x-show="canScrollLeft"
                x-cloak
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                class="shelf-arrow-left absolute left-1 sm:-left-4 lg:-left-5 top-1/2 -translate-y-1/2 z-30 flex h-9 w-9 sm:h-11 sm:w-11 items-center justify-center rounded-full bg-slate-950/80 text-white/90 border border-white/15 shadow-xl backdrop-blur-sm transition-colors duration-200 hover:bg-slate-900 hover:border-rose-500/80 hover:text-rose-400 focus:outline-none"
                aria-label="Önceki">
            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
        </button>

        {{-- Ana Konteyner (CSS Media Queries ile Cihaz Bazlı Grid / Carousel) --}}
        <div x-ref="sliderContainer"
             @scroll.passive="updateNav()"
             @resize.window.debounce.100ms.passive="updateNav()"
             class="shelf-container carousel-scrollbar-hidden pb-4 pt-1"
             style="--cols-desk: {{ $desktopCols }}; --cols-tab: {{ $tabletCols }}; --cols-mob: {{ $mobileCols }};">
            @foreach ($items as $item)
                <div class="shelf-item snap-start flex-shrink-0 w-full min-w-0">
                    @if ($cardComponent === 'site.video-card' || $item instanceof \App\Models\Episode)
                        <x-site.video-card :episode="$item" :card-radius="$resolvedCardRadius" :show-title="$showVideoTitles" />
                    @else
                        <a href="{{ route('programs.show', $item) }}" class="block w-full">
                            <x-site.program-card :title="$item->name" :cover-image="$item->cover_image" :card-radius="$resolvedCardRadius" :card-ratio="$resolvedCardRatio" :show-title="$showProgramTitles" />
                        </a>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Sağ Navigasyon Oku --}}
        <button type="button"
                @click="scrollRight()"
                x-show="canScrollRight"
                x-cloak
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                class="shelf-arrow-right absolute right-1 sm:-right-4 lg:-right-5 top-1/2 -translate-y-1/2 z-30 flex h-9 w-9 sm:h-11 sm:w-11 items-center justify-center rounded-full bg-slate-950/80 text-white/90 border border-white/15 shadow-xl backdrop-blur-sm transition-colors duration-200 hover:bg-slate-900 hover:border-rose-500/80 hover:text-rose-400 focus:outline-none"
                aria-label="Sonraki">
            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
            </svg>
        </button>
    </div>
@endif

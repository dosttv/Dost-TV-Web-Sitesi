@props([
    'preview' => false,
    'siteSettings' => null,
    'siteName' => null,
    'logo' => null,
    'logoAltText' => null,
    'liveButtonVisible' => null,
    'liveButtonText' => null,
    'headerSticky' => null,
    'searchVisible' => null,
    'fixedSettings' => null,
])

@php
    $siteSettings = $siteSettings ?? \App\Models\SiteSetting::current();

    $siteName = $siteName ?? ($siteSettings->site_name ?? 'Dost TV');
    $logo = $logo ?? ($siteSettings->logo ?? null);
    $logoAltText = $logoAltText ?? ($siteSettings->logo_alt_text ?: $siteName);
    $liveButtonVisible = $liveButtonVisible ?? (($siteSettings->live_button_is_visible ?? true) && ($siteSettings->live_tv_is_public ?? true));
    $liveButtonText = $liveButtonText ?? ($siteSettings->live_button_text && $siteSettings->live_button_text !== 'Canlı İzle' ? $siteSettings->live_button_text : 'Canlı');
    $headerSticky = $headerSticky ?? ($siteSettings->header_is_sticky ?? true);
    $searchVisible = $searchVisible ?? ($siteSettings->search_is_visible ?? true);

    $headerResponsive = $siteSettings->normalized_header_responsive_settings ?? \App\Models\SiteSetting::getDefaultHeaderResponsiveSettings();

    $mH = $headerResponsive['mobile']['header_height'] ?? 60;
    $mW = $headerResponsive['mobile']['logo_width'] ?? 105;
    $mLiveH = $headerResponsive['mobile']['live_button_height'] ?? 32;
    $mLiveF = $headerResponsive['mobile']['live_button_font_size'] ?? 12;
    $mPx = $headerResponsive['mobile']['header_horizontal_padding'] ?? 12;

    $tH = $headerResponsive['tablet']['header_height'] ?? 72;
    $tW = $headerResponsive['tablet']['logo_width'] ?? 120;
    $tLiveH = $headerResponsive['tablet']['live_button_height'] ?? 36;
    $tLiveF = $headerResponsive['tablet']['live_button_font_size'] ?? 13;
    $tPx = $headerResponsive['tablet']['header_horizontal_padding'] ?? 16;

    $dH = $headerResponsive['desktop']['header_height'] ?? 80;
    $dW = $headerResponsive['desktop']['logo_width'] ?? 140;
    $dLiveH = $headerResponsive['desktop']['live_button_height'] ?? 40;
    $dLiveF = $headerResponsive['desktop']['live_button_font_size'] ?? 14;
    $dPx = $headerResponsive['desktop']['header_horizontal_padding'] ?? 24;

    if ($fixedSettings === null) {
        $activeLayout = \App\Models\HomepageLayout::query()->where('is_active', true)->first();
        $publishedSections = $activeLayout?->published_sections ?? [];
        $fixedSettings = $publishedSections['_fixed_settings'] ?? [];
    }

    $headerConfig = $fixedSettings['header'] ?? [];
    if (!empty($headerConfig['header_height'])) {
        $dH = (int) $headerConfig['header_height'];
    }
    if (!empty($headerConfig['logo_size'])) {
        $dW = (int) $headerConfig['logo_size'];
    }
    if (!empty($headerConfig['section_padding_x'])) {
        $dPx = (int) $headerConfig['section_padding_x'];
    }

    $headerId = 'dost-site-header-' . \Illuminate\Support\Str::random(6);

    $logoUrl = null;
    if (!empty($logo)) {
        $rawLogo = is_array($logo) ? reset($logo) : $logo;
        if (is_object($rawLogo) && method_exists($rawLogo, 'temporaryUrl')) {
            try {
                $logoUrl = $rawLogo->temporaryUrl();
            } catch (\Throwable $e) {
                $logoUrl = null;
            }
        } elseif (is_string($rawLogo) && !empty(trim($rawLogo))) {
            $logoUrl = \App\Support\ResponsiveImage::url($rawLogo, 640);
        }
    }
@endphp

<header id="{{ $headerId }}" x-data="{ mobileMenuOpen: false }" @keydown.escape.window="mobileMenuOpen = false" class="{{ ($headerSticky && !$preview) ? 'sticky top-0' : 'relative' }} z-40 border-b border-white/5 bg-slate-950/95 md:bg-slate-950/80 md:backdrop-blur rounded-xl">
    <div class="dost-header-inner mx-auto flex max-w-7xl items-center justify-between">
        {{-- Logo Section --}}
        <a href="{{ $preview ? 'javascript:void(0)' : route('home') }}" class="flex shrink-0 items-center gap-2">
            @if(!empty($logoUrl))
                <img src="{{ $logoUrl }}" alt="{{ $logoAltText }}" class="dost-header-logo-img h-auto object-contain lg:w-[120px] lg:max-w-[120px]">
            @else
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-rose-500 to-amber-400 font-black text-slate-950 text-sm sm:h-9 sm:w-9 sm:text-base">D</span>
                <span class="text-base font-bold tracking-tight text-white sm:text-lg">Dost<span class="text-rose-500">TV</span></span>
            @endif
        </a>

        {{-- Navigation Menu Section --}}
        <x-site.menu location="header_primary" :logo-url="$logoUrl" :logo-alt-text="$logoAltText" :preview="$preview" />

        {{-- Right Section: Search, Hamburger & Live Dropdown CTA --}}
        <div class="flex items-center gap-2 sm:gap-3" x-data="{ searchOpen: false }" @keydown.escape.window="searchOpen = false">
            @if($searchVisible)
                <form action="{{ $preview ? 'javascript:void(0)' : route('search.index') }}" method="GET"
                      x-show="searchOpen" style="display: none;"
                      @submit="if (! $refs.searchInput.value.trim()) $event.preventDefault()">
                    <input type="text" name="q" required x-ref="searchInput"
                           x-effect="searchOpen && $nextTick(() => $refs.searchInput.focus())"
                           placeholder="Program veya bölüm ara..."
                           class="w-32 rounded-full bg-white/5 px-3 py-1 text-xs text-white placeholder:text-slate-500 ring-1 ring-inset ring-white/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500 sm:w-56 sm:text-sm sm:px-4 sm:py-1.5">
                </form>

                <button type="button" @click="searchOpen = !searchOpen"
                        class="rounded p-1.5 text-slate-400 transition hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500 min-h-[36px] min-w-[36px] flex items-center justify-center"
                        aria-label="Arama">
                    <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                    </svg>
                </button>
            @endif

            {{-- Mobile Hamburger Button (< 768px) --}}
            <button type="button"
                    @click="mobileMenuOpen = true"
                    class="flex h-9 w-9 items-center justify-center rounded-lg p-1.5 text-slate-300 transition hover:bg-white/10 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-500 md:hidden"
                    aria-label="Menüyü Aç"
                    :aria-expanded="mobileMenuOpen"
                    aria-controls="mobile-navigation-drawer">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                </svg>
            </button>

            @if($liveButtonVisible)
                <a href="{{ $preview ? 'javascript:void(0)' : route('live.tv') }}"
                   class="dost-header-live-btn hidden md:inline-flex group shrink-0 items-center gap-1.5 sm:gap-2 rounded-full bg-rose-600 font-semibold text-white shadow-lg shadow-rose-600/30 transition hover:bg-rose-500">
                    <span class="relative flex h-2 w-2">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-white opacity-75"></span>
                        <span class="relative inline-flex h-2 w-2 rounded-full bg-white"></span>
                    </span>
                    <span>{{ $liveButtonText }}</span>
                </a>
            @endif
        </div>
    </div>

    <style>
        #{{ $headerId }} .dost-header-inner {
            height: {{ $mH }}px;
            padding-left: {{ $mPx }}px;
            padding-right: {{ $mPx }}px;
        }
        #{{ $headerId }} .dost-header-logo-img {
            max-width: {{ $mW }}px;
            max-height: {{ max(24, (int)round($mH * 0.55)) }}px;
        }
        #{{ $headerId }} .dost-header-live-btn {
            height: {{ $mLiveH }}px;
            font-size: {{ $mLiveF }}px;
            padding-left: 10px;
            padding-right: 10px;
        }
        @media (min-width: 640px) {
            #{{ $headerId }} .dost-header-inner {
                height: {{ $tH }}px;
                padding-left: {{ $tPx }}px;
                padding-right: {{ $tPx }}px;
            }
            #{{ $headerId }} .dost-header-logo-img {
                max-width: {{ $tW }}px;
                max-height: {{ max(28, (int)round($tH * 0.55)) }}px;
            }
            #{{ $headerId }} .dost-header-live-btn {
                height: {{ $tLiveH }}px;
                font-size: {{ $tLiveF }}px;
                padding-left: 14px;
                padding-right: 14px;
            }
        }
        @media (min-width: 1024px) {
            #{{ $headerId }} .dost-header-inner {
                height: {{ $dH }}px;
                padding-left: {{ $dPx }}px;
                padding-right: {{ $dPx }}px;
            }
            #{{ $headerId }} .dost-header-logo-img {
                width: 120px;
                max-width: 120px;
                max-height: {{ max(48, (int)round($dH * 0.65)) }}px;
            }
            #{{ $headerId }} .dost-header-live-btn {
                height: {{ $dLiveH }}px;
                font-size: {{ $dLiveF }}px;
                padding-left: 16px;
                padding-right: 16px;
            }
        }
    </style>
</header>

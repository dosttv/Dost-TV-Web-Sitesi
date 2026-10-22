@props([
    'preview' => false,
    'siteSettings' => null,
    'fixedSettings' => null,
    'footerLogo' => null,
    'footerDescription' => null,
    'recommendedSites' => null,
    'phone' => null,
    'email' => null,
    'facebookUrl' => null,
    'instagramUrl' => null,
    'xUrl' => null,
    'youtubeUrl' => null,
    'whatsappUrl' => null,
    'telegramUrl' => null,
    'copyrightText' => null,
])

@php
    $siteSettings = $siteSettings ?? \App\Models\SiteSetting::current();

    $phone = $phone ?? $siteSettings->phone;
    $email = $email ?? $siteSettings->email;

    $facebookUrl = $facebookUrl ?? $siteSettings->facebook_url;
    $instagramUrl = $instagramUrl ?? $siteSettings->instagram_url;
    $xUrl = $xUrl ?? $siteSettings->x_url;
    $youtubeUrl = $youtubeUrl ?? $siteSettings->youtube_url;
    $whatsappUrl = $whatsappUrl ?? $siteSettings->whatsapp_url;
    $telegramUrl = $telegramUrl ?? $siteSettings->telegram_url;

    $recommendedSites = $recommendedSites ?? ($siteSettings->recommended_sites ?? []);
    $activeRecommendedSites = collect($recommendedSites)->filter(function ($item) {
        if (! is_array($item)) {
            return false;
        }
        return ! isset($item['is_active']) || $item['is_active'] === true || $item['is_active'] === '1' || $item['is_active'] === 1;
    })->values();

    $copyrightText = $copyrightText ?? ($siteSettings->copyright_text ?: '© {year} Dost TV. Tüm hakları saklıdır.');
    $copyrightText = str_replace('{year}', now()->year, $copyrightText);

    // 1. Column: Kurumsal pages (page_type = 'corporate' AND show_in_footer = true)
    $corporatePages = \App\Models\Page::query()
        ->where('page_type', 'corporate')
        ->where('show_in_footer', true)
        ->orderBy('sort_order')
        ->orderBy('title')
        ->get();

    if ($fixedSettings === null) {
        $activeLayout = \App\Models\HomepageLayout::query()->where('is_active', true)->first();
        $publishedSections = $activeLayout?->published_sections ?? [];
        $fixedSettings = $publishedSections['_fixed_settings'] ?? [];
    }

    $footerConfig = $fixedSettings['footer'] ?? [];
    $paddingTop = (int) ($footerConfig['section_padding_top'] ?? 48);
    $paddingBottom = (int) ($footerConfig['section_padding_bottom'] ?? 56);
    $paddingX = (int) ($footerConfig['section_padding_x'] ?? 32);
@endphp

<footer class="mt-0 text-slate-300 overflow-hidden" style="background-color: var(--color-bg); padding-top: {{ $paddingTop }}px; padding-bottom: {{ $paddingBottom }}px; padding-left: {{ $paddingX }}px; padding-right: {{ $paddingX }}px;">
    <style>
        .dost-footer-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 2rem;
            align-items: start;
        }
        @media (min-width: 640px) and (max-width: 767px) {
            .dost-footer-grid {
                grid-template-columns: repeat({{ $activeRecommendedSites->isNotEmpty() ? 2 : 2 }}, minmax(0, 1fr));
            }
        }
        @media (min-width: 768px) {
            .dost-footer-grid {
                display: grid !important;
                grid-template-columns: {{ $activeRecommendedSites->isNotEmpty() ? '4fr 4fr 4fr' : '7fr 5fr' }} !important;
                gap: 2.5rem !important;
                align-items: start !important;
            }
        }
    </style>

    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8 space-y-8">

        <div class="dost-footer-grid pb-4">
            
            {{-- Column 1: Kurumsal --}}
            <div class="space-y-3">
                <h4 class="text-sm font-bold uppercase tracking-wider text-white mb-2">Kurumsal</h4>
                @if($corporatePages->isNotEmpty())
                    <ul class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                        @foreach($corporatePages as $page)
                            <li>
                                <a href="{{ $preview ? 'javascript:void(0)' : route('pages.show', $page->slug) }}" class="text-slate-400 hover:text-white transition flex items-center gap-1.5 leading-snug">
                                    <span class="text-rose-500 font-bold">›</span>
                                    <span>{{ $page->title }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-xs text-slate-500">Kurumsal sayfa bulunamadı.</p>
                @endif
            </div>

            {{-- Column 2: Önerilen Siteler (Rendered only if active sites exist) --}}
            @if($activeRecommendedSites->isNotEmpty())
                <div class="space-y-3">
                    <h4 class="text-sm font-bold uppercase tracking-wider text-white mb-2">Önerilen Siteler</h4>
                    <div class="grid grid-cols-1 gap-2">
                        @foreach($activeRecommendedSites as $site)
                            @php
                                $siteName = is_string($site['name'] ?? null) ? $site['name'] : 'Site';
                                $siteUrl = is_string($site['url'] ?? null) ? $site['url'] : '#';
                                $siteLogo = $site['logo'] ?? null;
                                if (is_array($siteLogo)) {
                                    $siteLogo = reset($siteLogo) ?: null;
                                }

                                $targetBlank = ! empty($site['target_blank']);

                                $logoUrl = null;
                                if (is_string($siteLogo) && filled(trim($siteLogo))) {
                                    $siteLogo = trim($siteLogo);
                                    if (str_starts_with($siteLogo, 'http://') || str_starts_with($siteLogo, 'https://')) {
                                        $logoUrl = $siteLogo;
                                    } else {
                                        $cleanPath = ltrim(preg_replace('/^\/?storage\//', '', $siteLogo), '/');
                                        if (\Illuminate\Support\Facades\Storage::disk('public')->exists($cleanPath)) {
                                            $logoUrl = \App\Support\ResponsiveImage::url($cleanPath, 160);
                                        }
                                    }
                                }
                            @endphp
                            <a href="{{ $preview ? 'javascript:void(0)' : $siteUrl }}"
                               target="{{ $preview ? '_self' : ($targetBlank ? '_blank' : '_self') }}"
                               @if($targetBlank && ! $preview) rel="noopener noreferrer" @endif
                               class="group flex items-center gap-3 p-1.5 pr-3 rounded-xl bg-slate-900/40 border border-white/[0.06] hover:bg-slate-900/90 hover:border-rose-500/30 transition-all duration-200">
                                
                                @if($logoUrl)
                                    <img src="{{ $logoUrl }}" alt="{{ $siteName }}" loading="lazy" class="w-10 h-10 rounded-full object-cover shrink-0 ring-1 ring-white/10 group-hover:ring-rose-500/50 transition">
                                @else
                                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-slate-800 to-slate-900 text-rose-400 font-bold text-sm flex items-center justify-center shrink-0 ring-1 ring-white/10 group-hover:ring-rose-500/50 transition">
                                        {{ mb_strtoupper(mb_substr($siteName, 0, 1)) }}
                                    </div>
                                @endif

                                <span class="text-xs font-semibold text-slate-300 group-hover:text-white transition line-clamp-1">
                                    {{ $siteName }}
                                </span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Column 3: Sosyal Medya --}}
            <div class="space-y-3">
                <h4 class="text-sm font-bold uppercase tracking-wider text-white mb-2">Sosyal Medya</h4>
                @if($instagramUrl || $facebookUrl || $youtubeUrl || $xUrl || $whatsappUrl || $telegramUrl)
                    <div class="flex flex-wrap gap-2">
                        @if($instagramUrl)
                            <a href="{{ $preview ? 'javascript:void(0)' : $instagramUrl }}" target="{{ $preview ? '_self' : '_blank' }}" rel="noopener noreferrer" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-slate-900/80 border border-slate-800 hover:bg-slate-800 hover:border-slate-700 hover:text-white text-xs font-medium text-slate-300 transition" aria-label="Instagram">
                                <svg width="20" height="20" style="width: 20px; height: 20px; min-width: 20px; min-height: 20px;" class="w-5 h-5 text-pink-500 shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                                <span>Instagram</span>
                            </a>
                        @endif

                        @if($facebookUrl)
                            <a href="{{ $preview ? 'javascript:void(0)' : $facebookUrl }}" target="{{ $preview ? '_self' : '_blank' }}" rel="noopener noreferrer" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-slate-900/80 border border-slate-800 hover:bg-slate-800 hover:border-slate-700 hover:text-white text-xs font-medium text-slate-300 transition" aria-label="Facebook">
                                <svg width="20" height="20" style="width: 20px; height: 20px; min-width: 20px; min-height: 20px;" class="w-5 h-5 text-blue-500 shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                                <span>Facebook</span>
                            </a>
                        @endif

                        @if($youtubeUrl)
                            <a href="{{ $preview ? 'javascript:void(0)' : $youtubeUrl }}" target="{{ $preview ? '_self' : '_blank' }}" rel="noopener noreferrer" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-slate-900/80 border border-slate-800 hover:bg-slate-800 hover:border-slate-700 hover:text-white text-xs font-medium text-slate-300 transition" aria-label="YouTube">
                                <svg width="20" height="20" style="width: 20px; height: 20px; min-width: 20px; min-height: 20px;" class="w-5 h-5 text-red-500 shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                                <span>YouTube</span>
                            </a>
                        @endif

                        @if($xUrl)
                            <a href="{{ $preview ? 'javascript:void(0)' : $xUrl }}" target="{{ $preview ? '_self' : '_blank' }}" rel="noopener noreferrer" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-slate-900/80 border border-slate-800 hover:bg-slate-800 hover:border-slate-700 hover:text-white text-xs font-medium text-slate-300 transition" aria-label="X / Twitter">
                                <svg width="20" height="20" style="width: 20px; height: 20px; min-width: 20px; min-height: 20px;" class="w-5 h-5 text-slate-300 shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                                <span>X</span>
                            </a>
                        @endif

                        @if($whatsappUrl)
                            <a href="{{ $preview ? 'javascript:void(0)' : $whatsappUrl }}" target="{{ $preview ? '_self' : '_blank' }}" rel="noopener noreferrer" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-slate-900/80 border border-slate-800 hover:bg-slate-800 hover:border-slate-700 hover:text-white text-xs font-medium text-slate-300 transition" aria-label="WhatsApp">
                                <svg width="20" height="20" style="width: 20px; height: 20px; min-width: 20px; min-height: 20px;" class="w-5 h-5 text-emerald-500 shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-1.146 4.187 4.289-1.125z"/></svg>
                                <span>WhatsApp</span>
                            </a>
                        @endif

                        @if($telegramUrl)
                            <a href="{{ $preview ? 'javascript:void(0)' : $telegramUrl }}" target="{{ $preview ? '_self' : '_blank' }}" rel="noopener noreferrer" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-slate-900/80 border border-slate-800 hover:bg-slate-800 hover:border-slate-700 hover:text-white text-xs font-medium text-slate-300 transition" aria-label="Telegram">
                                <svg width="20" height="20" style="width: 20px; height: 20px; min-width: 20px; min-height: 20px;" class="w-5 h-5 text-sky-400 shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0C5.37 0 0 5.37 0 12s5.37 12 12 12 12-5.37 12-12S18.63 0 12 0zm5.562 8.161c-.18.717-.962 4.084-1.362 5.754-.168.706-.426.942-.676.965-.546.049-.961-.359-1.492-.707-.83-.545-1.3-883-2.106-1.413-.932-.614-.398-1.042.062-1.518.12-.125 2.197-2.013 2.237-2.183.005-.021.01-.1-.013-.133-.024-.033-.082-.022-.12-.014-.055.011-.926.587-2.613 1.727-.247.169-.471.252-.672.247-.221-.005-.647-.125-.964-.228-.39-.127-.698-.194-.672-.41.014-.112.169-.227.468-.344 1.831-.798 3.052-1.324 3.665-1.579 1.745-.724 2.108-.85 2.345-.854.052 0 .168.012.244.074.064.053.082.124.09.174.008.05.018.163.01.272z"/></svg>
                                <span>Telegram</span>
                            </a>
                        @endif
                    </div>
                @else
                    <p class="text-xs text-slate-500">Sosyal medya bağlantısı eklenmedi.</p>
                @endif
            </div>

        </div>

        {{-- Single Bottom Copyright Bar --}}
        <div class="pt-4 text-center text-xs text-slate-400">
            <p>{{ $copyrightText }}</p>
        </div>

    </div>
</footer>

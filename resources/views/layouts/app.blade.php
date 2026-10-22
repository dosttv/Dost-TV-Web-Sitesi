@php
    $siteSettings = $siteSettings ?? \App\Models\SiteSetting::current();

    // Theme Preview Token & Session Resolution
    $previewToken = request('theme_preview_token') ?? session('theme_preview_token');
    $previewData = null;

    if ($previewToken) {
        $previewData = \Illuminate\Support\Facades\Cache::get('theme_preview_' . $previewToken) ?? session('theme_preview_data');
    } else {
        $previewData = session('theme_preview_data');
    }

    $isPreviewActive = false;
    $previewThemeSettings = null;

    if ($previewData && is_array($previewData)) {
        if (auth()->check() && auth()->user()?->hasAnyRole(['super_admin', 'administrator', 'designer', 'editor'])) {
            $isPreviewActive = true;
            $previewThemeSettings = $previewData['theme_settings'] ?? null;

            if ($previewToken && ! session()->has('theme_preview_token')) {
                session()->put('theme_preview_token', $previewToken);
                session()->put('theme_preview_data', $previewData);
            }
        }
    }

    if ($isPreviewActive && session()->has('theme_preview_mode')) {
        $overrideMode = session('theme_preview_mode');
        if (is_array($previewThemeSettings)) {
            $previewThemeSettings['mode'] = $overrideMode;
        } else {
            $previewThemeSettings = array_merge($siteSettings->normalized_theme_settings, ['mode' => $overrideMode]);
        }
    }

    $activeThemeSettings = $previewThemeSettings ?? $siteSettings->normalized_theme_settings;
    $activeThemeMode = $activeThemeSettings['mode'] ?? 'dark';

    $rawTitle = trim(View::yieldContent('title'));
    $titleSuffix = $siteSettings->title_suffix ?? '| DOST TV';

    if (!empty($rawTitle)) {
        if (preg_match('/^(.*?)\s*[-|]\s*Dost TV$/ui', $rawTitle, $matches)) {
            $cleanTitle = trim($matches[1]);
            $pageTitle = $cleanTitle . (filled($titleSuffix) ? ' ' . trim($titleSuffix) : '');
        } elseif (filled($titleSuffix) && !str_ends_with($rawTitle, trim($titleSuffix))) {
            $pageTitle = $rawTitle . ' ' . trim($titleSuffix);
        } else {
            $pageTitle = $rawTitle;
        }
    } else {
        $pageTitle = ($siteSettings->site_name ?? 'Dost TV') . (filled($titleSuffix) ? ' ' . trim($titleSuffix) : '');
    }

    $metaDescription = trim(View::yieldContent('description'));
    if (empty($metaDescription)) {
        $metaDescription = $siteSettings->default_meta_description ?: 'Dost TV - Uydu üzerinden yayın yapan Türkçe TV kanalı. Canlı TV, canlı radyo, program arşivi ve yayın akışı.';
    }

    $ogImage = trim(View::yieldContent('og_image'));
    if (empty($ogImage)) {
        if (!empty($siteSettings->default_og_image)) {
            $ogImage = \Illuminate\Support\Facades\Storage::disk('public')->url($siteSettings->default_og_image);
        } elseif (!empty($siteSettings->logo)) {
            $ogImage = \Illuminate\Support\Facades\Storage::disk('public')->url($siteSettings->logo);
        } else {
            $ogImage = null;
        }
    }

    $isIndexingAllowed = $siteSettings->search_engine_indexing ?? true;
    $robotsDirective = $isIndexingAllowed ? 'index, follow' : 'noindex, nofollow';
@endphp
<!DOCTYPE html>
<html lang="tr" data-theme="{{ $activeThemeMode }}" class="{{ $activeThemeMode }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $metaDescription }}">
    <meta name="robots" content="{{ $robotsDirective }}">
    <link rel="canonical" href="{{ url()->current() }}">

    <style id="dost-theme-tokens">
        {!! $siteSettings->renderThemeCss($previewThemeSettings) !!}
    </style>

    {{-- OpenGraph Meta Tags --}}
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    @if(!empty($ogImage))
        <meta property="og:image" content="{{ $ogImage }}">
    @endif

    {{-- Favicon --}}
    @if(!empty($siteSettings->favicon))
        <link rel="icon" href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($siteSettings->favicon) }}">
    @endif

    {{-- Google Site Verification --}}
    @if(!empty($siteSettings->google_site_verification))
        @if(str_starts_with(trim($siteSettings->google_site_verification), '<meta'))
            {!! $siteSettings->google_site_verification !!}
        @else
            <meta name="google-site-verification" content="{{ $siteSettings->google_site_verification }}">
        @endif
    @endif

    {{-- Google Tag Manager --}}
    @if(!empty($siteSettings->google_tag_manager_id))
        <!-- Google Tag Manager -->
        <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
        new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
        j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
        'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
        })(window,document,'script','dataLayer','{{ $siteSettings->google_tag_manager_id }}');</script>
        <!-- End Google Tag Manager -->
    @endif

    {{-- Google Analytics (gtag.js) --}}
    @if(!empty($siteSettings->google_analytics_id))
        <!-- Google Analytics (gtag.js) -->
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ $siteSettings->google_analytics_id }}"></script>
        <script>
          window.dataLayer = window.dataLayer || [];
          function gtag(){dataLayer.push(arguments);}
          gtag('js', new Date());
          gtag('config', '{{ $siteSettings->google_analytics_id }}');
        </script>
        <!-- End Google Analytics -->
    @endif

    {{-- Custom Head Code --}}
    @if(!empty($siteSettings->custom_head_code))
        {!! $siteSettings->custom_head_code !!}
    @endif

    @stack('head')

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen theme-bg-main theme-text-main antialiased">
    @if($isPreviewActive)
        <x-site.theme-preview-bar :active-mode="$activeThemeMode" />
    @endif
    {{-- Google Tag Manager (noscript) --}}
    @if(!empty($siteSettings->google_tag_manager_id))
        <!-- Google Tag Manager (noscript) -->
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ $siteSettings->google_tag_manager_id }}"
        height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
        <!-- End Google Tag Manager (noscript) -->
    @endif

    <div class="pointer-events-none fixed inset-0 -z-10 theme-bg-main overflow-hidden">
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_80%_60%_at_50%_-10%,var(--color-accent),transparent)] opacity-10"></div>
    </div>

    <x-site.header />

    <main class="w-full max-w-full overflow-x-hidden">
        @yield('content')
    </main>

    <x-site.footer />

    <x-site.announcement-popup />

    {{-- Custom Body Code --}}
    @if(!empty($siteSettings->custom_body_code))
        {!! $siteSettings->custom_body_code !!}
    @endif
</body>
</html>

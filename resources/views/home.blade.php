@extends('layouts.app')

@section('title', 'Dost TV - Ana Sayfa')

@push('head')
    @php
        // Preload exactly what the hero <picture> will pick (resized WebP
        // variants), so the browser doesn't fetch the multi-MB original too.
        $firstHeroProgram = collect($heroPrograms)->first();
        $heroDesktopImage = $firstHeroProgram?->horizontal_image;
        $heroMobileImage = $firstHeroProgram?->mobile_hero_image;
    @endphp
    @if ($heroMobileImage)
        <link rel="preload" as="image" media="(max-width: 767px)" href="{{ \App\Support\ResponsiveImage::url($heroMobileImage, 960) }}" @if ($srcset = \App\Support\ResponsiveImage::srcset($heroMobileImage, [480, 640, 960, 1280])) imagesrcset="{{ $srcset }}" imagesizes="100vw" @endif fetchpriority="high">
    @endif
    @if ($heroDesktopImage)
        <link rel="preload" as="image" @if ($heroMobileImage) media="(min-width: 768px)" @endif href="{{ \App\Support\ResponsiveImage::url($heroDesktopImage, 1920) }}" @if ($srcset = \App\Support\ResponsiveImage::srcset($heroDesktopImage, [640, 960, 1280, 1920])) imagesrcset="{{ $srcset }}" imagesizes="100vw" @endif fetchpriority="high">
    @elseif ($heroMobileImage)
        <link rel="preload" as="image" media="(min-width: 768px)" href="{{ \App\Support\ResponsiveImage::url($heroMobileImage, 960) }}" @if ($srcset = \App\Support\ResponsiveImage::srcset($heroMobileImage, [480, 640, 960, 1280])) imagesrcset="{{ $srcset }}" imagesizes="100vw" @endif fetchpriority="high">
    @endif
@endpush

@section('content')
    {{-- Fixed Hero Section (Always Top) --}}
    <x-site.home.hero-section :today-schedule="$todaySchedule" :hero-programs="$heroPrograms" :block="$fixedSettings['hero'] ?? []" />

    {{-- Dynamic Builder Blocks (Between Hero and Footer) --}}
    <x-site.homepage-sections
        :sections="$homepageSections"
        :banners="$banners"
        :settings="$settings"
        :today-schedule="$todaySchedule"
        :featured-programs="$featuredPrograms"
        :hero-programs="$heroPrograms"
        :resolved-block-data="$resolvedBlockData ?? []"
    />
@endsection

@extends('layouts.app')

@section('title', 'Dost TV - Ana Sayfa')

@push('head')
    @php
        $firstHeroProgram = collect($heroPrograms)->first();
        $heroPreloadUrl = null;
        if ($firstHeroProgram) {
            $rawPreload = $firstHeroProgram->mobile_hero_image ?: $firstHeroProgram->horizontal_image;
            if ($rawPreload) {
                $heroPreloadUrl = str_starts_with($rawPreload, 'http')
                    ? $rawPreload
                    : \Illuminate\Support\Facades\Storage::disk('public')->url($rawPreload);
            }
        }
    @endphp
    @if ($heroPreloadUrl)
        <link rel="preload" as="image" href="{{ $heroPreloadUrl }}" fetchpriority="high">
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

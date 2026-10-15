@extends('layouts.app')

@section('title', 'Haftalık Yayın Akışı - Dost TV')
@section('description', 'Dost TV güncel televizyon yayın akış planı. Canlı ve tekrar yayınlanan tüm programların yayın saatleri.')

@section('content')
    <section class="mx-auto max-w-5xl px-4 py-12 sm:px-6 lg:px-8"
        x-data="{
            selectedDay: {{ $defaultSelectedDay }},
            todayDay: {{ $todayIndex }},
            scrollCarousel(direction) {
                const container = this.$refs.carousel;
                if (container) {
                    const scrollAmount = container.clientWidth * 0.75;
                    container.scrollBy({ left: direction * scrollAmount, behavior: 'smooth' });
                }
            },
            init() {
                this.$nextTick(() => {
                    // Center active day card in carousel on load
                    const activeCard = document.getElementById('day-card-' + this.selectedDay);
                    if (activeCard && this.$refs.carousel) {
                        activeCard.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                    }

                    // Auto-scroll to 'now-playing' item on page load if today is viewed and no explicit hash
                    if (this.selectedDay === this.todayDay && !window.location.hash) {
                        const nowEl = document.getElementById('now-playing') || document.querySelector('[data-now-playing=\'true\']');
                        if (nowEl) {
                            const isReducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                            setTimeout(() => {
                                nowEl.scrollIntoView({
                                    behavior: isReducedMotion ? 'auto' : 'smooth',
                                    block: 'center'
                                });
                            }, 200);
                        }
                    }
                });
            }
        }">

        <!-- Page Header & Secondary Active Period Badge -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-white/10 pb-6">
            <div>
                <h1 class="text-3xl sm:text-4xl font-black tracking-tight text-white">Haftalık Yayın Akışı</h1>
                <p class="mt-2 text-sm sm:text-base text-slate-400">Dost TV güncel televizyon yayın akış planı.</p>
            </div>
            @if ($activeTemplate)
                <div class="self-start md:self-auto inline-flex items-center gap-2 rounded-xl border border-white/10 bg-white/[0.04] px-3.5 py-1.5 text-xs text-slate-300 backdrop-blur-md">
                    <span class="relative flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-rose-500"></span>
                    </span>
                    <span class="text-slate-400">Aktif Dönem:</span>
                    <strong class="text-white font-semibold">{{ $activeTemplate->name }}</strong>
                </div>
            @endif
        </div>

        <!-- Day Selector Carousel (4 Visible on Desktop) -->
        <div class="mt-8 relative group">
            <!-- Left Arrow Button -->
            <button type="button"
                @click="scrollCarousel(-1)"
                aria-label="Önceki Günler"
                class="absolute -left-3 top-1/2 -translate-y-1/2 z-20 flex h-9 w-9 items-center justify-center rounded-full border border-white/15 bg-slate-900/90 text-white shadow-xl backdrop-blur-md transition-all hover:scale-110 hover:border-rose-500/50 hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-rose-500 hidden sm:flex">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                </svg>
            </button>

            <!-- Right Arrow Button -->
            <button type="button"
                @click="scrollCarousel(1)"
                aria-label="Sonraki Günler"
                class="absolute -right-3 top-1/2 -translate-y-1/2 z-20 flex h-9 w-9 items-center justify-center rounded-full border border-white/15 bg-slate-900/90 text-white shadow-xl backdrop-blur-md transition-all hover:scale-110 hover:border-rose-500/50 hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-rose-500 hidden sm:flex">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                </svg>
            </button>

            <!-- Horizontal Scrollable Container -->
            <div x-ref="carousel"
                class="flex gap-3 overflow-x-auto scroll-smooth py-2 px-1 snap-x snap-mandatory [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden">
                @foreach ($daysData as $day)
                    <div id="day-card-{{ $day['index'] }}"
                        @click="selectedDay = {{ $day['index'] }}"
                        class="snap-start flex-shrink-0 cursor-pointer select-none rounded-2xl p-4 text-center transition-all duration-200 border
                               w-[calc(50%-6px)] sm:w-[calc(33.333%-8px)] lg:w-[calc(25%-9px)]"
                        :class="selectedDay === {{ $day['index'] }}
                            ? 'border-rose-500/70 bg-gradient-to-b from-rose-500/20 to-rose-500/5 shadow-[0_0_20px_rgba(244,63,94,0.25)] ring-1 ring-rose-500/50 scale-[1.02]'
                            : 'border-white/10 bg-white/[0.03] hover:border-white/20 hover:bg-white/[0.06] opacity-85 hover:opacity-100'">

                        <!-- Day Header -->
                        <div class="flex items-center justify-center gap-1.5 text-xs sm:text-sm font-semibold"
                            :class="selectedDay === {{ $day['index'] }} ? 'text-rose-400 font-bold' : 'text-slate-400'">
                            @if ($day['is_today'])
                                <span class="flex h-2 w-2 relative">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-rose-500"></span>
                                </span>
                            @endif
                            <span>{{ $day['day_name'] }}</span>
                        </div>

                        <!-- Date Label -->
                        <div class="mt-1 text-base sm:text-lg font-bold text-white tracking-tight">
                            {{ $day['date_label'] }}
                        </div>

                        <!-- Today Badge -->
                        @if ($day['is_today'])
                            <div class="mt-1.5 inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider"
                                :class="selectedDay === {{ $day['index'] }} ? 'bg-rose-500 text-white' : 'bg-white/10 text-rose-300'">
                                Bugün
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

                    <!-- Broadcast Stream List for Selected Day -->
        <div class="mt-8">
            @foreach ($daysData as $day)
                <div x-show="selectedDay === {{ $day['index'] }}"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 translate-y-2"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    style="display: none;"
                    class="rounded-2xl border border-white/10 bg-white/[0.02] p-3 sm:p-6 backdrop-blur-xl shadow-2xl">

                    <!-- Day Title Bar -->
                    <div class="flex items-center justify-between border-b border-white/10 pb-2.5 mb-2.5 sm:pb-4 sm:mb-4">
                        <div class="flex items-center gap-2">
                            <span class="inline-block w-2 h-2 sm:w-2.5 sm:h-2.5 rounded-full {{ $day['is_today'] ? 'bg-rose-500 animate-pulse' : 'bg-slate-400' }}"></span>
                            <h2 class="text-base sm:text-xl font-bold text-white flex items-center gap-1.5">
                                {{ $day['day_name'] }}
                                <span class="text-xs sm:text-sm font-normal text-slate-400">· {{ $day['date_label'] }}</span>
                            </h2>
                        </div>
                        <span class="text-xs sm:text-sm font-medium text-slate-400">
                            {{ $day['broadcasts']->count() }} Yayın
                        </span>
                    </div>

                    @if ($day['broadcasts']->isEmpty())
                        <div class="py-12 text-center">
                            <p class="text-base font-semibold text-slate-300">Bu gün için planlanmış yayın bulunmamaktadır.</p>
                            <p class="mt-1 text-sm text-slate-500">Lütfen diğer günlerin akışını kontrol ediniz.</p>
                        </div>
                    @else
                        <div class="divide-y divide-white/5 space-y-1">
                            @foreach ($day['broadcasts'] as $bIndex => $item)
                                @php
                                    $isNowPlaying = $day['is_today'] && ($day['now_playing_index'] === $bIndex);
                                    $isNextUpcoming = $day['is_today'] && ($day['now_playing_index'] === null) && ($day['next_upcoming_index'] === $bIndex);
                                    $startTimeFormatted = \Illuminate\Support\Carbon::parse($item->start_time)->format('H:i');
                                    $endTimeFormatted = $item->end_time ? \Illuminate\Support\Carbon::parse($item->end_time)->format('H:i') : null;

                                    $targetUrl = null;
                                    if ($isNowPlaying && $item->is_live) {
                                        $targetUrl = route('live.tv');
                                    } elseif ($item->program) {
                                        $targetUrl = route('programs.show', $item->program);
                                    }

                                    $imgSrc = null;
                                    if ($item->program?->default_episode_image) {
                                        $rawImg = $item->program->default_episode_image;
                                        if (str_starts_with($rawImg, 'http://') || str_starts_with($rawImg, 'https://')) {
                                            $imgSrc = $rawImg;
                                        } else {
                                            $imgSrc = \Illuminate\Support\Facades\Storage::disk('public')->url($rawImg);
                                        }
                                    }
                                @endphp

                                <div @if($isNowPlaying) id="now-playing" data-now-playing="true" @elseif($isNextUpcoming) data-next-upcoming="true" @endif
                                    class="group flex flex-row items-center gap-2.5 sm:gap-5 py-2.5 sm:py-3.5 px-2 sm:px-4 rounded-xl transition-all duration-200 scroll-mt-28
                                           {{ $isNowPlaying
                                                ? 'bg-rose-500/[0.12] border border-rose-500/40 shadow-[0_0_25px_rgba(244,63,94,0.18)] ring-1 ring-rose-500/30 my-1.5'
                                                : 'hover:bg-white/[0.04] border border-transparent' }}">

                                    <!-- Time Column (Left): Compact 72px on mobile, expanded on desktop -->
                                    <div class="w-[72px] sm:w-36 md:w-40 lg:w-44 flex-shrink-0 sm:border-r sm:border-white/10 sm:pr-5 flex flex-col justify-center">
                                        <div class="flex flex-col items-start justify-center gap-0.5">
                                            <span class="text-lg sm:text-3xl lg:text-[38px] font-bold tracking-tight leading-none {{ $isNowPlaying ? 'text-rose-400' : 'text-white' }}">
                                                {{ $startTimeFormatted }}
                                            </span>
                                            @if ($endTimeFormatted)
                                                <span class="text-[10px] sm:text-sm font-normal text-slate-400/60 tracking-normal leading-none sm:leading-normal">
                                                    {{ $endTimeFormatted }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>

                                    <!-- 16:9 Thumbnail Column (Middle): 96px on mobile, 176/192px on desktop -->
                                    <div class="w-24 sm:w-44 md:w-48 flex-shrink-0">
                                        @if ($targetUrl)
                                            <a href="{{ $targetUrl }}" class="group/thumb block relative aspect-video w-full overflow-hidden rounded-lg sm:rounded-xl bg-slate-900 ring-1 ring-white/10 group-hover:ring-rose-500/50 transition">
                                        @else
                                            <div class="relative aspect-video w-full overflow-hidden rounded-lg sm:rounded-xl bg-slate-900 ring-1 ring-white/10">
                                        @endif

                                            @if ($imgSrc)
                                                <img src="{{ $imgSrc }}" alt="{{ $item->display_title ?? $item->program?->name }}" loading="lazy" class="h-full w-full object-cover transition duration-300 group-hover/thumb:scale-105">
                                            @else
                                                <div class="flex h-full w-full items-center justify-center bg-slate-900/90 text-slate-700">
                                                    <svg class="h-5 w-5 sm:h-7 sm:w-7 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                </div>
                                            @endif

                                        @if ($targetUrl)
                                            </a>
                                        @else
                                            </div>
                                        @endif
                                    </div>

                                    <!-- Program Info Column (Right): flex-1 min-w-0 -->
                                    <div class="flex-1 min-w-0 flex flex-col justify-center gap-0.5">
                                        @if ($isNowPlaying || $item->is_live || $item->is_repeat || !empty($item->is_package))
                                            <div class="min-w-0 flex items-center flex-wrap gap-1 mb-0.5">
                                                @if ($isNowPlaying)
                                                    <span class="flex-shrink-0 inline-flex items-center gap-1 rounded-full bg-rose-500 px-2 py-0.5 text-[9px] sm:text-xs font-black uppercase tracking-wider text-white shadow">
                                                        <span class="h-1 w-1 rounded-full bg-white animate-pulse"></span>
                                                        ŞİMDİ
                                                    </span>
                                                @endif

                                                @if ($item->is_live)
                                                    <span class="flex-shrink-0 rounded bg-red-600 px-1.5 py-0.5 text-[9px] sm:text-[10px] font-bold uppercase tracking-wider text-white">
                                                        CANLI
                                                    </span>
                                                @endif

                                                @if ($item->is_repeat)
                                                    <span class="flex-shrink-0 rounded bg-amber-500/20 text-amber-300 border border-amber-500/30 px-1.5 py-0.5 text-[9px] sm:text-[10px] font-medium">
                                                        TEKRAR
                                                    </span>
                                                @endif

                                                @if (!empty($item->is_package))
                                                    <span class="flex-shrink-0 rounded bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 px-1.5 py-0.5 text-[9px] sm:text-[10px] font-medium">
                                                        PAKET
                                                    </span>
                                                @endif
                                            </div>
                                        @endif

                                        @php
                                            $showProgramNames = (bool) ($activeTemplate->show_program_names ?? true);
                                        @endphp

                                        @if ($showProgramNames)
                                            @if ($item->program)
                                                <a href="{{ route('programs.show', $item->program) }}"
                                                    class="text-sm sm:text-base lg:text-lg font-bold tracking-tight text-white hover:text-rose-400 transition-colors line-clamp-2 {{ $isNowPlaying ? 'text-rose-100' : '' }}">
                                                    {{ $item->display_title ?? $item->program->name }}
                                                </a>
                                            @else
                                                <span class="text-sm sm:text-base lg:text-lg font-bold tracking-tight text-white line-clamp-2 {{ $isNowPlaying ? 'text-rose-100' : '' }}">
                                                    {{ $item->display_title ?? 'Özel Yayın' }}
                                                </span>
                                            @endif
                                        @endif

                                        @if (!empty($item->program?->short_description))
                                            <p class="text-xs text-slate-400 leading-relaxed line-clamp-2 {{ $isNowPlaying ? 'text-rose-200/80' : '' }}">
                                                {{ $item->program->short_description }}
                                            </p>
                                        @endif

                                        @if (!empty($item->note))
                                            <span class="text-[10px] sm:text-xs text-slate-400 italic">
                                                {{ $item->note }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </section>
@endsection


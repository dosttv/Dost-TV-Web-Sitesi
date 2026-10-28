@props([
    'todaySchedule' => collect(),
    'heroPrograms' => collect(),
    'block' => [],
])

@php
    $now = now();
    $heroHeightMode = $block['section_height_mode'] ?? 'auto';
    $heroHeight = (int) ($block['section_height'] ?? 480);
    $heroCustomStyle = match($heroHeightMode) {
        'short' => 'height: 380px !important; min-height: 380px !important; max-height: 380px !important;',
        'medium' => 'height: 480px !important; min-height: 480px !important; max-height: 480px !important;',
        'tall' => 'height: 600px !important; min-height: 600px !important; max-height: 600px !important;',
        'custom' => "height: {$heroHeight}px !important; min-height: {$heroHeight}px !important; max-height: {$heroHeight}px !important;",
        default => '',
    };

    $scheduleByProgramId = collect($todaySchedule)
        ->filter(fn ($item) => $item->program)
        ->keyBy(fn ($item) => $item->program->id);

    $slides = collect($heroPrograms)->map(function ($program) use ($scheduleByProgramId, $now) {
        $scheduleItem = $scheduleByProgramId->get($program->id);
        $start = null;
        $end = null;
        $isLive = false;

        if ($scheduleItem) {
            $start = \Illuminate\Support\Carbon::parse($scheduleItem->start_time);
            $end = $scheduleItem->end_time ? \Illuminate\Support\Carbon::parse($scheduleItem->end_time) : null;
            $inWindow = $end ? $now->between($start, $end) : $start->lte($now);
            $isLive = $inWindow && (bool) ($scheduleItem->is_live ?? false);
        }

        return (object) [
            'program' => $program,
            'start' => $start,
            'end' => $end,
            'isLive' => $isLive,
        ];
    })->values();

    $showTitle = (bool) ($block['show_title'] ?? true);
    $showSchedule = (bool) ($block['show_schedule'] ?? true);
    $showDescription = (bool) ($block['show_description'] ?? true);
    $overlayMode = (string) ($block['overlay_mode'] ?? 'none');

    $dayLabel = \App\Models\Schedule::DAYS[$now->dayOfWeekIso - 1] ?? null;
@endphp

@if ($slides->isNotEmpty())
    <section class="relative w-full overflow-hidden bg-slate-950"
             x-data="{ active: 0, count: {{ $slides->count() }}, timer: null }"
             x-init="
                 const start = () => { if (!timer && count > 1) timer = setInterval(() => active = (active + 1) % count, 7000); };
                 const stop = () => { if (timer) { clearInterval(timer); timer = null; } };
                 start();
                 document.addEventListener('visibilitychange', () => {
                     if (document.hidden) stop();
                     else start();
                 });
                 window.addEventListener('message', (e) => {
                     if (e.data && e.data.type === 'select-hero-slide' && e.data.programId) {
                         const pId = parseInt(e.data.programId, 10);
                         const slideProgramIds = @js($slides->map(fn($s) => $s->program->id)->values());
                         const idx = slideProgramIds.indexOf(pId);
                         if (idx !== -1) {
                             active = idx;
                         }
                     }
                 });
             ">

        {{-- Tek Dış Hero Viewport Container --}}
        <div class="relative w-full overflow-hidden {{ $heroHeightMode === 'auto' ? 'min-h-[380px] sm:min-h-[440px] lg:max-h-[580px] xl:max-h-[680px] 2xl:max-h-[720px]' : '' }}" style="{{ $heroCustomStyle ?: 'aspect-ratio: 1920 / 720;' }}">
            @foreach ($slides as $index => $slide)
                @php
                    $program = $slide->program;
                    // Resized WebP variants; originals are multi-MB PNG uploads.
                    $rawImage = $program->horizontal_image;
                    $imageUrl = $rawImage ? \App\Support\ResponsiveImage::url($rawImage, 1920) : null;
                    $imageSrcset = \App\Support\ResponsiveImage::srcset($rawImage, [640, 960, 1280, 1920]);

                    $rawMobileImage = $program->mobile_hero_image;
                    $mobileImageUrl = $rawMobileImage ? \App\Support\ResponsiveImage::url($rawMobileImage, 960) : null;
                    $mobileImageSrcset = \App\Support\ResponsiveImage::srcset($rawMobileImage, [480, 640, 960, 1280]);
                    $description = $program->hero_text;
                @endphp

                <div x-show="active === {{ $index }}"
                     x-transition:enter="transition ease-out duration-700"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     @if ($index > 0) style="display: none;" @endif
                     class="absolute inset-0 h-full w-full">

                    {{-- Arka plan görseli (Responsive Picture / Mobile Fallback) --}}
                    @if ($imageUrl || $mobileImageUrl)
                        <picture class="absolute inset-0 h-full w-full">
                            @if ($mobileImageUrl)
                                <source media="(max-width: 767px)" srcset="{{ $mobileImageSrcset ?: $mobileImageUrl }}" sizes="100vw">
                            @endif
                            <img
                                src="{{ $imageUrl ?: $mobileImageUrl }}"
                                @if ($imageUrl ? $imageSrcset : $mobileImageSrcset)
                                    srcset="{{ $imageUrl ? $imageSrcset : $mobileImageSrcset }}"
                                    sizes="100vw"
                                @endif
                                alt="{{ $program->name }}"
                                width="1920"
                                height="720"
                                decoding="async"
                                class="absolute inset-0 h-full w-full object-cover transform scale-100 transition-transform duration-1000"
                                @if ($index === 0)
                                    fetchpriority="high"
                                @else
                                    loading="lazy"
                                @endif
                            >
                        </picture>
                    @else
                        <div class="absolute inset-0 flex items-center justify-center bg-slate-900 text-5xl font-black text-slate-700">
                            {{ mb_substr($program->name ?: 'D', 0, 1) }}
                        </div>
                    @endif

                    {{-- Overlay katmanları: overlay_mode seçimine göre render et --}}
                    @if ($overlayMode === 'soft')
                        <div class="absolute inset-0 bg-gradient-to-r from-slate-950/45 via-slate-950/20 to-transparent"></div>
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-950/30 via-transparent to-transparent"></div>
                    @elseif ($overlayMode === 'strong')
                        <div class="absolute inset-0 bg-gradient-to-r from-slate-950 via-slate-950/70 to-slate-950/20"></div>
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/40 to-transparent"></div>
                        <div class="absolute inset-0 bg-[radial-gradient(ellipse_70%_50%_at_20%_50%,rgba(2,6,23,0.8),transparent)]"></div>
                    @endif

                    {{-- Metin İçeriği (Grid Hizalamasına Sadık Centered Outer Wrapper) --}}
                    <div class="absolute inset-0 z-20 flex items-center">
                        <div class="mx-auto w-full max-w-7xl px-11 sm:px-16 lg:px-20">
                            <div class="flex max-w-xl flex-col gap-2 sm:gap-3.5 lg:gap-4.5">
                                @if ($showTitle)
                                    <h1 class="text-xl sm:text-3xl lg:text-5xl font-black leading-tight text-white tracking-tight drop-shadow-md break-words">
                                        {{ $program->name }}
                                    </h1>
                                @endif

                                @if ($showDescription && $description)
                                    <p class="line-clamp-2 sm:line-clamp-3 text-xs sm:text-sm lg:text-base text-slate-300 font-normal leading-relaxed">
                                        {{ $description }}
                                    </p>
                                @endif

                                @if ($showSchedule && $slide->start)
                                    <div class="flex flex-wrap items-center gap-1.5 sm:gap-2 text-xs sm:text-sm font-semibold text-rose-300">
                                        @if ($slide->isLive)
                                            <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-500/20 px-2 py-0.5 sm:px-3 sm:py-1 ring-1 ring-inset ring-rose-500/40 backdrop-blur-sm text-rose-200 shrink-0">
                                                <span class="h-1.5 w-1.5 rounded-full bg-rose-400 animate-pulse"></span>
                                                Şu An Yayında
                                            </span>
                                        @endif
                                        <span class="break-words">
                                            Her {{ $dayLabel }} &middot; {{ $slide->start->format('H:i') }}
                                        </span>
                                    </div>
                                @endif

                                <div class="flex flex-wrap items-center gap-2 sm:gap-3.5 pt-1 sm:pt-2">
                                    <a href="{{ route('programs.show', $program) }}"
                                       class="inline-flex items-center gap-1.5 sm:gap-2 rounded-full bg-gradient-to-r from-rose-600 to-orange-600 px-4 py-2 sm:px-6 sm:py-3 text-xs sm:text-sm font-bold text-white shadow-lg shadow-rose-600/30 transition-all hover:scale-105 hover:from-rose-500 hover:to-orange-500 focus:outline-none shrink-0">
                                        <span>Bölümleri İzle</span>
                                        <svg class="h-3.5 w-3.5 sm:h-4 sm:w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                        </svg>
                                    </a>

                                    @if ($slide->isLive)
                                        <a href="{{ route('live.tv') }}"
                                           class="inline-flex items-center gap-1.5 sm:gap-2 rounded-full bg-white/10 px-4 py-2 sm:px-6 sm:py-3 text-xs sm:text-sm font-semibold text-white ring-1 ring-inset ring-white/25 transition-all hover:bg-white/20 hover:ring-white/40 backdrop-blur-md shrink-0">
                                            <span class="h-2 w-2 rounded-full bg-rose-500 animate-ping"></span>
                                            Canlı İzle
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @if ($slides->count() > 1)
            {{-- Sol Ok --}}
            <button type="button" @click="active = (active - 1 + count) % count"
                    aria-label="Önceki program"
                    class="absolute left-2 sm:left-4 lg:left-6 top-1/2 z-30 flex h-8 w-8 sm:h-11 sm:w-11 -translate-y-1/2 items-center justify-center rounded-full bg-slate-950/70 text-white border border-white/15 backdrop-blur-md transition-all hover:bg-rose-600 hover:border-rose-500 hover:scale-110 focus:outline-none">
                <svg class="h-4 w-4 sm:h-6 sm:w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
            </button>

            {{-- Sağ Ok --}}
            <button type="button" @click="active = (active + 1) % count"
                    aria-label="Sonraki program"
                    class="absolute right-2 sm:right-4 lg:right-6 top-1/2 z-30 flex h-8 w-8 sm:h-11 sm:w-11 -translate-y-1/2 items-center justify-center rounded-full bg-slate-950/70 text-white border border-white/15 backdrop-blur-md transition-all hover:bg-rose-600 hover:border-rose-500 hover:scale-110 focus:outline-none">
                <svg class="h-4 w-4 sm:h-6 sm:w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                </svg>
            </button>

            {{-- Slider Noktaları --}}
            <div class="absolute bottom-5 left-1/2 z-30 flex -translate-x-1/2 gap-2">
                @foreach ($slides as $index => $slide)
                    <button type="button" @click="active = {{ $index }}"
                            :class="active === {{ $index }} ? 'bg-gradient-to-r from-rose-500 to-orange-500 w-6' : 'bg-white/30 hover:bg-white/60 w-2'"
                            class="h-2 rounded-full transition-all duration-300"
                            aria-label="Slayt {{ $index + 1 }}"></button>
                @endforeach
            </div>
        @endif
    </section>
@endif

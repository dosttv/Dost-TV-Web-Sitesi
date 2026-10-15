<?php

namespace App\Services\Home;

use App\Models\Banner;
use App\Models\Category;
use App\Models\Episode;
use App\Models\HomepageLayout;
use App\Models\InstagramCategory;
use App\Models\InstagramReelsSchedule;
use App\Models\InstagramVideo;
use App\Models\Program;
use App\Models\ProgramCollection;
use App\Models\ScheduleTemplateItem;
use App\Models\SiteSetting;
use App\Models\VideoCollection;
use App\Models\YoutubeChannel;
use App\Services\Schedule\BroadcastScheduleResolver;
use App\Support\SiteCache;
use Illuminate\Support\Collection;

class HomepageDataService
{
    public function __construct(
        protected BroadcastScheduleResolver $scheduleResolver
    ) {}

    /**
     * Resolves data required for homepage rendering.
     * If no $sections array is provided, fetches the active HomepageLayout published_sections,
     * or falls back to legacy SiteSetting sections.
     */
    public function getHomepageData(?array $sections = null, ?SiteSetting $settings = null): array
    {
        $settings = $settings ?? SiteSetting::current();

        if ($sections === null) {
            $activeLayout = HomepageLayout::query()->active()->first();
            if ($activeLayout && ! empty($activeLayout->published_sections)) {
                $sections = $activeLayout->published_sections;
            } else {
                $sections = $settings->normalized_homepage_sections;
            }
        }

        $now = now();

        $banners = SiteCache::rememberHomeBanners(function () use ($now) {
            return Banner::query()
                ->where('is_active', true)
                ->where(function ($query) {
                    $query->whereNull('content_type')
                        ->orWhere('content_type', 'hero');
                })
                ->where(function ($query) use ($now) {
                    $query->whereNull('starts_at')
                        ->orWhere('starts_at', '<=', $now);
                })
                ->where(function ($query) use ($now) {
                    $query->whereNull('ends_at')
                        ->orWhere('ends_at', '>=', $now);
                })
                ->orderBy('sort_order')
                ->get();
        });

        $featuredPrograms = SiteCache::rememberHomeFeaturedPrograms(function () {
            return Program::query()
                ->with('categories')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->limit(8)
                ->get();
        });

        $heroPrograms = $this->resolveHeroPrograms();

        $todaySchedule = $this->scheduleResolver->getScheduleForDate(now());

        // Resolve dynamic block data
        $resolvedBlockData = [];
        foreach ($sections as $block) {
            if (empty($block['visible'])) {
                continue;
            }

            $uuid = $block['uuid'] ?? ($block['key'] ?? 'block_' . uniqid());
            $blockType = $block['block_type'] ?? ($block['key'] ?? '');

            if ($blockType === 'content_shelf') {
                $resolvedBlockData[$uuid] = $this->resolveContentShelfData($block);
            } elseif ($blockType === 'video_collection') {
                $resolvedBlockData[$uuid] = $this->resolveVideoBlockData($block);
            } elseif ($blockType === 'program_showcase') {
                $resolvedBlockData[$uuid] = $this->resolveProgramBlockData($block);
            } elseif ($blockType === 'category_shelf') {
                $resolvedBlockData[$uuid] = $this->resolveCategoryShelfData($block);
            } elseif ($blockType === 'youtube_channel_shelf') {
                $resolvedBlockData[$uuid] = $this->resolveYoutubeChannelShelfData($block);
            } elseif ($blockType === 'instagram_videos') {
                $resolvedBlockData[$uuid] = $this->resolveInstagramVideoBlockData($block);
            }
        }

        $fixedSettings = $sections['_fixed_settings'] ?? [
            'header' => [
                'header_height' => 80,
                'section_padding_top' => 16,
                'section_padding_bottom' => 16,
                'section_padding_x' => 32,
                'logo_size' => 48,
                'nav_spacing' => 24,
            ],
            'hero' => [
                'hero_height' => 480,
                'padding_top' => 32,
                'padding_bottom' => 32,
                'gap_size' => 24,
                'card_radius' => 16,
                'text_offset' => 0,
                'show_title' => true,
                'show_schedule' => true,
                'show_description' => true,
                'overlay_mode' => 'none',
            ],
            'footer' => [
                'section_padding_top' => 48,
                'section_padding_bottom' => 56,
                'section_padding_x' => 32,
                'contact_cards_gap' => 24,
                'contact_cards_to_map_gap' => 28,
                'map_title_gap' => 14,
                'map_height' => 320,
                'map_radius' => 16,
                'map_form_gap' => 56,
                'form_field_gap' => 20,
                'input_height' => 48,
                'input_padding_x' => 16,
                'input_padding_y' => 12,
                'textarea_height' => 140,
                'card_radius' => 16,
                'form_radius' => 16,
                'heading_size' => 24,
                'label_size' => 12,
                'card_border' => 'light',
                'card_surface' => 'soft',
            ],
        ];

        return [
            'settings' => $settings,
            'sections' => $sections,
            'banners' => $banners,
            'featuredPrograms' => $featuredPrograms,
            'heroPrograms' => $heroPrograms,
            'todaySchedule' => $todaySchedule,
            'resolvedBlockData' => $resolvedBlockData,
            'fixedSettings' => $fixedSettings,
        ];
    }

    /**
     * Resolves dynamic unified content shelf data (Program Rafı or Video Rafı).
     * Supports Manual, Category, and Hybrid source modes with automatic deduplication.
     */
    public function resolveContentShelfData(array $block): Collection
    {
        $shelfType = $block['shelf_type'] ?? 'program'; // 'program' or 'video'
        $sourceMode = $block['source_mode'] ?? 'manual'; // 'manual', 'category', 'hybrid'
        $categoryMode = $block['category_mode'] ?? 'live_category'; // 'live_category', 'fixed_list'
        $categoryId = $block['category_id'] ?? null;
        $itemIds = array_values(array_filter((array) ($block['item_ids'] ?? [])));
        $limit = HomepageBlockRegistry::resolveContentLimit($block);

        if ($shelfType === 'program') {
            return $this->resolveProgramShelfData($sourceMode, $categoryMode, $categoryId, $itemIds, $limit);
        }

        return $this->resolveVideoShelfData($sourceMode, $categoryMode, $categoryId, $itemIds, $limit);
    }

    protected function resolveProgramShelfData(string $sourceMode, string $categoryMode, $categoryId, array $itemIds, ?int $limit): Collection
    {
        $baseQuery = Program::query()
            ->with(['categories'])
            ->where('show_on_public', true)
            ->where('is_active', true);

        if ($sourceMode === 'manual') {
            if (empty($itemIds)) {
                return collect();
            }
            $items = (clone $baseQuery)
                ->whereIn('id', $itemIds)
                ->get()
                ->sortBy(fn ($prog) => array_search($prog->id, $itemIds))
                ->values();

            return $limit === null ? $items : $items->take($limit);
        }

        if ($sourceMode === 'category') {
            if ($categoryMode === 'fixed_list' && ! empty($itemIds)) {
                $fixed = (clone $baseQuery)
                    ->whereIn('id', $itemIds)
                    ->get()
                    ->sortBy(fn ($prog) => array_search($prog->id, $itemIds))
                    ->values();

                return $limit === null ? $fixed : $fixed->take($limit);
            }

            if (! $categoryId) {
                return collect();
            }

            $catPrograms = (clone $baseQuery)
                ->whereHas('categories', fn ($q) => $q->where('categories.id', $categoryId))
                ->orderBy('sort_order')
                ->get();

            if (! empty($itemIds)) {
                $manualOrdered = (clone $baseQuery)
                    ->whereIn('id', $itemIds)
                    ->get()
                    ->sortBy(fn ($prog) => array_search($prog->id, $itemIds))
                    ->values();

                $merged = $manualOrdered->concat($catPrograms)->unique('id')->values();
                return $limit === null ? $merged : $merged->take($limit);
            }

            return $limit === null ? $catPrograms : $catPrograms->take($limit);
        }

        // Hybrid mode: Pinned item_ids first + remaining category programs
        $pinned = collect();
        if (! empty($itemIds)) {
            $pinned = (clone $baseQuery)
                ->whereIn('id', $itemIds)
                ->get()
                ->sortBy(fn ($prog) => array_search($prog->id, $itemIds))
                ->values();
        }

        $autoCat = collect();
        if ($categoryId) {
            $autoCat = (clone $baseQuery)
                ->whereHas('categories', fn ($q) => $q->where('categories.id', $categoryId))
                ->whereNotIn('id', $pinned->pluck('id')->all())
                ->orderBy('sort_order')
                ->get();
        }

        $merged = $pinned->concat($autoCat)->unique('id')->values();
        return $limit === null ? $merged : $merged->take($limit);
    }

    protected function resolveVideoShelfData(string $sourceMode, string $categoryMode, $categoryId, array $itemIds, ?int $limit): Collection
    {
        $baseQuery = Episode::query()
            ->with(['program'])
            ->where('show_on_public', true)
            ->where('is_active', true);

        if ($sourceMode === 'manual') {
            if (empty($itemIds)) {
                return collect();
            }
            $items = (clone $baseQuery)
                ->whereIn('id', $itemIds)
                ->get()
                ->sortBy(fn ($ep) => array_search($ep->id, $itemIds))
                ->values();

            return $limit === null ? $items : $items->take($limit);
        }

        if ($sourceMode === 'category') {
            if ($categoryMode === 'fixed_list' && ! empty($itemIds)) {
                $fixed = (clone $baseQuery)
                    ->whereIn('id', $itemIds)
                    ->get()
                    ->sortBy(fn ($ep) => array_search($ep->id, $itemIds))
                    ->values();

                return $limit === null ? $fixed : $fixed->take($limit);
            }

            if (! $categoryId) {
                return collect();
            }

            $catVideos = (clone $baseQuery)
                ->whereHas('program.categories', fn ($q) => $q->where('categories.id', $categoryId))
                ->orderByDesc('aired_at')
                ->orderByDesc('id')
                ->get();

            if (! empty($itemIds)) {
                $manualOrdered = (clone $baseQuery)
                    ->whereIn('id', $itemIds)
                    ->get()
                    ->sortBy(fn ($ep) => array_search($ep->id, $itemIds))
                    ->values();

                $merged = $manualOrdered->concat($catVideos)->unique('id')->values();
                return $limit === null ? $merged : $merged->take($limit);
            }

            return $limit === null ? $catVideos : $catVideos->take($limit);
        }

        // Hybrid mode: Pinned video item_ids first + remaining category videos
        $pinned = collect();
        if (! empty($itemIds)) {
            $pinned = (clone $baseQuery)
                ->whereIn('id', $itemIds)
                ->get()
                ->sortBy(fn ($ep) => array_search($ep->id, $itemIds))
                ->values();
        }

        $autoCat = collect();
        if ($categoryId) {
            $autoCat = (clone $baseQuery)
                ->whereHas('program.categories', fn ($q) => $q->where('categories.id', $categoryId))
                ->whereNotIn('id', $pinned->pluck('id')->all())
                ->orderByDesc('aired_at')
                ->orderByDesc('id')
                ->get();
        }

        $merged = $pinned->concat($autoCat)->unique('id')->values();
        return $limit === null ? $merged : $merged->take($limit);
    }

    /**
     * Resolves episodes for a video collection / showcase block based on source mode & limit.
     */
    public function resolveVideoBlockData(array $block): Collection
    {
        $limit = HomepageBlockRegistry::resolveContentLimit($block);
        $sourceMode = $block['source_mode'] ?? 'video_collections';
        $collectionId = $block['collection_id'] ?? ($block['video_collection_id'] ?? null);
        $featuredVideoIds = array_filter((array) ($block['homepage_featured_video_ids'] ?? ($block['featured_video_ids'] ?? [])));

        // 1. Explicitly selected homepage featured videos (when not in hybrid_videos mode)
        if (! empty($featuredVideoIds) && $sourceMode !== 'hybrid_videos') {
            $episodes = Episode::query()
                ->with(['program.categories'])
                ->whereIn('id', $featuredVideoIds)
                ->where('show_on_public', true)
                ->where('is_active', true)
                ->get()
                ->sortBy(fn ($ep) => array_search($ep->id, $featuredVideoIds))
                ->values();

            return $limit === null ? $episodes : $episodes->take($limit);
        }

        // 2. Video Collection binding with limit for homepage showcase
        if ($collectionId) {
            $collection = VideoCollection::query()->where('is_active', true)->find($collectionId);
            if ($collection) {
                return $collection->resolveEpisodes($limit);
            }
        }

        // FALLBACK for legacy blocks without collection_id
        $query = Episode::query()
            ->with(['program'])
            ->where('show_on_public', true)
            ->where('is_active', true);

        if ($sourceMode === 'hybrid_videos') {
            $pinnedIds = array_filter((array) ($block['pinned_ids'] ?? []));
            $pinnedEpisodes = collect();
            if (! empty($pinnedIds)) {
                $pinnedEpisodes = Episode::query()
                    ->with(['program'])
                    ->whereIn('id', $pinnedIds)
                    ->where('show_on_public', true)
                    ->where('is_active', true)
                    ->get()
                    ->sortBy(fn ($ep) => array_search($ep->id, $pinnedIds))
                    ->values();
            }

            $autoQuery = (clone $query)
                ->whereNotIn('id', $pinnedEpisodes->pluck('id')->all())
                ->orderByDesc('aired_at')
                ->orderByDesc('id');

            if ($limit === null) {
                return $pinnedEpisodes->concat($autoQuery->get());
            }

            $remainingCount = $limit - $pinnedEpisodes->count();
            if ($remainingCount <= 0) {
                return $pinnedEpisodes->take($limit);
            }

            return $pinnedEpisodes->concat($autoQuery->take($remainingCount)->get());
        }

        if ($sourceMode === 'program_videos') {
            $programId = $block['program_id'] ?? null;
            if ($programId) {
                $query->where('program_id', $programId);
            }
        } elseif ($sourceMode === 'category_videos') {
            $categoryId = $block['category_id'] ?? null;
            if ($categoryId) {
                $query->whereHas('program.categories', function ($q) use ($categoryId) {
                    $q->where('categories.id', $categoryId);
                });
            }
        } elseif ($sourceMode === 'random_videos') {
            return $query->inRandomOrder()->when($limit !== null, fn ($q) => $q->take($limit))->get();
        }

        return $query->orderByDesc('aired_at')->orderByDesc('id')->when($limit !== null, fn ($q) => $q->take($limit))->get();
    }

    /**
     * Resolves unique public/active programs scheduled in the active broadcast period's 7-day weekly schedule,
     * ordered chronologically by their first appearance in the weekly template.
     */
    public function resolveActivePeriodWeeklyPrograms(): Collection
    {
        $today = now();
        $template = $this->scheduleResolver->getActivePublishedTemplateForDate($today);

        if (! $template) {
            return collect();
        }

        // Fetch all active schedule items across all 7 days of the active template
        // Order by day_of_week ASC (0=Pazartesi .. 6=Pazar), then start_time ASC
        $items = ScheduleTemplateItem::query()
            ->where('schedule_template_id', $template->id)
            ->where('is_active', true)
            ->whereNotNull('program_id')
            ->with(['program.categories'])
            ->orderBy('day_of_week', 'asc')
            ->orderBy('start_time', 'asc')
            ->get();

        if ($items->isEmpty()) {
            return collect();
        }

        $programs = collect();
        $seenProgramIds = [];

        foreach ($items as $item) {
            $program = $item->program;
            if (! $program) {
                continue;
            }

            // Public / active contract
            if (! $program->show_on_public || ! $program->is_active) {
                continue;
            }

            if (! in_array($program->id, $seenProgramIds, true)) {
                $seenProgramIds[] = $program->id;
                $programs->push($program);
            }
        }

        return $programs;
    }

    /**
     * Resolves unique public/active programs scheduled as LIVE (is_live = true)
     * in the active broadcast period's 7-day weekly schedule template.
     */
    public function resolveActivePeriodWeeklyLivePrograms(): Collection
    {
        $today = now();
        $template = $this->scheduleResolver->getActivePublishedTemplateForDate($today);

        if (! $template) {
            return collect();
        }

        $items = ScheduleTemplateItem::query()
            ->where('schedule_template_id', $template->id)
            ->where('is_active', true)
            ->where('is_live', true)
            ->whereNotNull('program_id')
            ->with(['program.categories'])
            ->orderBy('day_of_week', 'asc')
            ->orderBy('start_time', 'asc')
            ->get();

        if ($items->isEmpty()) {
            return collect();
        }

        $programs = collect();
        $seenProgramIds = [];

        foreach ($items as $item) {
            $program = $item->program;
            if (! $program || ! $program->show_on_public || ! $program->is_active) {
                continue;
            }

            if (! in_array($program->id, $seenProgramIds, true)) {
                $seenProgramIds[] = $program->id;
                $programs->push($program);
            }
        }

        return $programs;
    }

    /**
     * Resolves unique public/active Hero programs using a daily hybrid approach:
     * 1. Today's live broadcast scheduled programs (chronological start time order)
     * 2. Today's manual featured programs (hero_days filter or legacy null fallback, sort_order)
     * Deduplicated by program ID preserving live programs first.
     */
    public function resolveHeroPrograms(): Collection
    {
        return SiteCache::rememberHomeHeroPrograms(function () {
            $today = now();
            $todayDayKey = strtolower($today->format('l'));

            // 1. Bugüne ait canlı yayınlanan programlar (yayın saati sırasıyla)
            $todaySchedule = $this->scheduleResolver->getScheduleForDate($today);
            $livePrograms = collect();

            foreach ($todaySchedule as $item) {
                if (empty($item->is_live) || empty($item->is_active)) {
                    continue;
                }

                $program = $item->program ?? null;
                if ($program && $program->show_on_public && $program->is_active) {
                    $livePrograms->push($program);
                }
            }

            $livePrograms = $livePrograms->unique('id')->values();
            $liveProgramIds = $livePrograms->pluck('id')->all();

            // 2. Bugüne özel manuel öne çıkan programlar (Hero Sırası / sort_order sırasıyla)
            $manualPrograms = Program::query()
                ->where('show_on_public', true)
                ->where('is_active', true)
                ->where('is_featured', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->filter(function (Program $program) use ($todayDayKey) {
                    if (empty($program->hero_days)) {
                        return true; // hero_days null/boş ise geriye uyumluluk: her gün manuel hero adayı
                    }

                    return in_array($todayDayKey, (array) $program->hero_days, true);
                })
                ->reject(fn (Program $p) => in_array($p->id, $liveProgramIds, true))
                ->values();

            // 3. Otomatik canlılar önce + Manuel programlar sonra, tekil program ID
            return $livePrograms->concat($manualPrograms)->values();
        });
    }

    /**
     * Resolves programs for a program showcase block based on program_collection_id or fallback source_mode & limit.
     */
    public function resolveProgramBlockData(array $block): Collection
    {
        $limit = HomepageBlockRegistry::resolveContentLimit($block);
        $sourceMode = $block['source_mode'] ?? 'program_collections';

        // 1. Primary path: Resolve via ProgramCollection model if program_collection_id is present or source_mode is program_collections
        $collectionId = $block['program_collection_id'] ?? null;
        if ($collectionId || $sourceMode === 'program_collections') {
            if ($collectionId) {
                $collection = ProgramCollection::query()->where('is_active', true)->find($collectionId);
                if ($collection) {
                    return $collection->resolvePrograms($limit);
                }
            }
            return collect();
        }

        // 2. FALLBACK for legacy blocks without program_collection_id
        $sourceMode = $block['source_mode'] ?? 'active_period_schedule';
        $sortBy = $block['sort_by'] ?? 'custom';

        if ($sourceMode === 'active_period_schedule') {
            $programs = $this->resolveActivePeriodWeeklyPrograms();

            if ($sortBy !== 'custom' && $programs->isNotEmpty()) {
                $query = Program::query()
                    ->with(['categories'])
                    ->whereIn('id', $programs->pluck('id')->all());
                $this->applyProgramSorting($query, $sortBy);
                $programs = $query->get();
            }

            return $limit === null ? $programs : $programs->take($limit);
        }

        $query = Program::query()
            ->with(['categories'])
            ->where('show_on_public', true)
            ->where('is_active', true);

        if ($sourceMode === 'manual_programs') {
            $pinnedIds = array_filter((array) ($block['pinned_ids'] ?? []));
            if (empty($pinnedIds)) {
                return collect();
            }
            $manual = (clone $query)
                ->whereIn('id', $pinnedIds)
                ->get()
                ->sortBy(fn ($prog) => array_search($prog->id, $pinnedIds))
                ->values();

            return $limit === null ? $manual : $manual->take($limit);
        }

        if ($sourceMode === 'hybrid_programs') {
            $pinnedIds = array_filter((array) ($block['pinned_ids'] ?? []));
            $pinnedPrograms = collect();
            if (! empty($pinnedIds)) {
                $pinnedPrograms = (clone $query)
                    ->whereIn('id', $pinnedIds)
                    ->get()
                    ->sortBy(fn ($prog) => array_search($prog->id, $pinnedIds))
                    ->values();
            }

            $autoQuery = (clone $query)->whereNotIn('id', $pinnedPrograms->pluck('id')->all());
            $this->applyProgramSorting($autoQuery, $sortBy);

            if ($limit === null) {
                return $pinnedPrograms->concat($autoQuery->get());
            }

            $remainingCount = $limit - $pinnedPrograms->count();
            if ($remainingCount <= 0) {
                return $pinnedPrograms->take($limit);
            }

            return $pinnedPrograms->concat($autoQuery->take($remainingCount)->get());
        }

        if ($sourceMode === 'category_programs') {
            $categoryId = $block['category_id'] ?? null;
            if ($categoryId) {
                $query->whereHas('categories', function ($q) use ($categoryId) {
                    $q->where('categories.id', $categoryId);
                });
            }
        } elseif ($sourceMode === 'featured_programs') {
            $query->where('is_featured', true);
        }

        $this->applyProgramSorting($query, $sortBy);

        return $query->when($limit !== null, fn ($q) => $q->take($limit))->get();
    }

    /**
     * Resolves a single category's public/active programs for a category_shelf block.
     */
    public function resolveCategoryShelfData(array $block): Collection
    {
        $categoryId = $block['category_id'] ?? null;
        if (! $categoryId) {
            return collect();
        }

        $limit = HomepageBlockRegistry::resolveContentLimit($block);

        return Program::query()
            ->where('show_on_public', true)
            ->where('is_active', true)
            ->whereHas('categories', fn ($q) => $q->where('categories.id', $categoryId))
            ->orderBy('sort_order')
            ->when($limit !== null, fn ($q) => $q->take($limit))
            ->get();
    }

    protected function applyProgramSorting($query, string $sortBy): void
    {
        switch ($sortBy) {
            case 'alphabetical':
                $query->orderBy('name');
                break;
            case 'latest':
                $query->orderByDesc('created_at');
                break;
            case 'random':
                $query->inRandomOrder();
                break;
            default:
                $query->orderBy('sort_order')->orderBy('id');
                break;
        }
    }

    /**
     * Resolves active YouTube channels for youtube_channel_shelf block.
     */
    public function resolveYoutubeChannelShelfData(array $block): Collection
    {
        $channelIds = array_values(array_filter((array) ($block['channel_ids'] ?? [])));
        $limit = HomepageBlockRegistry::resolveContentLimit($block);

        $query = YoutubeChannel::query()->where('is_active', true);

        if (! empty($channelIds)) {
            $channels = (clone $query)
                ->whereIn('id', $channelIds)
                ->get()
                ->sortBy(fn ($ch) => array_search($ch->id, array_map('intval', $channelIds)))
                ->values();

            return $limit === null ? $channels : $channels->take($limit);
        }

        return $query->orderBy('sort_order')->when($limit !== null, fn ($q) => $q->take($limit))->get();
    }

    /**
     * Resolves active Instagram videos for instagram_videos block according to weekly category schedule.
     */
    public function resolveInstagramVideoBlockData(array $block): Collection
    {
        $now = now('Europe/Istanbul');
        $dayOfWeek = $now->dayOfWeekIso; // 1 (Monday) to 7 (Sunday)

        $schedule = InstagramReelsSchedule::current();

        $dayFieldMap = [
            1 => 'monday_category_id',
            2 => 'tuesday_category_id',
            3 => 'wednesday_category_id',
            4 => 'thursday_category_id',
            5 => 'friday_category_id',
            6 => 'saturday_category_id',
            7 => 'sunday_category_id',
        ];

        $todayCategoryId = $schedule->{$dayFieldMap[$dayOfWeek] ?? 'monday_category_id'} ?? null;

        $targetCategory = null;
        if ($todayCategoryId) {
            $targetCategory = InstagramCategory::query()
                ->where('id', $todayCategoryId)
                ->where('is_active', true)
                ->first();
        }

        $query = InstagramVideo::query()->where('instagram_videos.is_active', true);

        // If a valid active category exists for today, filter by category relation
        if ($targetCategory) {
            $query->whereHas('categories', function ($q) use ($targetCategory) {
                $q->where('instagram_categories.id', $targetCategory->id);
            });
        }

        // Determine sort mode: weekly schedule sort_mode or block level override
        $sortMode = $schedule->sort_mode ?? 'latest';
        if (isset($block['sort_by']) && in_array($block['sort_by'], ['manual', 'latest', 'oldest', 'random'], true)) {
            $sortMode = $block['sort_by'];
        }

        if ($sortMode === 'manual') {
            if ($targetCategory) {
                $query->join('instagram_category_video', 'instagram_videos.id', '=', 'instagram_category_video.instagram_video_id')
                    ->where('instagram_category_video.instagram_category_id', $targetCategory->id)
                    ->orderBy('instagram_category_video.sort_order', 'asc')
                    ->orderBy('instagram_videos.id', 'desc')
                    ->select('instagram_videos.*');
            } else {
                $query->orderBy('sort_order', 'asc')->orderBy('id', 'desc');
            }
        } elseif ($sortMode === 'oldest') {
            $query->orderBy('posted_at', 'asc')->orderBy('id', 'asc');
        } elseif ($sortMode === 'latest') {
            $query->orderBy('posted_at', 'desc')->orderBy('id', 'desc');
        }

        $items = $query->get();

        // Filter valid cover image & deduplicate
        $filtered = $items
            ->filter(fn ($video) => filled($video->cover_image_url))
            ->unique(function ($video) {
                if (! empty($video->shortcode)) {
                    return $video->shortcode;
                }
                if (preg_match('#instagram\.com/(reel|p|tv)/([A-Za-z0-9_-]+)#i', (string) $video->permalink, $m)) {
                    return $m[2];
                }
                return $video->permalink;
            })
            ->unique('id')
            ->values();

        // Apply deterministic random shuffle if random mode is selected
        if ($sortMode === 'random' && $filtered->isNotEmpty()) {
            $dateStr = $now->toDateString();
            $catStr = (string) ($targetCategory?->id ?? 'all');
            $seed = (int) hexdec(substr(md5($dateStr . '_reels_seed_' . $catStr), 0, 8));
            $filtered = $this->shuffleDeterministically($filtered, $seed);
        }

        $limit = HomepageBlockRegistry::resolveContentLimit($block);
        if ($limit !== null && $limit > 0) {
            return $filtered->take($limit);
        }

        return $filtered;
    }

    protected function shuffleDeterministically(Collection $collection, int $seed): Collection
    {
        $items = $collection->values()->all();
        mt_srand($seed);
        for ($i = count($items) - 1; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            $tmp = $items[$i];
            $items[$i] = $items[$j];
            $items[$j] = $tmp;
        }
        mt_srand();
        return collect($items);
    }
}

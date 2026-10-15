<?php

namespace Tests\Feature;

use App\Models\Program;
use App\Models\ScheduleTemplate;
use App\Models\ScheduleTemplateItem;
use App\Services\Home\HomepageDataService;
use App\Support\SiteCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DailyHybridHeroAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_hybrid_hero_resolves_live_and_day_specific_manual_programs(): void
    {
        Carbon::setTestNow('2026-09-07 10:00:00'); // 2026-09-07 is a Monday

        // 1. Program A: is_featured=false, scheduled LIVE on Monday 09:00
        $programA = Program::create([
            'name' => 'Program A (Live 09:00)',
            'slug' => 'program-a',
            'is_active' => true,
            'show_on_public' => true,
            'is_featured' => false,
            'hero_days' => null,
            'sort_order' => 10,
        ]);

        // 2. Program B: is_featured=false, scheduled LIVE on Monday 14:00
        $programB = Program::create([
            'name' => 'Program B (Live 14:00)',
            'slug' => 'program-b',
            'is_active' => true,
            'show_on_public' => true,
            'is_featured' => false,
            'hero_days' => null,
            'sort_order' => 20,
        ]);

        // 3. Program C: is_featured=true, scheduled LIVE on Monday 20:00 AND hero_days=['monday']
        $programC = Program::create([
            'name' => 'Program C (Live 20:00 & Manual Monday)',
            'slug' => 'program-c',
            'is_active' => true,
            'show_on_public' => true,
            'is_featured' => true,
            'hero_days' => ['monday'],
            'sort_order' => 0,
        ]);

        // 4. Program D: is_featured=true, hero_days=['monday'] (Not scheduled live today)
        $programD = Program::create([
            'name' => 'Program D (Manual Monday Only)',
            'slug' => 'program-d',
            'is_active' => true,
            'show_on_public' => true,
            'is_featured' => true,
            'hero_days' => ['monday'],
            'sort_order' => 1,
        ]);

        // 5. Program E: is_featured=true, hero_days=['friday'] (Not for Monday)
        $programE = Program::create([
            'name' => 'Program E (Manual Friday Only)',
            'slug' => 'program-e',
            'is_active' => true,
            'show_on_public' => true,
            'is_featured' => true,
            'hero_days' => ['friday'],
            'sort_order' => 2,
        ]);

        // Create schedule template for Monday (day_of_week = 0)
        $template = ScheduleTemplate::create([
            'name' => 'Weekly Template',
            'is_active' => true,
            'status' => 'published',
            'valid_from' => '2026-01-01',
        ]);

        ScheduleTemplateItem::create([
            'schedule_template_id' => $template->id,
            'day_of_week' => 0, // Monday
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'program_id' => $programA->id,
            'is_live' => true,
            'is_active' => true,
        ]);

        ScheduleTemplateItem::create([
            'schedule_template_id' => $template->id,
            'day_of_week' => 0, // Monday
            'start_time' => '14:00:00',
            'end_time' => '15:00:00',
            'program_id' => $programB->id,
            'is_live' => true,
            'is_active' => true,
        ]);

        ScheduleTemplateItem::create([
            'schedule_template_id' => $template->id,
            'day_of_week' => 0, // Monday
            'start_time' => '20:00:00',
            'end_time' => '21:00:00',
            'program_id' => $programC->id,
            'is_live' => true,
            'is_active' => true,
        ]);

        SiteCache::forgetHomeHeroPrograms();

        /** @var HomepageDataService $service */
        $service = app(HomepageDataService::class);
        $heroPrograms = $service->resolveHeroPrograms();

        // Expected Monday Hero: [A, B, C, D]
        // C should NOT be duplicated, E should be excluded because hero_days is Friday only.
        $this->assertEquals(['program-a', 'program-b', 'program-c', 'program-d'], $heroPrograms->pluck('slug')->all());

        Carbon::setTestNow();
    }

    public function test_legacy_null_hero_days_serves_as_fallback_for_all_days(): void
    {
        Carbon::setTestNow('2026-09-08 10:00:00'); // Tuesday

        $program = Program::create([
            'name' => 'Legacy Program',
            'slug' => 'legacy-program',
            'is_active' => true,
            'show_on_public' => true,
            'is_featured' => true,
            'hero_days' => null, // Legacy null hero_days
            'sort_order' => 1,
        ]);

        SiteCache::forgetHomeHeroPrograms();

        /** @var HomepageDataService $service */
        $service = app(HomepageDataService::class);
        $heroPrograms = $service->resolveHeroPrograms();

        $this->assertTrue($heroPrograms->contains('id', $program->id));

        Carbon::setTestNow();
    }

    public function test_hero_programs_cache_is_isolated_by_date(): void
    {
        Carbon::setTestNow('2026-09-07 10:00:00'); // Monday

        Program::create([
            'name' => 'Monday Hero',
            'slug' => 'monday-hero',
            'is_active' => true,
            'show_on_public' => true,
            'is_featured' => true,
            'hero_days' => ['monday'],
        ]);

        /** @var HomepageDataService $service */
        $service = app(HomepageDataService::class);

        $mondayResult = $service->resolveHeroPrograms();
        $this->assertTrue($mondayResult->contains('slug', 'monday-hero'));

        // Advance date to Tuesday
        Carbon::setTestNow('2026-09-08 10:00:00'); // Tuesday

        $tuesdayResult = $service->resolveHeroPrograms();
        $this->assertFalse($tuesdayResult->contains('slug', 'monday-hero'));

        Carbon::setTestNow();
    }

    public function test_hero_image_fallback_priority_and_cover_image_exclusion(): void
    {
        // 1. Program with horizontal_image
        $progHorizontal = Program::create([
            'name' => 'Horizontal Show',
            'slug' => 'horizontal-show',
            'horizontal_image' => 'programs/hero-horiz.jpg',
            'default_episode_image' => 'episodes/def-ep.jpg',
            'cover_image' => 'programs/vertical-cover.jpg',
            'is_active' => true,
            'show_on_public' => true,
            'is_featured' => true,
        ]);

        // 2. Program with default_episode_image only (like Çocuk ve Biz)
        $progDefaultEpisode = Program::create([
            'name' => 'Çocuk ve Biz Test',
            'slug' => 'cocuk-ve-biz-test',
            'horizontal_image' => null,
            'default_episode_image' => 'episodes/cocuk-episode.jpg',
            'cover_image' => 'programs/cocuk-vertical-cover.jpg',
            'is_active' => true,
            'show_on_public' => true,
            'is_featured' => true,
        ]);

        // 3. Program with cover_image only (should render placeholder, NEVER vertical cover)
        $progCoverOnly = Program::create([
            'name' => 'Cover Only Show',
            'slug' => 'cover-only-show',
            'horizontal_image' => null,
            'default_episode_image' => null,
            'cover_image' => 'programs/only-vertical-cover.jpg',
            'is_active' => true,
            'show_on_public' => true,
            'is_featured' => true,
        ]);

        $heroPrograms = collect([$progHorizontal, $progDefaultEpisode, $progCoverOnly]);

        $view = $this->blade('<x-site.home.hero-section :heroPrograms="$heroPrograms" />', ['heroPrograms' => $heroPrograms]);

        // 1. Horizontal image rendered ONLY for program 1
        $view->assertSee('storage/programs/hero-horiz.jpg');

        // 2. default_episode_image is NEVER rendered in Hero for program 2 (Çocuk ve Biz)
        $view->assertDontSee('storage/episodes/cocuk-episode.jpg');
        $view->assertDontSee('storage/episodes/def-ep.jpg');

        // 3. Vertical cover_image is NEVER rendered in Hero for any program
        $view->assertDontSee('storage/programs/vertical-cover.jpg');
        $view->assertDontSee('storage/programs/cocuk-vertical-cover.jpg');
        $view->assertDontSee('storage/programs/only-vertical-cover.jpg');

        // 4. Initials / Placeholder rendered for programs with null horizontal_image
        $view->assertSee('Ç');
        $view->assertSee('C');
    }

    public function test_hero_switches_when_active_period_changes_and_invalidates_cache(): void
    {
        Carbon::setTestNow('2026-10-15 10:00:00'); // Thursday (ISO day 3)

        $programA = Program::create([
            'name' => 'Live Period A Show',
            'slug' => 'live-period-a',
            'is_active' => true,
            'show_on_public' => true,
            'is_featured' => false,
        ]);

        $programB = Program::create([
            'name' => 'Live Period B Show',
            'slug' => 'live-period-b',
            'is_active' => true,
            'show_on_public' => true,
            'is_featured' => false,
        ]);

        // Template A is active
        $templateA = ScheduleTemplate::create([
            'name' => 'Yaz Akışı A',
            'is_active' => true,
            'status' => 'published',
            'valid_from' => '2026-06-01',
            'valid_until' => '2026-12-31',
        ]);

        ScheduleTemplateItem::create([
            'schedule_template_id' => $templateA->id,
            'day_of_week' => 3, // Thursday
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'program_id' => $programA->id,
            'is_live' => true,
            'is_active' => true,
        ]);

        // Template B is published but not active yet
        $templateB = ScheduleTemplate::create([
            'name' => 'Sonbahar Akışı B',
            'is_active' => false,
            'status' => 'published',
            'valid_from' => '2026-09-01',
            'valid_until' => '2026-12-31',
        ]);

        ScheduleTemplateItem::create([
            'schedule_template_id' => $templateB->id,
            'day_of_week' => 3, // Thursday
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'program_id' => $programB->id,
            'is_live' => true,
            'is_active' => true,
        ]);

        SiteCache::forgetHomeHeroPrograms();

        $service = app(HomepageDataService::class);
        $heroInitial = $service->resolveHeroPrograms();

        // Scenario A: Period A's live program is shown
        $this->assertTrue($heroInitial->contains('slug', 'live-period-a'));
        $this->assertFalse($heroInitial->contains('slug', 'live-period-b'));

        // Scenario B, C & G: Period B is activated via common service
        app(\App\Services\Schedule\ScheduleCalendarService::class)->activateTemplate($templateB);

        $this->assertFalse($templateA->fresh()->is_active);
        $this->assertTrue($templateB->fresh()->is_active);

        // Without manual forget, service must return Period B immediately (cache was invalidated)
        $heroAfter = $service->resolveHeroPrograms();
        $this->assertTrue($heroAfter->contains('slug', 'live-period-b'));
        $this->assertFalse($heroAfter->contains('slug', 'live-period-a'));

        Carbon::setTestNow();
    }

    public function test_manual_hero_inclusion_exclusion_hero_days_and_sort_order(): void
    {
        Carbon::setTestNow('2026-10-15 10:00:00'); // Thursday

        $prog1 = Program::create([
            'name' => 'Manual Show 1',
            'slug' => 'manual-show-1',
            'is_active' => true,
            'show_on_public' => true,
            'is_featured' => true,
            'hero_days' => ['thursday'],
            'sort_order' => 10,
        ]);

        $prog2 = Program::create([
            'name' => 'Manual Show 2',
            'slug' => 'manual-show-2',
            'is_active' => true,
            'show_on_public' => true,
            'is_featured' => true,
            'hero_days' => ['thursday'],
            'sort_order' => 2,
        ]);

        SiteCache::forgetHomeHeroPrograms();
        $service = app(HomepageDataService::class);
        $hero = $service->resolveHeroPrograms();

        // Scenario D: Programs appear on appropriate day
        $this->assertTrue($hero->contains('slug', 'manual-show-1'));
        $this->assertTrue($hero->contains('slug', 'manual-show-2'));

        // Scenario F: sort_order determines sequence (prog2 before prog1)
        $prog2Index = $hero->search(fn ($p) => $p->slug === 'manual-show-2');
        $prog1Index = $hero->search(fn ($p) => $p->slug === 'manual-show-1');
        $this->assertLessThan($prog1Index, $prog2Index);

        // Scenario E: When excluded from manual hero, disappears from Hero
        $prog1->update(['is_featured' => false]);
        $heroUpdated = $service->resolveHeroPrograms();
        $this->assertFalse($heroUpdated->contains('slug', 'manual-show-1'));

        Carbon::setTestNow();
    }

    public function test_deduplication_and_passive_item_exclusion(): void
    {
        Carbon::setTestNow('2026-10-15 10:00:00'); // Thursday

        $program = Program::create([
            'name' => 'Dual Candidate Show',
            'slug' => 'dual-candidate',
            'is_active' => true,
            'show_on_public' => true,
            'is_featured' => true,
            'hero_days' => ['thursday'],
            'sort_order' => 99,
        ]);

        $passiveLiveProgram = Program::create([
            'name' => 'Passive Live Show',
            'slug' => 'passive-live-show',
            'is_active' => true,
            'show_on_public' => true,
            'is_featured' => false,
        ]);

        $template = ScheduleTemplate::create([
            'name' => 'Dedupe Template',
            'is_active' => true,
            'status' => 'published',
            'valid_from' => '2026-01-01',
        ]);

        // Dual candidate is live in schedule
        ScheduleTemplateItem::create([
            'schedule_template_id' => $template->id,
            'day_of_week' => 3,
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'program_id' => $program->id,
            'is_live' => true,
            'is_active' => true,
        ]);

        // Passive item is marked live, but is_active = false
        ScheduleTemplateItem::create([
            'schedule_template_id' => $template->id,
            'day_of_week' => 3,
            'start_time' => '12:00:00',
            'end_time' => '13:00:00',
            'program_id' => $passiveLiveProgram->id,
            'is_live' => true,
            'is_active' => false,
        ]);

        SiteCache::forgetHomeHeroPrograms();
        $service = app(HomepageDataService::class);
        $hero = $service->resolveHeroPrograms();

        // Scenario I: Appears exactly once (deduplicated)
        $this->assertEquals(1, $hero->where('slug', 'dual-candidate')->count());

        // Scenario J: Passive live item does NOT appear in Hero
        $this->assertFalse($hero->contains('slug', 'passive-live-show'));

        Carbon::setTestNow();
    }
}


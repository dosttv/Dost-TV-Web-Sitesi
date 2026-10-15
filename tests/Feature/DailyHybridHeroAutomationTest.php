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

    public function test_legacy_null_or_empty_hero_days_is_never_shown(): void
    {
        Carbon::setTestNow('2026-09-08 10:00:00'); // Tuesday

        $programNull = Program::create([
            'name' => 'Legacy Null Days Program',
            'slug' => 'legacy-null-program',
            'is_active' => true,
            'show_on_public' => true,
            'is_featured' => true,
            'hero_days' => null, // Legacy null hero_days
            'sort_order' => 1,
        ]);

        $programEmpty = Program::create([
            'name' => 'Legacy Empty Array Program',
            'slug' => 'legacy-empty-program',
            'is_active' => true,
            'show_on_public' => true,
            'is_featured' => true,
            'hero_days' => [], // Empty array hero_days
            'sort_order' => 2,
        ]);

        SiteCache::forgetHomeHeroPrograms();

        /** @var HomepageDataService $service */
        $service = app(HomepageDataService::class);
        $heroPrograms = $service->resolveHeroPrograms();

        // New rule: hero_days null or [] must NEVER be shown on any day
        $this->assertFalse($heroPrograms->contains('id', $programNull->id));
        $this->assertFalse($heroPrograms->contains('id', $programEmpty->id));

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

    /**
     * Senaryo 1: Bugün canlı yayını olan aktif program otomatik Hero'da görünür.
     */
    public function test_scenario_1_active_live_program_today_automatically_appears_in_hero(): void
    {
        Carbon::setTestNow('2026-10-15 14:00:00'); // Thursday (ISO day 3)

        $program = Program::create([
            'name' => 'Canlı Yayın Programı',
            'slug' => 'canli-yayin-programi',
            'is_active' => true,
            'show_on_public' => true,
            'is_featured' => false, // Manuel hero kapalı
            'hero_days' => null,
        ]);

        $template = ScheduleTemplate::create([
            'name' => 'Aktif Dönem',
            'is_active' => true,
            'status' => 'published',
            'valid_from' => '2026-01-01',
        ]);

        ScheduleTemplateItem::create([
            'schedule_template_id' => $template->id,
            'day_of_week' => 3, // Thursday
            'start_time' => '14:00:00',
            'end_time' => '15:00:00',
            'program_id' => $program->id,
            'is_live' => true,
            'is_active' => true,
        ]);

        SiteCache::forgetHomeHeroPrograms();
        $hero = app(HomepageDataService::class)->resolveHeroPrograms();

        $this->assertTrue($hero->contains('id', $program->id));
        Carbon::setTestNow();
    }

    /**
     * Senaryo 2: Bugün canlı yayını olmayan, Hero'da Göster açık ama günleri boş program görünmez.
     */
    public function test_scenario_2_non_live_program_with_is_featured_true_but_empty_hero_days_does_not_appear(): void
    {
        Carbon::setTestNow('2026-10-15 14:00:00'); // Thursday

        $programNullDays = Program::create([
            'name' => 'Hero Açık Ama Günleri Null',
            'slug' => 'hero-null-days',
            'is_active' => true,
            'show_on_public' => true,
            'is_featured' => true,
            'hero_days' => null,
        ]);

        $programEmptyDays = Program::create([
            'name' => 'Hero Açık Ama Günleri Boş Dizi',
            'slug' => 'hero-empty-days',
            'is_active' => true,
            'show_on_public' => true,
            'is_featured' => true,
            'hero_days' => [],
        ]);

        SiteCache::forgetHomeHeroPrograms();
        $hero = app(HomepageDataService::class)->resolveHeroPrograms();

        $this->assertFalse($hero->contains('id', $programNullDays->id));
        $this->assertFalse($hero->contains('id', $programEmptyDays->id));
        Carbon::setTestNow();
    }

    /**
     * Senaryo 3: Hero'da Göster açık ve bugünün günü seçili program manuel Hero'da görünür.
     */
    public function test_scenario_3_is_featured_true_with_today_selected_appears_in_manual_hero(): void
    {
        Carbon::setTestNow('2026-10-15 14:00:00'); // Thursday

        $program = Program::create([
            'name' => 'Perşembe Manuel Hero',
            'slug' => 'persembe-manuel-hero',
            'is_active' => true,
            'show_on_public' => true,
            'is_featured' => true,
            'hero_days' => ['thursday', 'friday'],
        ]);

        SiteCache::forgetHomeHeroPrograms();
        $hero = app(HomepageDataService::class)->resolveHeroPrograms();

        $this->assertTrue($hero->contains('id', $program->id));
        Carbon::setTestNow();
    }

    /**
     * Senaryo 4: Hero'da Göster açık ancak yalnızca başka gün seçili program bugün görünmez.
     */
    public function test_scenario_4_is_featured_true_with_other_day_only_selected_does_not_appear_today(): void
    {
        Carbon::setTestNow('2026-10-15 14:00:00'); // Thursday

        $program = Program::create([
            'name' => 'Sadece Cuma Manuel Hero',
            'slug' => 'sadece-cuma-manuel-hero',
            'is_active' => true,
            'show_on_public' => true,
            'is_featured' => true,
            'hero_days' => ['friday', 'saturday'],
        ]);

        SiteCache::forgetHomeHeroPrograms();
        $hero = app(HomepageDataService::class)->resolveHeroPrograms();

        $this->assertFalse($hero->contains('id', $program->id));
        Carbon::setTestNow();
    }

    /**
     * Senaryo 5: Hero'da Göster kapalı ancak bugünün canlı yayını olan program otomatik görünür.
     */
    public function test_scenario_5_is_featured_false_program_with_live_broadcast_today_appears_automatically(): void
    {
        Carbon::setTestNow('2026-10-15 14:00:00'); // Thursday

        $program = Program::create([
            'name' => 'Hero Kapalı Canlı Program',
            'slug' => 'hero-kapali-canli-program',
            'is_active' => true,
            'show_on_public' => true,
            'is_featured' => false,
            'hero_days' => [],
        ]);

        $template = ScheduleTemplate::create([
            'name' => 'Aktif Dönem',
            'is_active' => true,
            'status' => 'published',
            'valid_from' => '2026-01-01',
        ]);

        ScheduleTemplateItem::create([
            'schedule_template_id' => $template->id,
            'day_of_week' => 3, // Thursday
            'start_time' => '16:00:00',
            'end_time' => '17:00:00',
            'program_id' => $program->id,
            'is_live' => true,
            'is_active' => true,
        ]);

        SiteCache::forgetHomeHeroPrograms();
        $hero = app(HomepageDataService::class)->resolveHeroPrograms();

        $this->assertTrue($hero->contains('id', $program->id));
        Carbon::setTestNow();
    }

    /**
     * Senaryo 6: Pasif program Hero'da görünmez.
     */
    public function test_scenario_6_inactive_program_never_appears_in_hero(): void
    {
        Carbon::setTestNow('2026-10-15 14:00:00'); // Thursday

        // Pasif program (status = archived, show_on_public = true -> is_active = false)
        $program = Program::create([
            'name' => 'Pasif Program',
            'slug' => 'pasif-program',
            'status' => 'archived',
            'show_on_public' => true,
            'is_featured' => true,
            'hero_days' => ['thursday'],
        ]);

        SiteCache::forgetHomeHeroPrograms();
        $hero = app(HomepageDataService::class)->resolveHeroPrograms();

        $this->assertFalse($hero->contains('id', $program->id));
        Carbon::setTestNow();
    }

    /**
     * Senaryo 7: Public görünürlüğü kapalı program Hero'da görünmez.
     */
    public function test_scenario_7_non_public_program_never_appears_in_hero(): void
    {
        Carbon::setTestNow('2026-10-15 14:00:00'); // Thursday

        $program = Program::create([
            'name' => 'Gizli Program',
            'slug' => 'gizli-program',
            'status' => 'active',
            'show_on_public' => false,
            'is_featured' => true,
            'hero_days' => ['thursday'],
        ]);

        SiteCache::forgetHomeHeroPrograms();
        $hero = app(HomepageDataService::class)->resolveHeroPrograms();

        $this->assertFalse($hero->contains('id', $program->id));
        Carbon::setTestNow();
    }

    /**
     * Senaryo 8: Aynı program hem otomatik hem manuel aday olduğunda bir kez görünür.
     */
    public function test_scenario_8_program_both_live_and_manual_candidate_appears_exactly_once(): void
    {
        Carbon::setTestNow('2026-10-15 14:00:00'); // Thursday

        $program = Program::create([
            'name' => 'Çift Aday Program',
            'slug' => 'cift-aday-program',
            'is_active' => true,
            'show_on_public' => true,
            'is_featured' => true,
            'hero_days' => ['thursday'],
            'sort_order' => 1,
        ]);

        $template = ScheduleTemplate::create([
            'name' => 'Aktif Dönem',
            'is_active' => true,
            'status' => 'published',
            'valid_from' => '2026-01-01',
        ]);

        ScheduleTemplateItem::create([
            'schedule_template_id' => $template->id,
            'day_of_week' => 3, // Thursday
            'start_time' => '18:00:00',
            'end_time' => '19:00:00',
            'program_id' => $program->id,
            'is_live' => true,
            'is_active' => true,
        ]);

        SiteCache::forgetHomeHeroPrograms();
        $hero = app(HomepageDataService::class)->resolveHeroPrograms();

        $matchingCount = $hero->where('id', $program->id)->count();
        $this->assertEquals(1, $matchingCount);
        Carbon::setTestNow();
    }

    /**
     * Senaryo 9: Manuel programlar Hero Sırası değerine göre sıralanır.
     */
    public function test_scenario_9_manual_programs_are_ordered_by_sort_order_and_id(): void
    {
        Carbon::setTestNow('2026-10-15 14:00:00'); // Thursday

        $prog1 = Program::create([
            'name' => 'Manuel Program Sıra 50',
            'slug' => 'manuel-prog-50',
            'is_active' => true,
            'show_on_public' => true,
            'is_featured' => true,
            'hero_days' => ['thursday'],
            'sort_order' => 50,
        ]);

        $prog2 = Program::create([
            'name' => 'Manuel Program Sıra 5',
            'slug' => 'manuel-prog-5',
            'is_active' => true,
            'show_on_public' => true,
            'is_featured' => true,
            'hero_days' => ['thursday'],
            'sort_order' => 5,
        ]);

        $prog3 = Program::create([
            'name' => 'Manuel Program Sıra 20',
            'slug' => 'manuel-prog-20',
            'is_active' => true,
            'show_on_public' => true,
            'is_featured' => true,
            'hero_days' => ['thursday'],
            'sort_order' => 20,
        ]);

        SiteCache::forgetHomeHeroPrograms();
        $hero = app(HomepageDataService::class)->resolveHeroPrograms();

        $idx2 = $hero->search(fn ($p) => $p->id === $prog2->id);
        $idx3 = $hero->search(fn ($p) => $p->id === $prog3->id);
        $idx1 = $hero->search(fn ($p) => $p->id === $prog1->id);

        $this->assertLessThan($idx3, $idx2); // 5 comes before 20
        $this->assertLessThan($idx1, $idx3); // 20 comes before 50
        Carbon::setTestNow();
    }

    /**
     * Senaryo 10: Perşembe'den Cuma'ya geçildiğinde manuel programlar gün kurallarına göre değişir.
     */
    public function test_scenario_10_transition_from_thursday_to_friday_updates_manual_programs_by_day_rules(): void
    {
        Carbon::setTestNow('2026-10-15 14:00:00'); // Thursday

        $thursdayProg = Program::create([
            'name' => 'Sadece Perşembe Programı',
            'slug' => 'sadece-persembe-prog',
            'is_active' => true,
            'show_on_public' => true,
            'is_featured' => true,
            'hero_days' => ['thursday'],
        ]);

        $fridayProg = Program::create([
            'name' => 'Sadece Cuma Programı',
            'slug' => 'sadece-cuma-prog',
            'is_active' => true,
            'show_on_public' => true,
            'is_featured' => true,
            'hero_days' => ['friday'],
        ]);

        $service = app(HomepageDataService::class);

        // Perşembe kontrolü
        SiteCache::forgetHomeHeroPrograms();
        $thursdayHero = $service->resolveHeroPrograms();
        $this->assertTrue($thursdayHero->contains('id', $thursdayProg->id));
        $this->assertFalse($thursdayHero->contains('id', $fridayProg->id));

        // Cuma'ya geçiş
        Carbon::setTestNow('2026-10-16 14:00:00'); // Friday
        SiteCache::forgetHomeHeroPrograms();
        $fridayHero = $service->resolveHeroPrograms();
        $this->assertFalse($fridayHero->contains('id', $thursdayProg->id));
        $this->assertTrue($fridayHero->contains('id', $fridayProg->id));

        Carbon::setTestNow();
    }

    /**
     * Senaryo 11: Yayın dönemi değiştiğinde otomatik canlı programlar yeni döneme göre güncellenir.
     */
    public function test_scenario_11_broadcast_period_change_updates_live_programs_to_new_period(): void
    {
        Carbon::setTestNow('2026-10-15 14:00:00'); // Thursday

        $progPeriod1 = Program::create([
            'name' => '1. Dönem Canlı Programı',
            'slug' => 'donem-1-prog',
            'is_active' => true,
            'show_on_public' => true,
            'is_featured' => false,
        ]);

        $progPeriod2 = Program::create([
            'name' => '2. Dönem Canlı Programı',
            'slug' => 'donem-2-prog',
            'is_active' => true,
            'show_on_public' => true,
            'is_featured' => false,
        ]);

        $template1 = ScheduleTemplate::create([
            'name' => 'Dönem 1 (Eski)',
            'is_active' => true,
            'status' => 'published',
            'valid_from' => '2026-01-01',
        ]);

        ScheduleTemplateItem::create([
            'schedule_template_id' => $template1->id,
            'day_of_week' => 3,
            'start_time' => '14:00:00',
            'end_time' => '15:00:00',
            'program_id' => $progPeriod1->id,
            'is_live' => true,
            'is_active' => true,
        ]);

        $template2 = ScheduleTemplate::create([
            'name' => 'Dönem 2 (Yeni)',
            'is_active' => false,
            'status' => 'published',
            'valid_from' => '2026-06-01',
        ]);

        ScheduleTemplateItem::create([
            'schedule_template_id' => $template2->id,
            'day_of_week' => 3,
            'start_time' => '14:00:00',
            'end_time' => '15:00:00',
            'program_id' => $progPeriod2->id,
            'is_live' => true,
            'is_active' => true,
        ]);

        SiteCache::forgetHomeHeroPrograms();
        $service = app(HomepageDataService::class);
        $heroInitial = $service->resolveHeroPrograms();
        $this->assertTrue($heroInitial->contains('id', $progPeriod1->id));
        $this->assertFalse($heroInitial->contains('id', $progPeriod2->id));

        // Dönem 2'yi gösterime al
        app(\App\Services\Schedule\ScheduleCalendarService::class)->activateTemplate($template2);

        $heroAfter = $service->resolveHeroPrograms();
        $this->assertFalse($heroAfter->contains('id', $progPeriod1->id));
        $this->assertTrue($heroAfter->contains('id', $progPeriod2->id));

        Carbon::setTestNow();
    }

    /**
     * Senaryo 12: Hero Günleri değişikliği kaydedildikten sonra cache temizlenir ve yeni sonuç görüntülenir.
     */
    public function test_scenario_12_updating_hero_days_clears_cache_and_shows_updated_results(): void
    {
        Carbon::setTestNow('2026-10-15 14:00:00'); // Thursday

        $program = Program::create([
            'name' => 'Dinamik Gün Değişen Program',
            'slug' => 'dinamik-gun-prog',
            'is_active' => true,
            'show_on_public' => true,
            'is_featured' => true,
            'hero_days' => ['friday'], // İlk başta sadece Cuma
        ]);

        $service = app(HomepageDataService::class);

        // Perşembe günü Hero'da yok
        SiteCache::forgetHomeHeroPrograms();
        $this->assertFalse($service->resolveHeroPrograms()->contains('id', $program->id));

        // Editör Perşembe'yi ekliyor ve kaydediyor (Model event cache invalidation)
        $program->update([
            'hero_days' => ['thursday', 'friday'],
        ]);

        // Cache manuel silinmeden doğrudan servisten çekildiğinde Hero'da belirmeli
        $this->assertTrue($service->resolveHeroPrograms()->contains('id', $program->id));

        // Editör günleri boşaltıyor
        $program->update([
            'hero_days' => [],
        ]);

        // Gün boşaltıldığında anında Hero'dan düşmeli
        $this->assertFalse($service->resolveHeroPrograms()->contains('id', $program->id));

        Carbon::setTestNow();
    }
}


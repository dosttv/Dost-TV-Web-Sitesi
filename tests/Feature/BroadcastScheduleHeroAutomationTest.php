<?php

namespace Tests\Feature;

use App\Models\Program;
use App\Models\ScheduleException;
use App\Models\ScheduleExceptionItem;
use App\Models\ScheduleTemplate;
use App\Models\ScheduleTemplateItem;
use App\Services\Home\HomepageDataService;
use App\Support\SiteCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BroadcastScheduleHeroAutomationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Senaryo 1: Yeni CANLI yayın kaydında Hero otomatik açık.
     */
    public function test_scenario_1_new_live_broadcast_has_show_in_hero_automatically_true(): void
    {
        $template = ScheduleTemplate::create([
            'name' => 'Dönem 1',
            'status' => 'published',
            'is_active' => true,
        ]);

        $program = Program::create([
            'name' => 'Canlı Sabah Programı',
            'slug' => 'canli-sabah-programi',
            'is_active' => true,
            'show_on_public' => true,
        ]);

        // When created with is_live = true and show_in_hero is not passed, it automatically becomes true
        $item = ScheduleTemplateItem::create([
            'schedule_template_id' => $template->id,
            'program_id' => $program->id,
            'day_of_week' => 0,
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'is_live' => true,
            'is_active' => true,
        ]);

        $this->assertTrue((bool) $item->fresh()->show_in_hero);
    }

    /**
     * Senaryo 2: NORMAL yayın kaydında Hero başlangıçta kapalı.
     */
    public function test_scenario_2_normal_broadcast_has_show_in_hero_false_by_default(): void
    {
        $template = ScheduleTemplate::create([
            'name' => 'Dönem 2',
            'status' => 'published',
            'is_active' => true,
        ]);

        $program = Program::create([
            'name' => 'Normal Yayın Programı',
            'slug' => 'normal-yayin-programi',
            'is_active' => true,
            'show_on_public' => true,
        ]);

        $item = ScheduleTemplateItem::create([
            'schedule_template_id' => $template->id,
            'program_id' => $program->id,
            'day_of_week' => 0,
            'start_time' => '11:00:00',
            'end_time' => '12:00:00',
            'is_live' => false,
            'is_active' => true,
        ]);

        $this->assertFalse((bool) $item->fresh()->show_in_hero);
    }

    /**
     * Senaryo 3: NORMAL, TEKRAR veya PAKET yayın manuel Hero açık yapıldığında gösterilir.
     */
    public function test_scenario_3_normal_repeat_or_package_broadcast_shows_in_hero_when_manually_enabled(): void
    {
        Carbon::setTestNow('2026-10-16 10:00:00'); // Cuma (day_of_week = 4)
        $fridayIndex = 4;

        $template = ScheduleTemplate::create([
            'name' => 'Dönem 3',
            'status' => 'published',
            'is_active' => true,
        ]);

        $programNormal = Program::create([
            'name' => 'Normal Belgesel',
            'slug' => 'normal-belgesel',
            'is_active' => true,
            'show_on_public' => true,
        ]);

        $programRepeat = Program::create([
            'name' => 'Tekrar Dizi',
            'slug' => 'tekrar-dizi',
            'is_active' => true,
            'show_on_public' => true,
        ]);

        $programPackage = Program::create([
            'name' => 'Paket Program',
            'slug' => 'paket-program',
            'is_active' => true,
            'show_on_public' => true,
        ]);

        ScheduleTemplateItem::create([
            'schedule_template_id' => $template->id,
            'program_id' => $programNormal->id,
            'day_of_week' => $fridayIndex,
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'is_live' => false,
            'show_in_hero' => true,
            'is_active' => true,
        ]);

        ScheduleTemplateItem::create([
            'schedule_template_id' => $template->id,
            'program_id' => $programRepeat->id,
            'day_of_week' => $fridayIndex,
            'start_time' => '11:00:00',
            'end_time' => '12:00:00',
            'is_repeat' => true,
            'show_in_hero' => true,
            'is_active' => true,
        ]);

        ScheduleTemplateItem::create([
            'schedule_template_id' => $template->id,
            'program_id' => $programPackage->id,
            'day_of_week' => $fridayIndex,
            'start_time' => '14:00:00',
            'end_time' => '15:00:00',
            'is_package' => true,
            'show_in_hero' => true,
            'is_active' => true,
        ]);

        $service = app(HomepageDataService::class);
        $hero = $service->resolveHeroPrograms();

        $this->assertCount(3, $hero);
        $this->assertEquals([$programNormal->id, $programRepeat->id, $programPackage->id], $hero->pluck('id')->all());

        Carbon::setTestNow();
    }

    /**
     * Senaryo 4: CANLI yayın Hero kapatıldığında gösterilmez.
     */
    public function test_scenario_4_live_broadcast_does_not_show_in_hero_when_editor_turns_hero_off(): void
    {
        Carbon::setTestNow('2026-10-16 10:00:00');
        $fridayIndex = 4;

        $template = ScheduleTemplate::create([
            'name' => 'Dönem 4',
            'status' => 'published',
            'is_active' => true,
        ]);

        $program = Program::create([
            'name' => 'Canlı Fakat Hero Kapalı',
            'slug' => 'canli-fakat-hero-kapali',
            'is_active' => true,
            'show_on_public' => true,
        ]);

        $item = ScheduleTemplateItem::create([
            'schedule_template_id' => $template->id,
            'program_id' => $program->id,
            'day_of_week' => $fridayIndex,
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'is_live' => true,
            'is_active' => true,
        ]);

        // Editor turns hero toggle off
        $item->update(['show_in_hero' => false]);
        $this->assertFalse((bool) $item->fresh()->show_in_hero);

        $service = app(HomepageDataService::class);
        $hero = $service->resolveHeroPrograms();

        $this->assertEmpty($hero);

        Carbon::setTestNow();
    }

    /**
     * Senaryo 5: CANLI yayın düzenlenirken kapatılmış Hero seçimi yeniden açılmaz.
     */
    public function test_scenario_5_editing_already_live_broadcast_does_not_force_reopen_hero_if_editor_had_closed_it(): void
    {
        $template = ScheduleTemplate::create([
            'name' => 'Dönem 5',
            'status' => 'published',
            'is_active' => true,
        ]);

        $program = Program::create([
            'name' => 'Canlı Program 5',
            'slug' => 'canli-program-5',
            'is_active' => true,
        ]);

        $item = ScheduleTemplateItem::create([
            'schedule_template_id' => $template->id,
            'program_id' => $program->id,
            'day_of_week' => 0,
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'is_live' => true,
            'show_in_hero' => false,
            'is_active' => true,
        ]);

        $this->assertFalse((bool) $item->fresh()->show_in_hero);

        // Edit other fields or save again while still is_live = true
        $item->update([
            'custom_title' => 'Özel Başlık Güncellemesi',
            'start_time' => '10:15:00',
        ]);

        $this->assertFalse((bool) $item->fresh()->show_in_hero);
        $this->assertEquals('Özel Başlık Güncellemesi', $item->fresh()->custom_title);
    }

    /**
     * Senaryo 6: NORMAL yayın CANLI'ya çevrilince Hero açılır.
     */
    public function test_scenario_6_normal_broadcast_switched_to_live_automatically_turns_hero_on(): void
    {
        $template = ScheduleTemplate::create([
            'name' => 'Dönem 6',
            'status' => 'published',
            'is_active' => true,
        ]);

        $program = Program::create([
            'name' => 'Normal Program 6',
            'slug' => 'normal-program-6',
            'is_active' => true,
        ]);

        $item = ScheduleTemplateItem::create([
            'schedule_template_id' => $template->id,
            'program_id' => $program->id,
            'day_of_week' => 0,
            'start_time' => '12:00:00',
            'end_time' => '13:00:00',
            'is_live' => false,
            'show_in_hero' => false,
            'is_active' => true,
        ]);

        $this->assertFalse((bool) $item->fresh()->show_in_hero);

        // Switch to live
        $item->update(['is_live' => true]);

        $this->assertTrue((bool) $item->fresh()->show_in_hero);
    }

    /**
     * Senaryo 7: CANLI yayın NORMAL'e çevrilince Hero tercihi korunur.
     */
    public function test_scenario_7_live_broadcast_switched_to_normal_preserves_hero_preference(): void
    {
        $template = ScheduleTemplate::create([
            'name' => 'Dönem 7',
            'status' => 'published',
            'is_active' => true,
        ]);

        $program = Program::create([
            'name' => 'Program 7',
            'slug' => 'program-7',
            'is_active' => true,
        ]);

        // Case A: show_in_hero was true, switching to NORMAL keeps show_in_hero = true
        $itemA = ScheduleTemplateItem::create([
            'schedule_template_id' => $template->id,
            'program_id' => $program->id,
            'day_of_week' => 0,
            'start_time' => '14:00:00',
            'end_time' => '15:00:00',
            'is_live' => true,
            'show_in_hero' => true,
            'is_active' => true,
        ]);

        $itemA->update(['is_live' => false]);
        $this->assertTrue((bool) $itemA->fresh()->show_in_hero);

        // Case B: show_in_hero was false, switching to NORMAL keeps show_in_hero = false
        $itemB = ScheduleTemplateItem::create([
            'schedule_template_id' => $template->id,
            'program_id' => $program->id,
            'day_of_week' => 1,
            'start_time' => '14:00:00',
            'end_time' => '15:00:00',
            'is_live' => true,
            'show_in_hero' => false,
            'is_active' => true,
        ]);

        $itemB->update(['is_live' => false]);
        $this->assertFalse((bool) $itemB->fresh()->show_in_hero);
    }

    /**
     * Senaryo 8: Akışta Görünür kapalıysa Hero'da gösterilmez, ama Hero tercihi DB'de korunur.
     */
    public function test_scenario_8_broadcast_does_not_show_in_hero_when_is_active_is_false_but_keeps_hero_flag(): void
    {
        Carbon::setTestNow('2026-10-16 10:00:00');
        $fridayIndex = 4;

        $template = ScheduleTemplate::create([
            'name' => 'Dönem 8',
            'status' => 'published',
            'is_active' => true,
        ]);

        $program = Program::create([
            'name' => 'Program 8',
            'slug' => 'program-8',
            'is_active' => true,
            'show_on_public' => true,
        ]);

        $item = ScheduleTemplateItem::create([
            'schedule_template_id' => $template->id,
            'program_id' => $program->id,
            'day_of_week' => $fridayIndex,
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'is_live' => true,
            'show_in_hero' => true,
            'is_active' => false, // Akışta görünür KAPALI
        ]);

        $service = app(HomepageDataService::class);
        $hero = $service->resolveHeroPrograms();

        // Not shown in Hero because is_active is false
        $this->assertEmpty($hero);

        // But DB value is preserved as true
        $this->assertTrue((bool) $item->fresh()->show_in_hero);

        // When is_active is turned back on, it immediately reappears in Hero
        $item->update(['is_active' => true]);
        $heroAfter = $service->resolveHeroPrograms();
        $this->assertCount(1, $heroAfter);
        $this->assertEquals($program->id, $heroAfter->first()->id);

        Carbon::setTestNow();
    }

    /**
     * Senaryo 9: Hero anahtarı değişince cache temizlenir.
     */
    public function test_scenario_9_changing_show_in_hero_clears_hero_cache_immediately(): void
    {
        Carbon::setTestNow('2026-10-16 10:00:00');
        $fridayIndex = 4;

        $template = ScheduleTemplate::create([
            'name' => 'Dönem 9',
            'status' => 'published',
            'is_active' => true,
        ]);

        $program = Program::create([
            'name' => 'Program 9',
            'slug' => 'program-9',
            'is_active' => true,
            'show_on_public' => true,
        ]);

        $item = ScheduleTemplateItem::create([
            'schedule_template_id' => $template->id,
            'program_id' => $program->id,
            'day_of_week' => $fridayIndex,
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'is_live' => false,
            'show_in_hero' => false,
            'is_active' => true,
        ]);

        $service = app(HomepageDataService::class);

        // Warm up cache (should be empty)
        $initial = $service->resolveHeroPrograms();
        $this->assertEmpty($initial);

        // Turn hero on -> model saved event fires and clears SiteCache
        $item->update(['show_in_hero' => true]);

        // Next call fetches updated data immediately without stale cache
        $updated = $service->resolveHeroPrograms();
        $this->assertCount(1, $updated);
        $this->assertEquals($program->id, $updated->first()->id);

        Carbon::setTestNow();
    }

    /**
     * Senaryo 10: Aktif dönem düzenlenince taslağa düşmez.
     */
    public function test_scenario_10_editing_active_schedule_period_does_not_demote_it_to_draft(): void
    {
        $template = ScheduleTemplate::create([
            'name' => 'Aktif Dönem 10',
            'slug' => 'aktif-donem-10',
            'status' => 'published',
            'is_active' => true,
        ]);

        $program = Program::create(['name' => 'Program 10', 'slug' => 'program-10', 'is_active' => true]);

        $item = ScheduleTemplateItem::create([
            'schedule_template_id' => $template->id,
            'program_id' => $program->id,
            'day_of_week' => 0,
            'start_time' => '10:00',
            'end_time' => '11:00',
            'is_live' => false,
            'is_active' => true,
        ]);

        // Toggle show_in_hero or update item
        $item->update(['show_in_hero' => true]);

        $this->assertEquals('published', $template->fresh()->status);
        $this->assertTrue((bool) $template->fresh()->is_active);
    }

    /**
     * Senaryo 11: Birden fazla afiş varsa slider okları görünür, tek afişte gizlenir.
     */
    public function test_scenario_11_multiple_hero_items_display_slider_arrows_single_item_does_not(): void
    {
        Carbon::setTestNow('2026-10-16 10:00:00');
        $fridayIndex = 4;

        $template = ScheduleTemplate::create([
            'name' => 'Dönem 11',
            'status' => 'published',
            'is_active' => true,
        ]);

        $p1 = Program::create([
            'name' => 'Hero Program 11A',
            'slug' => 'hero-program-11a',
            'is_active' => true,
            'show_on_public' => true,
        ]);

        $item1 = ScheduleTemplateItem::create([
            'schedule_template_id' => $template->id,
            'program_id' => $p1->id,
            'day_of_week' => $fridayIndex,
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'show_in_hero' => true,
            'is_active' => true,
        ]);

        // 1 item -> no navigation arrows
        $resSingle = $this->get(route('home'));
        $resSingle->assertStatus(200);
        $resSingle->assertSee('Hero Program 11A');
        $resSingle->assertDontSee('Önceki program');

        $p2 = Program::create([
            'name' => 'Hero Program 11B',
            'slug' => 'hero-program-11b',
            'is_active' => true,
            'show_on_public' => true,
        ]);

        $item2 = ScheduleTemplateItem::create([
            'schedule_template_id' => $template->id,
            'program_id' => $p2->id,
            'day_of_week' => $fridayIndex,
            'start_time' => '14:00:00',
            'end_time' => '15:00:00',
            'show_in_hero' => true,
            'is_active' => true,
        ]);

        // 2 items -> navigation arrows present
        $resMultiple = $this->get(route('home'));
        $resMultiple->assertStatus(200);
        $resMultiple->assertSee('Hero Program 11A');
        $resMultiple->assertSee('Hero Program 11B');
        $resMultiple->assertSee('Önceki program');
        $resMultiple->assertSee('Sonraki program');

        Carbon::setTestNow();
    }

    /**
     * Senaryo 12: Aynı program birden fazla kez seçilmişse Hero'da tek görünür.
     */
    public function test_scenario_12_same_program_multiple_hero_broadcasts_is_deduplicated_by_earliest_start_time(): void
    {
        Carbon::setTestNow('2026-10-16 08:00:00');
        $fridayIndex = 4;

        $template = ScheduleTemplate::create([
            'name' => 'Dönem 12',
            'status' => 'published',
            'is_active' => true,
        ]);

        $program = Program::create([
            'name' => 'Gündem Özel 12',
            'slug' => 'gundem-ozel-12',
            'is_active' => true,
            'show_on_public' => true,
        ]);

        // Broadcast 1: 10:00 - 11:00 with Hero enabled
        ScheduleTemplateItem::create([
            'schedule_template_id' => $template->id,
            'program_id' => $program->id,
            'day_of_week' => $fridayIndex,
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'show_in_hero' => true,
            'is_active' => true,
        ]);

        // Broadcast 2: 18:00 - 19:00 with Hero enabled (Same program)
        ScheduleTemplateItem::create([
            'schedule_template_id' => $template->id,
            'program_id' => $program->id,
            'day_of_week' => $fridayIndex,
            'start_time' => '18:00:00',
            'end_time' => '19:00:00',
            'show_in_hero' => true,
            'is_active' => true,
        ]);

        $service = app(HomepageDataService::class);
        $hero = $service->resolveHeroPrograms();

        $this->assertCount(1, $hero);
        $this->assertEquals($program->id, $hero->first()->id);

        Carbon::setTestNow();
    }

    /**
     * Senaryo 13: Yayın istisnaları varsa onların Hero seçimleri de doğru çözülür.
     */
    public function test_scenario_13_schedule_exception_items_resolve_hero_correctly(): void
    {
        Carbon::setTestNow('2026-10-16 08:00:00'); // Date: 2026-10-16

        $programExceptionHero = Program::create([
            'name' => 'Özel Gün Hero Programı',
            'slug' => 'ozel-gun-hero-programi',
            'is_active' => true,
            'show_on_public' => true,
        ]);

        $programExceptionNormal = Program::create([
            'name' => 'Özel Gün Normal Programı',
            'slug' => 'ozel-gun-normal-programi',
            'is_active' => true,
            'show_on_public' => true,
        ]);

        $exception = ScheduleException::create([
            'name' => 'Kandil Özel Yayını',
            'exception_date' => '2026-10-16',
            'override_type' => 'replace_all',
            'status' => 'published',
        ]);

        // Exception item 1 with Hero enabled
        ScheduleExceptionItem::create([
            'schedule_exception_id' => $exception->id,
            'program_id' => $programExceptionHero->id,
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
            'is_live' => true,
            'show_in_hero' => true,
            'is_active' => true,
        ]);

        // Exception item 2 with Hero disabled
        ScheduleExceptionItem::create([
            'schedule_exception_id' => $exception->id,
            'program_id' => $programExceptionNormal->id,
            'start_time' => '14:00:00',
            'end_time' => '16:00:00',
            'is_live' => false,
            'show_in_hero' => false,
            'is_active' => true,
        ]);

        $service = app(HomepageDataService::class);
        $hero = $service->resolveHeroPrograms();

        $this->assertCount(1, $hero);
        $this->assertEquals($programExceptionHero->id, $hero->first()->id);

        Carbon::setTestNow();
    }
}

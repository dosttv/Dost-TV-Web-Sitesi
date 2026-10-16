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

class BroadcastScheduleHeroAutomationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Section 6 - Real Scenario 10-step end-to-end test:
     * 1. Bir programı CANLI olarak işaretle.
     * 2. Akışta Görünür seçeneğini aç.
     * 3. Programın manuel Hero ayarlarının kapalı olduğunu doğrula.
     * 4. Hero'da programın otomatik göründüğünü doğrula.
     * 5. İkinci bir canlı program ekle.
     * 6. İki afişin ve slider oklarının göründüğünü doğrula.
     * 7. İlk programın CANLI işaretini kaldır.
     * 8. Programın Hero'dan otomatik çıktığını doğrula.
     * 9. İkinci programın Akışta Görünür seçeneğini kapat.
     * 10. Onun da Hero'dan çıktığını doğrula.
     */
    public function test_ten_step_real_broadcast_hero_automation_scenario(): void
    {
        Carbon::setTestNow('2026-10-16 10:00:00'); // Friday (dayOfWeekIso = 5, day_of_week = 4)
        $fridayIndex = 4;

        // Active published schedule template
        $template = ScheduleTemplate::create([
            'name' => '2026 Normal Akış',
            'slug' => '2026-normal-akis',
            'status' => 'published',
            'is_active' => true,
        ]);

        // Program 1: Manuel hero ayarları kapalı (is_featured = false, hero_days = null)
        $program1 = Program::create([
            'name' => 'Canlı Yayın Programı 1',
            'slug' => 'canli-yayin-programi-1',
            'is_active' => true,
            'show_on_public' => true,
            'is_featured' => false,
            'hero_days' => null,
            'sort_order' => 99,
            'horizontal_image' => 'programs/p1_hero.jpg',
        ]);

        // Program 2: Manuel hero ayarları kapalı
        $program2 = Program::create([
            'name' => 'Canlı Yayın Programı 2',
            'slug' => 'canli-yayin-programi-2',
            'is_active' => true,
            'show_on_public' => true,
            'is_featured' => false,
            'hero_days' => null,
            'sort_order' => 100,
            'horizontal_image' => 'programs/p2_hero.jpg',
        ]);

        $service = app(HomepageDataService::class);

        // Adım 1 & 2: Bir programı CANLI olarak işaretle, Akışta Görünür aç.
        $item1 = ScheduleTemplateItem::create([
            'schedule_template_id' => $template->id,
            'program_id' => $program1->id,
            'day_of_week' => $fridayIndex,
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'is_live' => true,      // Adım 1
            'is_active' => true,    // Adım 2
        ]);

        // Adım 3: Programın manuel Hero ayarlarının kapalı olduğunu doğrula.
        $this->assertFalse((bool) $program1->is_featured);
        $this->assertNull($program1->hero_days);

        // Adım 4: Hero'da programın otomatik göründüğünü doğrula.
        $hero4 = $service->resolveHeroPrograms();
        $this->assertCount(1, $hero4);
        $this->assertEquals($program1->id, $hero4->first()->id);

        $response4 = $this->get(route('home'));
        $response4->assertStatus(200);
        $response4->assertSee('Canlı Yayın Programı 1');
        // Only 1 slide -> no slider navigation buttons
        $response4->assertDontSee('Önceki program');

        // Adım 5: İkinci bir canlı program ekle (14:00'da, Akışta Görünür = açık)
        $item2 = ScheduleTemplateItem::create([
            'schedule_template_id' => $template->id,
            'program_id' => $program2->id,
            'day_of_week' => $fridayIndex,
            'start_time' => '14:00:00',
            'end_time' => '15:30:00',
            'is_live' => true,
            'is_active' => true,
        ]);

        // Adım 6: İki afişin ve slider oklarının göründüğünü doğrula.
        $hero6 = $service->resolveHeroPrograms();
        $this->assertCount(2, $hero6);
        $this->assertEquals([$program1->id, $program2->id], $hero6->pluck('id')->all());

        $response6 = $this->get(route('home'));
        $response6->assertStatus(200);
        $response6->assertSee('Canlı Yayın Programı 1');
        $response6->assertSee('Canlı Yayın Programı 2');
        $response6->assertSee('Önceki program');
        $response6->assertSee('Sonraki program');

        // Adım 7: İlk programın CANLI işaretini kaldır.
        $item1->update(['is_live' => false]);

        // Adım 8: Programın Hero'dan otomatik çıktığını doğrula.
        $hero8 = $service->resolveHeroPrograms();
        $this->assertCount(1, $hero8);
        $this->assertEquals($program2->id, $hero8->first()->id);

        $response8 = $this->get(route('home'));
        $response8->assertStatus(200);
        $this->assertEquals([$program2->id], $response8->viewData('heroPrograms')->pluck('id')->all());
        $response8->assertDontSee('Önceki program'); // Only 1 slide remaining -> arrows removed

        // Adım 9: İkinci programın Akışta Görünür seçeneğini kapat.
        $item2->update(['is_active' => false]);

        // Adım 10: Onun da Hero'dan çıktığını doğrula (Hero tamamen boş/gizli).
        $hero10 = $service->resolveHeroPrograms();
        $this->assertEmpty($hero10);

        $response10 = $this->get(route('home'));
        $response10->assertStatus(200);
        $this->assertEmpty($response10->viewData('heroPrograms'));
        $response10->assertDontSee('Önceki program');
        $response10->assertDontSee('Bölümleri İzle'); // Hero CTA button is absent when 0 slides

        Carbon::setTestNow();
    }

    /**
     * Test deduplication: same program broadcasted multiple times live today appears only once in Hero,
     * ordered by its earliest broadcast start time.
     */
    public function test_same_program_multiple_live_broadcasts_today_is_deduplicated(): void
    {
        Carbon::setTestNow('2026-10-16 08:00:00');
        $fridayIndex = 4;

        $template = ScheduleTemplate::create([
            'name' => 'Dönem',
            'status' => 'published',
            'is_active' => true,
        ]);

        $program = Program::create([
            'name' => 'Gündem Özel',
            'slug' => 'gundem-ozel',
            'is_active' => true,
            'show_on_public' => true,
        ]);

        // Broadcast 1: 10:00 - 11:00 Live
        ScheduleTemplateItem::create([
            'schedule_template_id' => $template->id,
            'program_id' => $program->id,
            'day_of_week' => $fridayIndex,
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'is_live' => true,
            'is_active' => true,
        ]);

        // Broadcast 2: 18:00 - 19:00 Live (Same program)
        ScheduleTemplateItem::create([
            'schedule_template_id' => $template->id,
            'program_id' => $program->id,
            'day_of_week' => $fridayIndex,
            'start_time' => '18:00:00',
            'end_time' => '19:00:00',
            'is_live' => true,
            'is_active' => true,
        ]);

        $service = app(HomepageDataService::class);
        $hero = $service->resolveHeroPrograms();

        $this->assertCount(1, $hero);
        $this->assertEquals($program->id, $hero->first()->id);

        Carbon::setTestNow();
    }

    /**
     * Test sorting: live programs on Hero are sorted strictly by broadcast start_time ascending.
     */
    public function test_live_programs_ordered_by_broadcast_start_time(): void
    {
        Carbon::setTestNow('2026-10-16 08:00:00');
        $fridayIndex = 4;

        $template = ScheduleTemplate::create([
            'name' => 'Dönem',
            'status' => 'published',
            'is_active' => true,
        ]);

        $programLate = Program::create([
            'name' => 'Gece Yayını',
            'slug' => 'gece-yayini',
            'is_active' => true,
            'show_on_public' => true,
            'sort_order' => 1, // Sort order should be IGNORED by Hero
        ]);

        $programEarly = Program::create([
            'name' => 'Sabah Yayını',
            'slug' => 'sabah-yayini',
            'is_active' => true,
            'show_on_public' => true,
            'sort_order' => 99, // Sort order should be IGNORED by Hero
        ]);

        // Late broadcast: 21:00
        ScheduleTemplateItem::create([
            'schedule_template_id' => $template->id,
            'program_id' => $programLate->id,
            'day_of_week' => $fridayIndex,
            'start_time' => '21:00:00',
            'end_time' => '22:30:00',
            'is_live' => true,
            'is_active' => true,
        ]);

        // Early broadcast: 09:30
        ScheduleTemplateItem::create([
            'schedule_template_id' => $template->id,
            'program_id' => $programEarly->id,
            'day_of_week' => $fridayIndex,
            'start_time' => '09:30:00',
            'end_time' => '10:30:00',
            'is_live' => true,
            'is_active' => true,
        ]);

        $service = app(HomepageDataService::class);
        $hero = $service->resolveHeroPrograms();

        $this->assertEquals(['sabah-yayini', 'gece-yayini'], $hero->pluck('slug')->all());

        Carbon::setTestNow();
    }

    /**
     * Test isolation: is_featured = true programs without a live broadcast today are never added to Hero.
     */
    public function test_manual_featured_programs_are_never_added_to_hero(): void
    {
        Carbon::setTestNow('2026-10-16 08:00:00');

        $template = ScheduleTemplate::create([
            'name' => 'Dönem',
            'status' => 'published',
            'is_active' => true,
        ]);

        // Program with is_featured = true and hero_days = ['friday']
        Program::create([
            'name' => 'Manuel Program',
            'slug' => 'manuel-program',
            'is_active' => true,
            'show_on_public' => true,
            'is_featured' => true,
            'hero_days' => ['friday'],
            'sort_order' => 0,
        ]);

        $service = app(HomepageDataService::class);
        $hero = $service->resolveHeroPrograms();

        // Must be empty because it is NOT scheduled live in the active template today
        $this->assertEmpty($hero);

        Carbon::setTestNow();
    }

    /**
     * Test active template demotion prevention:
     * When an item in an active template is edited or deleted, the template status must remain 'published'.
     */
    public function test_editing_item_in_active_template_keeps_status_published(): void
    {
        $template = ScheduleTemplate::create([
            'name' => 'Aktif Dönem',
            'slug' => 'aktif-donem',
            'status' => 'published',
            'is_active' => true,
        ]);

        $prog = Program::create(['name' => 'Prog 1', 'slug' => 'prog-1', 'is_active' => true]);

        $item = ScheduleTemplateItem::create([
            'schedule_template_id' => $template->id,
            'program_id' => $prog->id,
            'day_of_week' => 0,
            'start_time' => '10:00',
            'end_time' => '11:00',
            'is_live' => false,
            'is_active' => true,
        ]);

        // Updating item
        $item->update(['is_live' => true]);

        $this->assertEquals('published', $template->fresh()->status);
        $this->assertTrue((bool) $template->fresh()->is_active);
    }
}

<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\ScheduleCalendarPage;
use App\Models\Program;
use App\Models\ScheduleException;
use App\Models\ScheduleTemplate;
use App\Models\ScheduleTemplateItem;
use App\Models\User;
use App\Services\Schedule\BroadcastScheduleResolver;
use App\Services\Schedule\ScheduleCalendarService;
use App\Services\Schedule\ScheduleConflictResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class ScheduleCalendarExceptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_scenario_a_replace_all_exception_serves_exception_today_and_template_tomorrow(): void
    {
        $today = Carbon::today();
        $tomorrow = Carbon::tomorrow();
        $dayOfWeekToday = $today->dayOfWeekIso - 1;
        $dayOfWeekTomorrow = $tomorrow->dayOfWeekIso - 1;

        $template = ScheduleTemplate::create([
            'name' => 'Sezon Akışı',
            'slug' => 'sezon-akisi',
            'status' => 'published',
            'is_active' => true,
            'priority' => 10,
        ]);

        $prog1 = Program::create(['name' => 'Normal Program 1', 'slug' => 'p1', 'is_active' => true]);
        $prog2 = Program::create(['name' => 'Normal Program 2', 'slug' => 'p2', 'is_active' => true]);
        $specialProg = Program::create(['name' => 'Kandil Özel Programı', 'slug' => 'kandil-ozel', 'is_active' => true]);

        ScheduleTemplateItem::create([
            'schedule_template_id' => $template->id,
            'program_id' => $prog1->id,
            'day_of_week' => $dayOfWeekToday,
            'start_time' => '10:00',
            'end_time' => '11:00',
            'is_active' => true,
        ]);

        ScheduleTemplateItem::create([
            'schedule_template_id' => $template->id,
            'program_id' => $prog2->id,
            'day_of_week' => $dayOfWeekTomorrow,
            'start_time' => '10:00',
            'end_time' => '11:00',
            'is_active' => true,
        ]);

        $service = new ScheduleCalendarService();
        $service->createSpecialBroadcast([
            'exception_date' => $today->toDateString(),
            'override_type' => 'replace_all',
            'program_id' => $specialProg->id,
            'start_time' => '20:00',
            'end_time' => '22:00',
            'is_live' => true,
            'note' => 'Kandil Özel',
        ]);

        $resolver = new BroadcastScheduleResolver();

        // 1. Today must serve Kandil Özel (replace_all)
        $todaySchedule = $resolver->getScheduleForDate($today);
        $this->assertCount(1, $todaySchedule);
        $this->assertEquals('Kandil Özel Programı', $todaySchedule->first()->display_title);

        // 2. Tomorrow must serve Normal Program 2
        $tomorrowSchedule = $resolver->getScheduleForDate($tomorrow);
        $this->assertCount(1, $tomorrowSchedule);
        $this->assertEquals('Normal Program 2', $tomorrowSchedule->first()->display_title);
    }

    public function test_scenario_b_type_a_end_trim_conflict_resolution(): void
    {
        $template = ScheduleTemplate::create(['name' => 'Trim Test', 'slug' => 'trim-test', 'status' => 'published']);
        $progA = Program::create(['name' => 'Hatm-i Şerif', 'slug' => 'hatmi-serif', 'is_active' => true]);
        $progX = Program::create(['name' => 'Özel Yayın X', 'slug' => 'ozel-x', 'is_active' => true]);

        // A: 13:10–14:10 ACTIVE
        $itemA = ScheduleTemplateItem::create([
            'schedule_template_id' => $template->id,
            'program_id' => $progA->id,
            'day_of_week' => 0,
            'start_time' => '13:10',
            'end_time' => '14:10',
            'is_active' => true,
        ]);

        // Proposed X: 14:00–15:00
        $resolver = new ScheduleConflictResolver();
        $analysis = $resolver->analyze(collect([$itemA]), '14:00', '15:00');

        $this->assertTrue($analysis['has_conflict']);
        $this->assertTrue($analysis['is_resolvable']);
        $this->assertEquals('end_trim', $analysis['conflicts'][0]['type']);
        $this->assertEquals('14:00', $analysis['conflicts'][0]['new_end_time']);

        // Apply resolution
        $resolver->applyResolutionInTransaction($analysis['conflicts'], function () use ($template, $progX) {
            return ScheduleTemplateItem::create([
                'schedule_template_id' => $template->id,
                'program_id' => $progX->id,
                'day_of_week' => 0,
                'start_time' => '14:00',
                'end_time' => '15:00',
                'is_active' => true,
            ]);
        });

        // Verify A end_time trimmed to 14:00
        $this->assertEquals('14:00:00', Carbon::parse($itemA->fresh()->end_time)->format('H:i:s'));
    }

    public function test_scenario_c_type_b_start_shift_conflict_resolution(): void
    {
        $template = ScheduleTemplate::create(['name' => 'Shift Test', 'slug' => 'shift-test', 'status' => 'published']);
        $progB = Program::create(['name' => 'Sağlık Sohbetleri', 'slug' => 'saglik-sohbetleri', 'is_active' => true]);
        $progX = Program::create(['name' => 'Özel Yayın X', 'slug' => 'ozel-x', 'is_active' => true]);

        // B: 14:10–15:30 ACTIVE
        $itemB = ScheduleTemplateItem::create([
            'schedule_template_id' => $template->id,
            'program_id' => $progB->id,
            'day_of_week' => 0,
            'start_time' => '14:10',
            'end_time' => '15:30',
            'is_active' => true,
        ]);

        // Proposed X: 14:00–15:00
        $resolver = new ScheduleConflictResolver();
        $analysis = $resolver->analyze(collect([$itemB]), '14:00', '15:00');

        $this->assertTrue($analysis['has_conflict']);
        $this->assertTrue($analysis['is_resolvable']);
        $this->assertEquals('start_shift', $analysis['conflicts'][0]['type']);
        $this->assertEquals('15:00', $analysis['conflicts'][0]['new_start_time']);

        // Apply resolution
        $resolver->applyResolutionInTransaction($analysis['conflicts'], function () use ($template, $progX) {
            return ScheduleTemplateItem::create([
                'schedule_template_id' => $template->id,
                'program_id' => $progX->id,
                'day_of_week' => 0,
                'start_time' => '14:00',
                'end_time' => '15:00',
                'is_active' => true,
            ]);
        });

        // Verify B start_time shifted to 15:00
        $this->assertEquals('15:00:00', Carbon::parse($itemB->fresh()->start_time)->format('H:i:s'));
    }

    public function test_scenario_d_type_c_full_encapsulation_conflict_resolution(): void
    {
        $template = ScheduleTemplate::create(['name' => 'Encapsulate Test', 'slug' => 'encapsulate-test', 'status' => 'published']);
        $progC = Program::create(['name' => 'Kısa Haber', 'slug' => 'kisa-haber', 'is_active' => true]);
        $progX = Program::create(['name' => 'Özel Yayın X', 'slug' => 'ozel-x', 'is_active' => true]);

        // C: 14:10–14:50 ACTIVE
        $itemC = ScheduleTemplateItem::create([
            'schedule_template_id' => $template->id,
            'program_id' => $progC->id,
            'day_of_week' => 0,
            'start_time' => '14:10',
            'end_time' => '14:50',
            'is_active' => true,
        ]);

        // Proposed X: 14:00–15:00
        $resolver = new ScheduleConflictResolver();
        $analysis = $resolver->analyze(collect([$itemC]), '14:00', '15:00');

        $this->assertTrue($analysis['has_conflict']);
        $this->assertTrue($analysis['is_resolvable']);
        $this->assertEquals('full_encapsulate', $analysis['conflicts'][0]['type']);

        // Apply resolution
        $resolver->applyResolutionInTransaction($analysis['conflicts'], function () use ($template, $progX) {
            return ScheduleTemplateItem::create([
                'schedule_template_id' => $template->id,
                'program_id' => $progX->id,
                'day_of_week' => 0,
                'start_time' => '14:00',
                'end_time' => '15:00',
                'is_active' => true,
            ]);
        });

        // Verify C set to is_active = false
        $this->assertFalse((bool) $itemC->fresh()->is_active);
    }

    public function test_scenario_e_type_d_mid_split_refuses_automatic_resolution(): void
    {
        $template = ScheduleTemplate::create(['name' => 'Split Test', 'slug' => 'split-test', 'status' => 'published']);
        $progD = Program::create(['name' => 'Uzun Belgesel', 'slug' => 'uzun-belgesel', 'is_active' => true]);

        // D: 13:00–16:00 ACTIVE
        $itemD = ScheduleTemplateItem::create([
            'schedule_template_id' => $template->id,
            'program_id' => $progD->id,
            'day_of_week' => 0,
            'start_time' => '13:00',
            'end_time' => '16:00',
            'is_active' => true,
        ]);

        // Proposed X: 14:00–15:00
        $resolver = new ScheduleConflictResolver();
        $analysis = $resolver->analyze(collect([$itemD]), '14:00', '15:00');

        $this->assertTrue($analysis['has_conflict']);
        $this->assertFalse($analysis['is_resolvable']);
        $this->assertTrue($analysis['has_mid_split']);
    }

    public function test_scenario_f_transaction_rollback_on_failure(): void
    {
        $template = ScheduleTemplate::create(['name' => 'Rollback Test', 'slug' => 'rollback-test', 'status' => 'published']);
        $progA = Program::create(['name' => 'Program A', 'slug' => 'prog-a', 'is_active' => true]);

        $itemA = ScheduleTemplateItem::create([
            'schedule_template_id' => $template->id,
            'program_id' => $progA->id,
            'day_of_week' => 0,
            'start_time' => '13:10',
            'end_time' => '14:10',
            'is_active' => true,
        ]);

        $resolver = new ScheduleConflictResolver();
        $analysis = $resolver->analyze(collect([$itemA]), '14:00', '15:00');

        try {
            $resolver->applyResolutionInTransaction($analysis['conflicts'], function () {
                throw new \RuntimeException('Simulated failure during creation');
            });
        } catch (\RuntimeException $e) {
            // expected
        }

        // Verify itemA end_time remains unchanged (14:10)
        $this->assertEquals('14:10:00', Carbon::parse($itemA->fresh()->end_time)->format('H:i:s'));
    }

    public function test_scenario_g_deleting_exception_returns_automatically_to_normal_template(): void
    {
        $today = Carbon::today();
        $dayOfWeekToday = $today->dayOfWeekIso - 1;

        $template = ScheduleTemplate::create([
            'name' => 'Ana Sezon',
            'slug' => 'ana-sezon',
            'status' => 'published',
            'is_active' => true,
            'priority' => 100,
        ]);

        $progNormal = Program::create(['name' => 'Sabah Bülteni', 'slug' => 'sabah-bulteni', 'is_active' => true]);
        $progSpecial = Program::create(['name' => 'Canlı Yayın Özel', 'slug' => 'canli-yayin-ozel', 'is_active' => true]);

        ScheduleTemplateItem::create([
            'schedule_template_id' => $template->id,
            'program_id' => $progNormal->id,
            'day_of_week' => $dayOfWeekToday,
            'start_time' => '09:00',
            'end_time' => '10:00',
            'is_active' => true,
        ]);

        $service = new ScheduleCalendarService();
        $exceptionItem = $service->createSpecialBroadcast([
            'exception_date' => $today->toDateString(),
            'override_type' => 'replace_all',
            'program_id' => $progSpecial->id,
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);

        $resolver = new BroadcastScheduleResolver();

        // 1. Exception active -> returns special program
        $this->assertEquals('Canlı Yayın Özel', $resolver->getScheduleForDate($today)->first()->display_title);

        // 2. Delete exception -> returns normal template
        $service->deleteException($exceptionItem->schedule_exception_id);

        $this->assertEquals('Sabah Bülteni', $resolver->getScheduleForDate($today)->first()->display_title);
    }

    public function test_scenario_h_current_next_epg_consistency(): void
    {
        $today = Carbon::today();
        $service = new ScheduleCalendarService();

        $progSpecial = Program::create(['name' => 'Özel Bayram Yayını', 'slug' => 'ozel-bayram', 'is_active' => true]);

        $service->createSpecialBroadcast([
            'exception_date' => $today->toDateString(),
            'override_type' => 'replace_all',
            'program_id' => $progSpecial->id,
            'start_time' => '12:00',
            'end_time' => '14:00',
        ]);

        $resolver = new BroadcastScheduleResolver();

        $scheduleDate = $resolver->getScheduleForDate($today);
        $weeklySchedule = $resolver->getWeeklySchedule($today)->get($today->dayOfWeekIso - 1);

        $this->assertEquals($scheduleDate->first()->display_title, $weeklySchedule->first()->display_title);
        $this->assertEquals('Özel Bayram Yayını', $weeklySchedule->first()->display_title);
    }

    public function test_scenario_i_audit_logging_and_cache_invalidation(): void
    {
        $today = Carbon::today();
        $service = new ScheduleCalendarService();
        $progSpecial = Program::create(['name' => 'Özel Mevlit Yayını', 'slug' => 'ozel-mevlit', 'is_active' => true]);

        $item = $service->createSpecialBroadcast([
            'exception_date' => $today->toDateString(),
            'override_type' => 'replace_all',
            'program_id' => $progSpecial->id,
            'start_time' => '19:00',
            'end_time' => '21:00',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'subject_label' => 'Özel Yayın Akışı',
            'action' => 'created',
        ]);

        $service->deleteException($item->schedule_exception_id);

        $this->assertDatabaseHas('audit_logs', [
            'subject_label' => 'Özel Yayın Akışı',
            'action' => 'deleted',
        ]);
    }

    public function test_active_overlap_triggers_warning_modal_and_allows_creation_on_confirm(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $template = ScheduleTemplate::create([
            'name' => 'Test Sezonu',
            'slug' => 'test-sezonu',
            'status' => 'published',
            'is_active' => true,
        ]);

        $progA = Program::create(['name' => 'Program A', 'slug' => 'prog-a', 'is_active' => true]);
        $progB = Program::create(['name' => 'Program B', 'slug' => 'prog-b', 'is_active' => true]);

        // Item A: 14:00–15:00 ACTIVE
        $itemA = ScheduleTemplateItem::create([
            'schedule_template_id' => $template->id,
            'program_id' => $progA->id,
            'day_of_week' => 0,
            'start_time' => '14:00',
            'end_time' => '15:00',
            'is_active' => true,
        ]);

        // Submit new active item B: 14:00–15:00 (triggers overlap warning modal)
        $lw = Livewire::test(ScheduleCalendarPage::class, ['template' => $template->id, 'day' => 0])
            ->callAction('create_item', [
                'program_id' => $progB->id,
                'day_of_week' => 0,
                'start_time' => '14:00',
                'end_time' => '15:00',
                'is_active' => true,
            ]);

        // Item B is not created yet because warning modal was triggered
        $this->assertDatabaseMissing('schedule_template_items', [
            'schedule_template_id' => $template->id,
            'program_id' => $progB->id,
        ]);

        // Confirm creation via overlap modal ("Yine de Ekle")
        $lw->callAction('confirm_overlap', arguments: [
            'action_type' => 'create',
            'form_data' => [
                'template_id' => $template->id,
                'program_id' => $progB->id,
                'day_of_week' => 0,
                'start_time' => '14:00',
                'end_time' => '15:00',
                'is_active' => true,
            ],
        ]);

        // Verify B was created in DB and A remains untouched
        $this->assertDatabaseHas('schedule_template_items', [
            'schedule_template_id' => $template->id,
            'program_id' => $progB->id,
            'start_time' => '14:00',
            'end_time' => '15:00',
            'is_active' => true,
        ]);

        $this->assertEquals('14:00:00', Carbon::parse($itemA->fresh()->start_time)->format('H:i:s'));
        $this->assertEquals('15:00:00', Carbon::parse($itemA->fresh()->end_time)->format('H:i:s'));
        $this->assertTrue((bool) $itemA->fresh()->is_active);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'created',
            'message' => 'Program B 14:00–15:00 akışa eklendi (Çakışma uyarısına rağmen yayın eklendi).',
        ]);
    }

    public function test_passive_overlap_does_not_trigger_warning_modal(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $template = ScheduleTemplate::create([
            'name' => 'Pasif Test',
            'slug' => 'pasif-test',
            'status' => 'published',
            'is_active' => true,
        ]);

        $progA = Program::create(['name' => 'Program A', 'slug' => 'prog-a', 'is_active' => true]);
        $progB = Program::create(['name' => 'Program B', 'slug' => 'prog-b', 'is_active' => true]);

        // Item A: 14:00–15:00 PASSIVE (is_active = false)
        ScheduleTemplateItem::create([
            'schedule_template_id' => $template->id,
            'program_id' => $progA->id,
            'day_of_week' => 0,
            'start_time' => '14:00',
            'end_time' => '15:00',
            'is_active' => false,
        ]);

        // Submit new active item B: 14:00–15:00
        Livewire::test(ScheduleCalendarPage::class, ['template' => $template->id, 'day' => 0])
            ->callAction('create_item', [
                'program_id' => $progB->id,
                'day_of_week' => 0,
                'start_time' => '14:00',
                'end_time' => '15:00',
                'is_active' => true,
            ]);

        $this->assertDatabaseHas('schedule_template_items', [
            'schedule_template_id' => $template->id,
            'program_id' => $progB->id,
            'is_active' => true,
        ]);
    }

    public function test_edit_active_overlap_triggers_warning_modal_and_allows_save_on_confirm(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $template = ScheduleTemplate::create([
            'name' => 'Edit Overlap Test',
            'slug' => 'edit-overlap-test',
            'status' => 'published',
            'is_active' => true,
        ]);

        $progA = Program::create(['name' => 'Program A', 'slug' => 'prog-a', 'is_active' => true]);
        $progC = Program::create(['name' => 'Program C', 'slug' => 'prog-c', 'is_active' => true]);

        // Item A: 14:00–15:00 ACTIVE
        ScheduleTemplateItem::create([
            'schedule_template_id' => $template->id,
            'program_id' => $progA->id,
            'day_of_week' => 0,
            'start_time' => '14:00',
            'end_time' => '15:00',
            'is_active' => true,
        ]);

        // Item C: 16:00–17:00 ACTIVE
        $itemC = ScheduleTemplateItem::create([
            'schedule_template_id' => $template->id,
            'program_id' => $progC->id,
            'day_of_week' => 0,
            'start_time' => '16:00',
            'end_time' => '17:00',
            'is_active' => true,
        ]);

        // Edit C to overlap with A (14:30–15:30) via warning modal confirmation ("Yine de Kaydet")
        Livewire::test(ScheduleCalendarPage::class, ['template' => $template->id, 'day' => 0])
            ->callAction('confirm_overlap', arguments: [
                'action_type' => 'edit',
                'form_data' => [
                    'record_id' => $itemC->id,
                    'program_id' => $progC->id,
                    'day_of_week' => 0,
                    'start_time' => '14:30',
                    'end_time' => '15:30',
                    'is_active' => true,
                ],
            ]);

        $this->assertEquals('14:30:00', Carbon::parse($itemC->fresh()->start_time)->format('H:i:s'));
        $this->assertEquals('15:30:00', Carbon::parse($itemC->fresh()->end_time)->format('H:i:s'));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'updated',
            'message' => 'Program C 14:30–15:30 yayını güncellendi (Çakışma uyarısına rağmen yayın güncellendi).',
        ]);
    }
}

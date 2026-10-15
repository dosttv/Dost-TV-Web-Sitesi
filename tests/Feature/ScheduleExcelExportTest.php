<?php

namespace Tests\Feature;

use App\Models\Program;
use App\Models\ScheduleTemplate;
use App\Models\ScheduleTemplateItem;
use App\Models\User;
use App\Services\Schedule\ScheduleExcelExportService;
use App\Filament\Pages\ScheduleCalendarPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ScheduleExcelExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_service_generates_valid_two_sheet_excel_for_selected_template(): void
    {
        // Setup selected template A
        $templateA = ScheduleTemplate::create([
            'name' => '2026 Yaz Akışı',
            'slug' => '2026-yaz-akisi',
            'status' => 'published',
            'is_active' => true,
        ]);

        // Setup other template B
        $templateB = ScheduleTemplate::create([
            'name' => '2026 Kış Akışı',
            'slug' => '2026-kis-akisi',
            'status' => 'draft',
            'is_active' => false,
        ]);

        $prog1 = Program::create(['name' => 'Hatm-i Şerif', 'slug' => 'hatm-i-serif', 'is_active' => true]);
        $prog2 = Program::create(['name' => 'Sağlık Sohbetleri', 'slug' => 'saglik-sohbetleri', 'is_active' => true]);

        // Items for Template A
        ScheduleTemplateItem::create([
            'schedule_template_id' => $templateA->id,
            'program_id' => $prog1->id,
            'day_of_week' => 0, // Pazartesi
            'start_time' => '00:00',
            'end_time' => '01:00',
            'is_live' => false,
            'is_repeat' => false,
            'is_package' => false,
            'is_active' => true,
        ]);

        ScheduleTemplateItem::create([
            'schedule_template_id' => $templateA->id,
            'program_id' => $prog2->id,
            'day_of_week' => 1, // Salı
            'start_time' => '14:10',
            'end_time' => '15:30',
            'custom_title' => 'Özel Sağlık',
            'is_live' => false,
            'is_repeat' => true,
            'is_package' => false,
            'is_active' => true,
        ]);

        // Item for Template B (should NOT be in export)
        ScheduleTemplateItem::create([
            'schedule_template_id' => $templateB->id,
            'program_id' => $prog1->id,
            'day_of_week' => 2,
            'start_time' => '10:00',
            'end_time' => '11:00',
            'is_active' => true,
        ]);

        $exportService = app(ScheduleExcelExportService::class);
        $spreadsheet = $exportService->createSpreadsheet($templateA, includePassive: false);

        // Assert 2 sheets exist
        $this->assertEquals(2, $spreadsheet->getSheetCount());
        $this->assertEquals('Haftalık Yayın Akışı', $spreadsheet->getSheet(0)->getTitle());
        $this->assertEquals('Detaylı Akış', $spreadsheet->getSheet(1)->getTitle());

        // Assert Sheet 1 (Haftalık Yayın Akışı)
        $sheet1 = $spreadsheet->getSheet(0);
        $this->assertStringContainsString('DOST TV YAYIN AKIŞI — 2026 YAZ AKIŞI', $sheet1->getCell('A1')->getValue());
        $this->assertEquals('Saat', $sheet1->getCell('A3')->getValue());
        $this->assertEquals('Pazartesi', $sheet1->getCell('B3')->getValue());
        $this->assertEquals('Salı', $sheet1->getCell('C3')->getValue());
        $this->assertEquals('Pazar', $sheet1->getCell('H3')->getValue());

        // Check program placement in matrix
        $this->assertStringContainsString('Hatm-i Şerif', (string) $sheet1->getCell('B4')->getValue());
        $this->assertStringNotContainsString('2026 Kış Akışı', (string) $sheet1->getCell('A1')->getValue());

        // Assert Sheet 2 (Detaylı Akış)
        $sheet2 = $spreadsheet->getSheet(1);
        $this->assertEquals('Gün', $sheet2->getCell('A1')->getValue());
        $this->assertEquals('Başlangıç', $sheet2->getCell('B1')->getValue());
        $this->assertEquals('Bitiş', $sheet2->getCell('C1')->getValue());
        $this->assertEquals('Program', $sheet2->getCell('D1')->getValue());
        $this->assertEquals('Özel Başlık', $sheet2->getCell('E1')->getValue());
        $this->assertEquals('Yayın Türü', $sheet2->getCell('F1')->getValue());
        $this->assertEquals('Canlı', $sheet2->getCell('G1')->getValue());
        $this->assertEquals('Tekrar', $sheet2->getCell('H1')->getValue());
        $this->assertEquals('Paket', $sheet2->getCell('I1')->getValue());
        $this->assertEquals('Akışta Görünür', $sheet2->getCell('J1')->getValue());

        // Data rows in Sheet 2
        $this->assertEquals('Pazartesi', $sheet2->getCell('A2')->getValue());
        $this->assertEquals('00:00', $sheet2->getCell('B2')->getValue());
        $this->assertEquals('Hatm-i Şerif', $sheet2->getCell('D2')->getValue());

        $this->assertEquals('Salı', $sheet2->getCell('A3')->getValue());
        $this->assertEquals('14:10', $sheet2->getCell('B3')->getValue());
        $this->assertEquals('Sağlık Sohbetleri', $sheet2->getCell('D3')->getValue());
        $this->assertEquals('Özel Sağlık', $sheet2->getCell('E3')->getValue());
        $this->assertEquals('TEKRAR', $sheet2->getCell('F3')->getValue());
        $this->assertEquals('Evet', $sheet2->getCell('H3')->getValue());
    }

    public function test_passive_items_inclusion_option(): void
    {
        $template = ScheduleTemplate::create([
            'name' => 'Pasif Test Akışı',
            'slug' => 'pasif-test-akisi',
            'status' => 'published',
            'is_active' => true,
        ]);

        $prog = Program::create(['name' => 'Test Program', 'slug' => 'test-prog', 'is_active' => true]);

        // Active item
        ScheduleTemplateItem::create([
            'schedule_template_id' => $template->id,
            'program_id' => $prog->id,
            'day_of_week' => 0,
            'start_time' => '10:00',
            'end_time' => '11:00',
            'is_active' => true,
        ]);

        // Passive item
        ScheduleTemplateItem::create([
            'schedule_template_id' => $template->id,
            'program_id' => $prog->id,
            'day_of_week' => 0,
            'start_time' => '11:00',
            'end_time' => '12:00',
            'is_active' => false,
        ]);

        $exportService = app(ScheduleExcelExportService::class);

        // When includePassive is FALSE
        $ssExclude = $exportService->createSpreadsheet($template, includePassive: false);
        $sheet2Exclude = $ssExclude->getSheet(1);
        $this->assertEquals('10:00', $sheet2Exclude->getCell('B2')->getValue());
        $this->assertEmpty($sheet2Exclude->getCell('B3')->getValue());

        // When includePassive is TRUE
        $ssInclude = $exportService->createSpreadsheet($template, includePassive: true);
        $sheet2Include = $ssInclude->getSheet(1);
        $this->assertEquals('10:00', $sheet2Include->getCell('B2')->getValue());
        $this->assertEquals('Evet', $sheet2Include->getCell('J2')->getValue());
        $this->assertEquals('11:00', $sheet2Include->getCell('B3')->getValue());
        $this->assertEquals('Hayır', $sheet2Include->getCell('J3')->getValue());
    }

    public function test_livewire_export_action_triggers_file_download(): void
    {
        $user = User::factory()->create();

        $template = ScheduleTemplate::create([
            'name' => '2026 Bahar Akışı',
            'slug' => '2026-bahar-akisi',
            'status' => 'published',
            'is_active' => true,
        ]);

        Livewire::actingAs($user)
            ->test(ScheduleCalendarPage::class, ['selectedTemplateId' => $template->id])
            ->callAction('export_excel', [
                'include_passive' => false,
            ])
            ->assertFileDownloaded('dost-tv-2026-bahar-akisi-haftalik.xlsx');
    }

    public function test_export_excel_action_is_accessible_in_schedule_calendar_page(): void
    {
        $user = User::factory()->create();

        $template = ScheduleTemplate::create([
            'name' => '2026 Yaz Akışı',
            'slug' => '2026-yaz-akisi',
            'status' => 'published',
            'is_active' => true,
        ]);

        Livewire::actingAs($user)
            ->test(ScheduleCalendarPage::class, ['template' => $template->id])
            ->assertActionExists('export_excel')
            ->mountAction('export_excel')
            ->assertActionMounted('export_excel')
            ->setActionData([
                'include_passive' => true,
            ])
            ->callMountedAction()
            ->assertFileDownloaded('dost-tv-2026-yaz-akisi-haftalik.xlsx');
    }
}

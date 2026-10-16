<?php

namespace Tests\Feature;

use App\Models\Program;
use App\Models\Schedule;

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramHeroTextTest extends TestCase
{
    use RefreshDatabase;

    public function test_program_model_supports_hero_text_column(): void
    {
        $program = Program::create([
            'name' => 'Test Programı',
            'slug' => 'test-programi',
            'status' => 'active',
            'short_description' => 'Yayın akışı kısa tanımı',
            'hero_text' => 'Ana sayfa hero için kısa ve vurucu metin.',
            'description' => 'Detaylı genel program tanıtım metni.',
            'is_active' => true,
            'is_featured' => true,
            'show_on_public' => true,
        ]);

        $this->assertDatabaseHas('programs', [
            'id' => $program->id,
            'short_description' => 'Yayın akışı kısa tanımı',
            'hero_text' => 'Ana sayfa hero için kısa ve vurucu metin.',
            'description' => 'Detaylı genel program tanıtım metni.',
        ]);

        $this->assertEquals('Ana sayfa hero için kısa ve vurucu metin.', $program->fresh()->hero_text);
    }

    public function test_homepage_hero_renders_hero_text_exclusively_without_fallback_to_description(): void
    {
        $template = \App\Models\ScheduleTemplate::create([
            'name' => 'Aktif Şablon',
            'slug' => 'aktif-sablon',
            'status' => 'published',
            'is_active' => true,
        ]);

        $programWithHeroText = Program::create([
            'name' => 'Hero Metinli Program',
            'slug' => 'hero-metinli-program',
            'status' => 'active',
            'short_description' => 'Kısa Tanım 123',
            'hero_text' => 'Özel Ana Sayfa Hero Açıklaması',
            'description' => 'Genel Program Tanıtım Açıklaması Detayı',
            'is_active' => true,
            'show_on_public' => true,
        ]);

        \App\Models\ScheduleTemplateItem::create([
            'schedule_template_id' => $template->id,
            'program_id' => $programWithHeroText->id,
            'day_of_week' => now()->dayOfWeekIso - 1,
            'start_time' => '10:00',
            'end_time' => '11:00',
            'is_live' => true,
            'is_active' => true,
        ]);

        $response = $this->get(route('home'));
        $response->assertStatus(200);
        $response->assertSee('Özel Ana Sayfa Hero Açıklaması');
        $response->assertDontSee('Genel Program Tanıtım Açıklaması Detayı');
    }

    public function test_homepage_hero_does_not_render_description_block_when_hero_text_is_null(): void
    {
        $template = \App\Models\ScheduleTemplate::create([
            'name' => 'Aktif Şablon 2',
            'slug' => 'aktif-sablon-2',
            'status' => 'published',
            'is_active' => true,
        ]);

        $programWithoutHeroText = Program::create([
            'name' => 'Hero Metinsiz Program',
            'slug' => 'hero-metinsiz-program',
            'status' => 'active',
            'short_description' => 'Yayın Akışı Kısa Açıklaması',
            'hero_text' => null,
            'description' => 'Bu Genel Tanıtım Metni Hero İçinde Asla Görünmemelidir',
            'is_active' => true,
            'show_on_public' => true,
        ]);

        \App\Models\ScheduleTemplateItem::create([
            'schedule_template_id' => $template->id,
            'program_id' => $programWithoutHeroText->id,
            'day_of_week' => now()->dayOfWeekIso - 1,
            'start_time' => '10:00',
            'end_time' => '11:00',
            'is_live' => true,
            'is_active' => true,
        ]);

        $response = $this->get(route('home'));
        $response->assertStatus(200);
        $response->assertSee('Hero Metinsiz Program');
        $response->assertDontSee('Bu Genel Tanıtım Metni Hero İçinde Asla Görünmemelidir');
    }

    public function test_program_form_schema_contains_hero_text_field_with_proper_labels(): void
    {
        $schema = \App\Filament\Resources\Programs\Schemas\ProgramForm::configure(new \Filament\Schemas\Schema());
        $components = $schema->getComponents();

        $this->assertNotEmpty($components);
        
        $formContent = file_get_contents(app_path('Filament/Resources/Programs/Schemas/ProgramForm.php'));
        $this->assertStringContainsString("'hero_text'", $formContent);
        $this->assertStringContainsString("'Ana Sayfa Hero Metni'", $formContent);
        $this->assertStringContainsString("günün yayın akışında canlı yayın olarak Hero", $formContent);
    }

    public function test_schedule_and_description_fields_remain_isolated(): void
    {
        $program = Program::create([
            'name' => 'Çoklu Tanımlı Program',
            'slug' => 'coklu-tanimli-program',
            'status' => 'active',
            'short_description' => 'Sadece Yayın Akışında Görünür',
            'hero_text' => 'Sadece Ana Sayfa Heroda Görünür',
            'description' => 'Sadece Program Detayında Görünür',
            'is_active' => true,
            'is_featured' => true,
            'show_on_public' => true,
        ]);

        $this->assertEquals('Sadece Yayın Akışında Görünür', $program->short_description);
        $this->assertEquals('Sadece Ana Sayfa Heroda Görünür', $program->hero_text);
        $this->assertEquals('Sadece Program Detayında Görünür', $program->description);
    }

    public function test_program_form_supports_long_text_without_max_length_constraints(): void
    {
        $longShortDesc = str_repeat('Yayın akışı uzun açıklaması. ', 20); // > 500 chars
        $longHeroText = str_repeat('Ana sayfa hero uzun açıklaması. ', 30); // > 900 chars

        $program = Program::create([
            'name' => 'Uzun Metinli Program',
            'slug' => 'uzun-metinli-program',
            'status' => 'active',
            'short_description' => $longShortDesc,
            'hero_text' => $longHeroText,
            'description' => 'Genel detay metni.',
            'is_active' => true,
            'is_featured' => true,
            'show_on_public' => true,
        ]);

        $this->assertGreaterThan(500, strlen($program->fresh()->short_description));
        $this->assertGreaterThan(900, strlen($program->fresh()->hero_text));

        $formContent = file_get_contents(app_path('Filament/Resources/Programs/Schemas/ProgramForm.php'));
        $this->assertStringNotContainsString("->maxLength(160)", $formContent);
        $this->assertStringNotContainsString("->maxLength(300)", $formContent);
    }
}

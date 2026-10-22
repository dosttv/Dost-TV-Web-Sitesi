<?php

namespace Tests\Feature;

use App\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeroResponsiveImageFocusTest extends TestCase
{
    use RefreshDatabase;

    public function test_program_model_persists_mobile_hero_image(): void
    {
        $program = Program::create([
            'name' => 'Hikmet Arayışları',
            'slug' => 'hikmet-arayislari',
            'status' => 'active',
            'is_active' => true,
            'is_featured' => true,
            'horizontal_image' => 'programs/hikmet.jpg',
            'mobile_hero_image' => 'programs/hikmet-mobile.jpg',
        ]);

        $this->assertDatabaseHas('programs', [
            'id' => $program->id,
            'mobile_hero_image' => 'programs/hikmet-mobile.jpg',
        ]);

        $fresh = Program::find($program->id);
        $this->assertEquals('programs/hikmet-mobile.jpg', $fresh->mobile_hero_image);
    }

    public function test_hero_section_renders_picture_tag_with_mobile_hero_image(): void
    {
        $programWithMobile = Program::create([
            'name' => 'Program A',
            'slug' => 'program-a',
            'status' => 'active',
            'is_active' => true,
            'is_featured' => true,
            'horizontal_image' => 'programs/horizontal-a.jpg',
            'mobile_hero_image' => 'programs/mobile-a.jpg',
        ]);

        $programWithoutMobile = Program::create([
            'name' => 'Program B',
            'slug' => 'program-b',
            'status' => 'active',
            'is_active' => true,
            'is_featured' => true,
            'horizontal_image' => 'programs/horizontal-b.jpg',
            'mobile_hero_image' => null,
        ]);

        $view = $this->blade('<x-site.home.hero-section :heroPrograms="$heroPrograms" />', [
            'heroPrograms' => collect([$programWithMobile, $programWithoutMobile]),
        ]);

        $html = (string) $view;

        // Verify picture elements are rendered with media query for < 768px
        $this->assertStringContainsString('<picture', $html);
        $this->assertStringContainsString('media="(max-width: 767px)"', $html);

        // Mobile image for Program A (served as resized WebP variants)
        $this->assertStringContainsString('img/960/programs/mobile-a.jpg', $html);
        $this->assertStringContainsString('img/480/programs/mobile-a.jpg 480w', $html);

        // Fallback for Program B uses horizontal image
        $this->assertStringContainsString('img/1920/programs/horizontal-b.jpg', $html);
        $this->assertStringContainsString('img/640/programs/horizontal-b.jpg 640w', $html);

        // Object cover is used, no focus object-position CSS rules
        $this->assertStringContainsString('object-cover', $html);
        $this->assertStringNotContainsString('object-position:', $html);
        $this->assertStringNotContainsString('hero-focus-style', $html);
    }
}


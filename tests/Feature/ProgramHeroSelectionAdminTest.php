<?php

namespace Tests\Feature;

use App\Models\Program;
use App\Models\User;
use App\Services\Home\HomepageDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProgramHeroSelectionAdminTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'email' => 'admin@dosttv.com',
            'role' => 'super_admin',
            'is_active' => true,
        ]);
    }

    public function test_program_hero_selection_and_ordering_acceptance_flow(): void
    {
        $template = \App\Models\ScheduleTemplate::create([
            'name' => 'Aktif Şablon',
            'slug' => 'aktif-sablon',
            'status' => 'published',
            'is_active' => true,
        ]);

        $programA = Program::create([
            'name' => 'Program A',
            'slug' => 'program-a',
            'status' => 'active',
            'show_on_public' => true,
            'is_active' => true,
            'is_featured' => false, // Notice: is_featured is FALSE, but Hero includes it because it is scheduled LIVE!
        ]);

        $programB = Program::create([
            'name' => 'Program B',
            'slug' => 'program-b',
            'status' => 'active',
            'show_on_public' => true,
            'is_active' => true,
            'is_featured' => false,
        ]);

        $itemA = \App\Models\ScheduleTemplateItem::create([
            'schedule_template_id' => $template->id,
            'program_id' => $programA->id,
            'day_of_week' => now()->dayOfWeekIso - 1,
            'start_time' => '09:00',
            'end_time' => '10:00',
            'is_live' => true,
            'is_active' => true,
        ]);

        $itemB = \App\Models\ScheduleTemplateItem::create([
            'schedule_template_id' => $template->id,
            'program_id' => $programB->id,
            'day_of_week' => now()->dayOfWeekIso - 1,
            'start_time' => '14:00',
            'end_time' => '15:00',
            'is_live' => true,
            'is_active' => true,
        ]);

        $service = app(HomepageDataService::class);

        // Verify A appears before B in Hero based on broadcast start_time (09:00 vs 14:00)
        $heroPrograms = $service->getHomepageData()['heroPrograms'];
        $this->assertEquals(['Program A', 'Program B'], $heroPrograms->pluck('name')->all());

        // 2. Turning is_featured on or off does NOT affect Hero
        $programA->update(['is_featured' => true]);
        $heroProgramsWithFeatured = $service->getHomepageData()['heroPrograms'];
        $this->assertEquals(['Program A', 'Program B'], $heroProgramsWithFeatured->pluck('name')->all());

        // 3. Turn off is_live for item B in schedule -> B drops from Hero
        $itemB->update(['is_live' => false]);
        $heroProgramsAfterBNotLive = $service->getHomepageData()['heroPrograms'];
        $this->assertEquals(['Program A'], $heroProgramsAfterBNotLive->pluck('name')->all());

        // 4. Turn off show_on_public for Program A -> Hero becomes empty
        $programA->update(['show_on_public' => false]);
        $heroProgramsAfterAOff = $service->getHomepageData()['heroPrograms'];
        $this->assertEmpty($heroProgramsAfterAOff);
    }
}

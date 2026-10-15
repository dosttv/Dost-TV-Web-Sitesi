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
        $todayKey = strtolower(now()->format('l'));

        $programA = Program::create([
            'name' => 'Program A',
            'slug' => 'program-a',
            'status' => 'active',
            'show_on_public' => true,
            'is_featured' => true,
            'hero_days' => [$todayKey],
            'sort_order' => 0,
        ]);

        $programB = Program::create([
            'name' => 'Program B',
            'slug' => 'program-b',
            'status' => 'active',
            'show_on_public' => true,
            'is_featured' => true,
            'hero_days' => [$todayKey],
            'sort_order' => 1,
        ]);

        $service = app(HomepageDataService::class);

        // Verify A appears before B in Hero
        $heroPrograms = $service->getHomepageData()['heroPrograms'];
        $this->assertEquals(['Program A', 'Program B'], $heroPrograms->pluck('name')->all());

        // 2. Turn off is_featured for Program B
        $programB->update(['is_featured' => false]);
        $heroProgramsAfterBOff = $service->getHomepageData()['heroPrograms'];
        $this->assertEquals(['Program A'], $heroProgramsAfterBOff->pluck('name')->all());

        // 3. Turn off show_on_public for Program A
        $programA->update(['show_on_public' => false]);
        $heroProgramsAfterAOff = $service->getHomepageData()['heroPrograms'];
        $this->assertEmpty($heroProgramsAfterAOff);
    }
}

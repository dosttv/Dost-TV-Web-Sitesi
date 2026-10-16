<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MobileHamburgerMenuLogoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $menu = Menu::create([
            'name' => 'Ana Üst Menü',
            'location' => 'header_primary',
            'is_active' => true,
        ]);

        MenuItem::create([
            'menu_id' => $menu->id,
            'title' => 'Programlar',
            'slug' => 'programlar',
            'item_type' => 'program_mega_menu',
            'sort_order' => 0,
            'is_active' => true,
        ]);
    }

    public function test_mobile_hamburger_drawer_renders_original_logo_and_removes_gradient_d_icon(): void
    {
        $logoPath = 'branding/test_dosttv_logo.png';
        SiteSetting::current()->update([
            'site_name' => 'Dost TV',
            'logo' => $logoPath,
            'logo_alt_text' => 'DOST TV Resmi Logo',
        ]);

        $expectedLogoUrl = Storage::disk('public')->url($logoPath);

        $response = $this->get(route('home'));
        $response->assertStatus(200);

        $content = $response->getContent();

        // 1. Mobile navigation drawer exists
        $this->assertStringContainsString('id="mobile-navigation-drawer"', $content);

        // 2. The drawer contains the original logo image tag
        $this->assertStringContainsString($expectedLogoUrl, $content);
        $this->assertStringContainsString('alt="DOST TV Resmi Logo"', $content);

        // 3. The drawer no longer contains the old gradient D box
        $this->assertStringNotContainsString('bg-gradient-to-br from-rose-500 to-amber-400 font-black text-slate-950 text-xs', $content);

        // 4. The close button is preserved
        $this->assertStringContainsString('aria-label="Menüyü Kapat"', $content);
    }

    public function test_mobile_hamburger_drawer_fallback_when_logo_is_empty(): void
    {
        SiteSetting::current()->update([
            'site_name' => 'Dost TV',
            'logo' => null,
            'logo_alt_text' => null,
        ]);

        $response = $this->get(route('home'));
        $response->assertStatus(200);

        $content = $response->getContent();

        // Drawer exists and fallback text is displayed cleanly without error or gradient D
        $this->assertStringContainsString('id="mobile-navigation-drawer"', $content);
        $this->assertStringNotContainsString('bg-gradient-to-br from-rose-500 to-amber-400 font-black text-slate-950 text-xs', $content);
        $this->assertStringContainsString('aria-label="Menüyü Kapat"', $content);
    }
}

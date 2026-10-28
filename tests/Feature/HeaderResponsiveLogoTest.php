<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeaderResponsiveLogoTest extends TestCase
{
    use RefreshDatabase;

    public function test_desktop_header_logo_renders_with_120px_width_classes_and_styles(): void
    {
        $setting = new SiteSetting([
            'site_name' => 'Dost TV',
            'logo' => 'branding/logo.png',
            'live_tv_is_active' => true,
            'live_tv_is_public' => true,
        ]);

        $view = $this->blade('<x-site.header :site-settings="$setting" />', ['setting' => $setting]);

        // Verify responsive Tailwind classes on desktop
        $view->assertSee('dost-header-logo-img h-auto object-contain lg:w-[120px] lg:max-w-[120px]', false);

        // Verify desktop media query sets 120px
        $view->assertSee('width: 120px;', false);
        $view->assertSee('max-width: 120px;', false);

        // Verify mobile and tablet rules are preserved
        $view->assertSee('@media (min-width: 640px)', false);
        $view->assertSee('@media (min-width: 1024px)', false);

        // Verify live button and navigation remain intact
        $view->assertSee('dost-header-live-btn', false);
        $view->assertSee('Canlı');
    }

    public function test_mobile_hamburger_drawer_logo_is_unmodified(): void
    {
        $view = $this->blade('<x-site.menu location="header_primary" logo-url="branding/logo.png" />');

        $view->assertSee('max-w-[125px]', false);
    }
}

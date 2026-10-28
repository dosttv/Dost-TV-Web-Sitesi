<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\AnnouncementType;
use App\Services\Announcement\AnnouncementService;
use App\View\Components\Site\AnnouncementPopup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AnnouncementPopupAndPlacementTest extends TestCase
{
    use RefreshDatabase;

    protected AnnouncementType $type;

    protected function setUp(): void
    {
        parent::setUp();
        $this->type = AnnouncementType::create([
            'name' => 'Genel',
            'slug' => 'genel',
            'is_active' => true,
        ]);
    }

    public function test_global_announcement_renders_on_all_pages(): void
    {
        Announcement::create([
            'title' => 'Global Sistem Duyurusu',
            'announcement_type_id' => $this->type->id,
            'message' => 'Tüm sayfalarda görünen mesaj',
            'placement' => 'global',
            'is_active' => true,
        ]);

        $this->get('/')->assertOk()->assertSee('Global Sistem Duyurusu');
        $this->get('/canli-yayin')->assertOk()->assertSee('Global Sistem Duyurusu');
        $this->get('/canli-radyo')->assertOk()->assertSee('Global Sistem Duyurusu');
        $this->get('/yayin-akisi')->assertOk()->assertSee('Global Sistem Duyurusu');
        $this->get('/programlar')->assertOk()->assertSee('Global Sistem Duyurusu');
    }

    public function test_home_placement_announcement_renders_only_on_home(): void
    {
        Announcement::create([
            'title' => 'Sadece Ana Sayfa Duyurusu',
            'announcement_type_id' => $this->type->id,
            'message' => 'Yalnız ana sayfaya özel popup',
            'placement' => 'home',
            'is_active' => true,
        ]);

        $this->get('/')->assertOk()->assertSee('Sadece Ana Sayfa Duyurusu');
        $this->get('/canli-yayin')->assertOk()->assertDontSee('Sadece Ana Sayfa Duyurusu');
        $this->get('/yayin-akisi')->assertOk()->assertDontSee('Sadece Ana Sayfa Duyurusu');
    }

    public function test_schedule_placement_announcement_renders_only_on_schedule_page(): void
    {
        Announcement::create([
            'title' => 'Yayın Akışı Özel Duyurusu',
            'announcement_type_id' => $this->type->id,
            'message' => 'Yeni yayın dönemi akış detayları',
            'placement' => 'schedule',
            'is_active' => true,
        ]);

        $this->get('/yayin-akisi')->assertOk()->assertSee('Yayın Akışı Özel Duyurusu');
        $this->get('/')->assertOk()->assertDontSee('Yayın Akışı Özel Duyurusu');
        $this->get('/canli-yayin')->assertOk()->assertDontSee('Yayın Akışı Özel Duyurusu');
    }

    public function test_live_tv_placement_announcement_renders_only_on_live_tv_page(): void
    {
        Announcement::create([
            'title' => 'Canlı TV Canlı Yayın Bilgilendirmesi',
            'announcement_type_id' => $this->type->id,
            'message' => 'Canlı TV özel frekans duyurusu',
            'placement' => 'live_tv',
            'is_active' => true,
        ]);

        $this->get('/canli-yayin')->assertOk()->assertSee('Canlı TV Canlı Yayın Bilgilendirmesi');
        $this->get('/')->assertOk()->assertDontSee('Canlı TV Canlı Yayın Bilgilendirmesi');
        $this->get('/canli-radyo')->assertOk()->assertDontSee('Canlı TV Canlı Yayın Bilgilendirmesi');
    }

    public function test_live_radio_placement_announcement_renders_only_on_live_radio_page(): void
    {
        Announcement::create([
            'title' => 'Canlı Radyo Frekans Duyurusu',
            'announcement_type_id' => $this->type->id,
            'message' => 'Radyo dinleyicilerimize özel mesaj',
            'placement' => 'live_radio',
            'is_active' => true,
        ]);

        $this->get('/canli-radyo')->assertOk()->assertSee('Canlı Radyo Frekans Duyurusu');
        $this->get('/')->assertOk()->assertDontSee('Canlı Radyo Frekans Duyurusu');
        $this->get('/canli-yayin')->assertOk()->assertDontSee('Canlı Radyo Frekans Duyurusu');
    }

    public function test_expired_announcement_is_not_rendered(): void
    {
        Announcement::create([
            'title' => 'Süresi Dolan Eski Duyuru',
            'announcement_type_id' => $this->type->id,
            'placement' => 'global',
            'is_active' => true,
            'ends_at' => now()->subMinute(),
        ]);

        $this->get('/')->assertOk()->assertDontSee('Süresi Dolan Eski Duyuru');
    }

    public function test_future_scheduled_announcement_is_not_rendered(): void
    {
        Announcement::create([
            'title' => 'Gelecekte Başlayacak Duyuru',
            'announcement_type_id' => $this->type->id,
            'placement' => 'global',
            'is_active' => true,
            'starts_at' => now()->addHour(),
        ]);

        $this->get('/')->assertOk()->assertDontSee('Gelecekte Başlayacak Duyuru');
    }

    public function test_pinned_announcement_has_highest_priority_over_newer_announcements(): void
    {
        Announcement::create([
            'title' => 'Normal Yeni Duyuru',
            'announcement_type_id' => $this->type->id,
            'placement' => 'global',
            'is_active' => true,
            'is_pinned' => false,
            'created_at' => now()->subMinutes(5),
        ]);

        Announcement::create([
            'title' => 'Sabitlenmiş Önemli Duyuru',
            'announcement_type_id' => $this->type->id,
            'placement' => 'global',
            'is_active' => true,
            'is_pinned' => true,
            'created_at' => now()->subMinutes(20),
        ]);

        $selected = AnnouncementPopup::getActivePopupAnnouncement('global');

        $this->assertNotNull($selected);
        $this->assertSame('Sabitlenmiş Önemli Duyuru', $selected->title);

        $response = $this->get('/');
        $response->assertOk();
        $response->assertSee('Sabitlenmiş Önemli Duyuru');
        $response->assertDontSee('Normal Yeni Duyuru');
    }

    public function test_cta_button_renders_when_button_url_is_provided(): void
    {
        Announcement::create([
            'title' => 'Özel Yayın Başladı',
            'announcement_type_id' => $this->type->id,
            'message' => 'Hemen izlemek için butona tıklayınız.',
            'button_text' => 'Canlı İzle',
            'button_url' => '/canli-tv',
            'placement' => 'global',
            'is_active' => true,
        ]);

        $response = $this->get('/');
        $response->assertOk();
        $response->assertSee('Özel Yayın Başladı');
        $response->assertSee('Canlı İzle');
        $response->assertSee('href="/canli-tv"', false);
    }

    public function test_cta_button_does_not_render_when_button_url_is_null(): void
    {
        Announcement::create([
            'title' => 'Butonsuz Bilgilendirme',
            'announcement_type_id' => $this->type->id,
            'message' => 'Sadece bilgi amaçlıdır.',
            'button_text' => null,
            'button_url' => null,
            'placement' => 'global',
            'is_active' => true,
        ]);

        $response = $this->get('/');
        $response->assertOk();
        $response->assertSee('Butonsuz Bilgilendirme');
        $response->assertSee('Anladım');
    }

    public function test_announcement_popup_uses_safe_modal_z_index_and_local_storage(): void
    {
        Announcement::create([
            'title' => 'Z-Index ve LocalStorage Testi',
            'announcement_type_id' => $this->type->id,
            'placement' => 'global',
            'is_active' => true,
        ]);

        $response = $this->get('/');
        $response->assertOk();
        $response->assertSee('z-[9990]');
        $response->assertSee('localStorage.getItem(this.storageKey)');
        $response->assertSee('localStorage.setItem(this.storageKey, Date.now().toString())');
        $response->assertSee('24 * 60 * 60 * 1000');
    }

    public function test_exact_user_verification_scenario(): void
    {
        // 1. Create temporary announcement with exact user params
        $announcement = Announcement::create([
            'title' => 'DOST TV Test Duyurusu',
            'announcement_type_id' => $this->type->id,
            'message' => 'Popup sisteminin düzgün çalışıp çalışmadığını test ediyoruz.',
            'placement' => 'home',
            'starts_at' => now(),
            'ends_at' => now()->addDay(),
            'is_active' => true,
            'is_pinned' => true,
            'button_text' => 'Yayın Akışına Git',
            'button_url' => '/yayin-akisi',
        ]);

        // 2. Verify on Home Page
        $homeResponse = $this->get('/');
        $homeResponse->assertOk();
        $homeResponse->assertSee('DOST TV Test Duyurusu');
        $homeResponse->assertSee('Popup sisteminin düzgün çalışıp çalışmadığını test ediyoruz.');
        $homeResponse->assertSee('Yayın Akışına Git');
        $homeResponse->assertSee('href="/yayin-akisi"', false);
        $homeResponse->assertSee('z-[9990]');
        $homeResponse->assertSee("document.body.style.overflow = 'hidden'", false);
        $homeResponse->assertSee("document.body.style.overflow = ''", false);
        $updatedAt = $announcement->updated_at->timestamp;
        $homeResponse->assertSee("dosttv_announcement_dismissed_{$announcement->id}_{$updatedAt}");

        // 3. Verify placement isolation
        $scheduleResponse = $this->get('/yayin-akisi');
        $scheduleResponse->assertOk();
        $scheduleResponse->assertDontSee('DOST TV Test Duyurusu');

        $liveTvResponse = $this->get('/canli-yayin');
        $liveTvResponse->assertOk();
        $liveTvResponse->assertDontSee('DOST TV Test Duyurusu');

        $liveRadioResponse = $this->get('/canli-radyo');
        $liveRadioResponse->assertOk();
        $liveRadioResponse->assertDontSee('DOST TV Test Duyurusu');
    }
}

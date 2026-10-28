<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\LiveBroadcastPage;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LiveBroadcastPageTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'administrator']);
        SiteSetting::current();
    }

    public function test_authorized_user_can_access_live_broadcast_page(): void
    {
        $this->actingAs($this->admin)
            ->get(LiveBroadcastPage::getUrl())
            ->assertSuccessful();
    }

    public function test_can_switch_between_dost_tv_and_dost_fm_tabs(): void
    {
        Livewire::actingAs($this->admin)
            ->test(LiveBroadcastPage::class)
            ->assertSet('activeTab', 'tv')
            ->set('activeTab', 'fm')
            ->assertSet('activeTab', 'fm');
    }

    public function test_dost_tv_settings_can_be_saved_independently(): void
    {
        Livewire::actingAs($this->admin)
            ->test(LiveBroadcastPage::class)
            ->fillForm([
                'live_tv_title' => 'Özel Dost TV Canlı',
                'live_tv_type' => 'hls',
                'live_tv_url' => 'https://example.com/live/stream.m3u8',
            ], 'tvForm')
            ->call('saveTv')
            ->assertHasNoFormErrors();

        $settings = SiteSetting::current();
        $this->assertEquals('Özel Dost TV Canlı', $settings->live_tv_title);
        $this->assertEquals('https://example.com/live/stream.m3u8', $settings->live_tv_url);
    }

    public function test_dost_fm_settings_can_be_saved_independently(): void
    {
        Livewire::actingAs($this->admin)
            ->test(LiveBroadcastPage::class)
            ->fillForm([
                'radio_name' => 'Özel Dost FM Canlı',
                'radio_stream_url' => 'https://example.com/radio/stream.mp3',
            ], 'fmForm')
            ->call('saveFm')
            ->assertHasNoFormErrors();

        $settings = SiteSetting::current();
        $this->assertEquals('Özel Dost FM Canlı', $settings->radio_name);
        $this->assertEquals('https://example.com/radio/stream.mp3', $settings->radio_stream_url);
    }

    public function test_saving_tv_settings_does_not_mutate_fm_settings(): void
    {
        $settings = SiteSetting::current();
        $settings->update(['radio_name' => 'Orijinal Radyo']);

        Livewire::actingAs($this->admin)
            ->test(LiveBroadcastPage::class)
            ->fillForm([
                'live_tv_title' => 'Yeni TV Adı',
                'live_tv_type' => 'iframe',
                'live_tv_url' => 'https://example.com/embed',
            ], 'tvForm')
            ->call('saveTv');

        $this->assertEquals('Orijinal Radyo', SiteSetting::current()->radio_name);
    }

    public function test_test_connection_action_handles_invalid_url(): void
    {
        Livewire::actingAs($this->admin)
            ->test(LiveBroadcastPage::class)
            ->set('tvData.live_tv_url', 'invalid-url')
            ->call('testTvConnection')
            ->assertNotified('URL Biçimi Geçersiz');
    }

    public function test_public_tv_and_radio_routes_return_status_200(): void
    {
        SiteSetting::current()->update([
            'live_tv_url' => 'https://example.com/live.m3u8',
            'live_tv_type' => 'hls',
            'radio_stream_url' => 'https://example.com/radio.mp3',
        ]);

        $this->get('/canli-yayin')->assertStatus(200);
        $this->get('/canli-tv')->assertStatus(301)->assertRedirect('/canli-yayin');
        $this->get('/canli-radyo')->assertStatus(200);
    }
}

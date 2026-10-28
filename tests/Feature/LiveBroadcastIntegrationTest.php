<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiveBroadcastIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SiteSetting::current()->update([
            'site_name' => 'Dost TV',
            'live_tv_is_active' => true,
            'live_tv_is_public' => true,
            'live_tv_title' => 'Özel Canlı TV Başlığı',
            'live_tv_description' => 'Özel canlı yayın açıklaması metni.',
            'live_tv_type' => 'hls',
            'live_tv_url' => 'https://stream.dosttv.com/tv/live.m3u8',
            'live_tv_backup_url' => 'https://backup.dosttv.com/tv/live.m3u8',
            'live_tv_maintenance_message' => 'Yayınımız şu anda planlı bakım çalışmasındadır.',
            'live_tv_error_message' => 'Yayın akışına ulaşılamadı lütfen birazdan tekrar deneyiniz.',
            'radio_is_active' => true,
            'radio_is_public' => true,
            'radio_name' => 'Özel Dost FM Başlığı',
            'radio_description' => 'Özel radyo açıklaması metni.',
            'radio_stream_url' => 'https://stream.dosttv.com/fm/live.mp3',
            'radio_backup_url' => 'https://backup.dosttv.com/fm/live.mp3',
            'radio_maintenance_message' => 'Radyo yayınımız bakım çalışmasındadır.',
            'radio_error_message' => 'Radyo akışı yüklenemedi.',
            'live_button_is_visible' => true,
        ]);
    }

    public function test_tv_active_and_public_renders_player_title_and_description(): void
    {
        $response = $this->get(route('live.tv'));

        $response->assertOk();
        $response->assertSee('Özel Canlı TV Başlığı');
        $response->assertSee('Özel canlı yayın açıklaması metni.');
        $response->assertSee('id="hls-player"', false);
        $response->assertSee('data-src="https://stream.dosttv.com/tv/live.m3u8"', false);
        $response->assertSee('data-backup-src="https://backup.dosttv.com/tv/live.m3u8"', false);
        $response->assertSee('data-error-msg="Yayın akışına ulaşılamadı lütfen birazdan tekrar deneyiniz."', false);
        $response->assertDontSee('Yayın Bakımda');
        $response->assertDontSee('Canlı Yayın Genel Erişime Kapalıdır');
    }

    public function test_tv_inactive_and_public_renders_maintenance_message_and_no_player(): void
    {
        SiteSetting::current()->update([
            'live_tv_is_active' => false,
            'live_tv_is_public' => true,
        ]);

        $response = $this->get(route('live.tv'));

        $response->assertOk();
        $response->assertSee('Yayın Bakımda');
        $response->assertSee('Yayınımız şu anda planlı bakım çalışmasındadır.');
        $response->assertDontSee('id="hls-player"', false);
    }

    public function test_tv_non_public_renders_public_restriction_and_no_player(): void
    {
        SiteSetting::current()->update([
            'live_tv_is_public' => false,
        ]);

        $response = $this->get(route('live.tv'));

        $response->assertOk();
        $response->assertSee('Canlı Yayın Genel Erişime Kapalıdır');
        $response->assertDontSee('id="hls-player"', false);
    }

    public function test_tv_header_live_button_hidden_when_tv_is_not_public(): void
    {
        SiteSetting::current()->update([
            'live_tv_is_public' => false,
        ]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertDontSee(route('live.tv'));
    }

    public function test_fm_active_and_public_renders_audio_player_name_and_description(): void
    {
        $response = $this->get(route('live.radio'));

        $response->assertOk();
        $response->assertSee('Özel Dost FM Başlığı');
        $response->assertSee('Özel radyo açıklaması metni.');
        $response->assertSee('https://stream.dosttv.com/fm/live.mp3');
        $response->assertSee('https://backup.dosttv.com/fm/live.mp3');
        $response->assertDontSee('Canlı radyo yayını şu anda public erişime kapalıdır.');
    }

    public function test_fm_inactive_and_public_renders_maintenance_message(): void
    {
        SiteSetting::current()->update([
            'radio_is_active' => false,
            'radio_is_public' => true,
        ]);

        $response = $this->get(route('live.radio'));

        $response->assertOk();
        $response->assertSee('Radyo yayınımız bakım çalışmasındadır.');
        $response->assertDontSee('<audio', false);
    }

    public function test_fm_non_public_renders_restriction_and_hides_from_tv_page(): void
    {
        SiteSetting::current()->update([
            'radio_is_public' => false,
        ]);

        // Radio page shows restriction
        $responseRadio = $this->get(route('live.radio'));
        $responseRadio->assertOk();
        $responseRadio->assertSee('Canlı radyo yayını şu anda public erişime kapalıdır.');
        $responseRadio->assertDontSee('<audio', false);

        // TV page does NOT show Dost FM CTA switcher
        $responseTv = $this->get(route('live.tv'));
        $responseTv->assertOk();
        $responseTv->assertDontSee(route('live.radio'));
    }

    public function test_tv_title_falls_back_when_empty(): void
    {
        SiteSetting::current()->update([
            'live_tv_title' => null,
            'site_name' => 'Dost TV',
        ]);

        $response = $this->get(route('live.tv'));
        $response->assertOk();
        $response->assertSee('Dost TV Canlı TV');
    }

    public function test_tv_description_empty_does_not_render_extra_paragraph(): void
    {
        SiteSetting::current()->update([
            'live_tv_description' => null,
        ]);

        $response = $this->get(route('live.tv'));
        $response->assertOk();
        $response->assertDontSee('Özel canlı yayın açıklaması metni.');
    }

    public function test_fm_title_falls_back_when_empty(): void
    {
        SiteSetting::current()->update([
            'radio_name' => null,
            'site_name' => 'Dost TV',
        ]);

        $response = $this->get(route('live.radio'));
        $response->assertOk();
        $response->assertSee('Dost TV Canlı Radyo');
    }

    public function test_tv_iframe_type_renders_iframe(): void
    {
        SiteSetting::current()->update([
            'live_tv_type' => 'iframe',
            'live_tv_url' => 'https://www.youtube-nocookie.com/embed/live_stream?channel=DOSTTV',
        ]);

        $response = $this->get(route('live.tv'));
        $response->assertOk();
        $response->assertSee('<iframe', false);
        $response->assertSee('https://www.youtube-nocookie.com/embed/live_stream?channel=DOSTTV');
        $response->assertDontSee('id="hls-player"', false);
    }

    public function test_legacy_canli_tv_permanently_redirects_to_canli_yayin(): void
    {
        // 1. /canli-tv returns 301 permanent redirect to /canli-yayin
        $response = $this->get('/canli-tv');
        $response->assertStatus(301);
        $response->assertRedirect('/canli-yayin');

        // 2. /canli-tv/ with trailing slash also redirects to /canli-yayin
        $responseSlash = $this->get('/canli-tv/');
        $responseSlash->assertStatus(301);
        $responseSlash->assertRedirect('/canli-yayin');

        // 3. /canli-yayin returns 200 OK directly
        $responseLive = $this->get('/canli-yayin');
        $responseLive->assertOk();
        $responseLive->assertSee('Özel Canlı TV Başlığı');
        $responseLive->assertSee('id="hls-player"', false);

        // 4. Following redirect from /canli-tv arrives at /canli-yayin with 200 OK and no loop
        $followed = $this->followingRedirects()->get('/canli-tv');
        $followed->assertOk();
        $followed->assertSee('Özel Canlı TV Başlığı');
        $followed->assertSee('id="hls-player"', false);
    }
}

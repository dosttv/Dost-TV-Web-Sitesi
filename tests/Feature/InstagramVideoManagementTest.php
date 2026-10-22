<?php

namespace Tests\Feature;

use App\Filament\Resources\InstagramVideos\InstagramVideoResource;
use App\Filament\Resources\InstagramVideos\Pages\CreateInstagramVideo;
use App\Models\HomepageLayout;
use App\Models\InstagramCategory;
use App\Models\InstagramReelsSchedule;
use App\Models\InstagramVideo;
use App\Models\User;
use App\Services\Home\HomepageBlockRegistry;
use App\Services\Home\HomepageDataService;
use App\Services\Instagram\InstagramFetchService;
use App\Services\Instagram\InstagramSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class InstagramVideoManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'email' => 'admin@dosttv.com',
            'is_active' => true,
        ]);
    }

    public function test_instagram_fetch_service_extracts_identifier_and_parses_various_urls()
    {
        $service = app(InstagramFetchService::class);

        $reelInfo = $service->extractIdentifier('https://www.instagram.com/reel/C123456789/?igsh=abc');
        $this->assertEquals('C123456789', $reelInfo['shortcode']);
        $this->assertEquals('reel', $reelInfo['type']);
        $this->assertEquals('https://www.instagram.com/reel/C123456789/', $reelInfo['permalink']);

        $postInfo = $service->extractIdentifier('https://instagram.com/p/ABC_987654/');
        $this->assertEquals('ABC_987654', $postInfo['shortcode']);
        $this->assertEquals('p', $postInfo['type']);
        $this->assertEquals('https://www.instagram.com/p/ABC_987654/', $postInfo['permalink']);

        $tvInfo = $service->extractIdentifier('https://instagr.am/tv/XYZ123/');
        $this->assertEquals('XYZ123', $tvInfo['shortcode']);
        $this->assertEquals('tv', $tvInfo['type']);
        $this->assertEquals('https://www.instagram.com/tv/XYZ123/', $tvInfo['permalink']);
    }

    public function test_invalid_instagram_url_throws_invalid_argument_exception()
    {
        $this->expectException(InvalidArgumentException::class);

        $service = app(InstagramFetchService::class);
        $service->extractIdentifier('https://invalid-url.com/something');
    }

    public function test_duplicate_instagram_video_throws_friendly_exception()
    {
        InstagramVideo::create([
            'instagram_media_id' => 'ig_DUP123',
            'shortcode' => 'DUP123',
            'permalink' => 'https://www.instagram.com/reel/DUP123/',
            'is_active' => true,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Bu Instagram içeriği zaten eklenmiş.');

        $service = app(InstagramFetchService::class);
        $service->fetchMediaData('https://www.instagram.com/reel/DUP123/');
    }

    public function test_admin_can_access_and_create_instagram_video_with_speaker_name_and_caption_in_filament()
    {
        $this->actingAs($this->admin);

        $createResponse = $this->get(InstagramVideoResource::getUrl('create'));
        $createResponse->assertStatus(200);

        Livewire::test(CreateInstagramVideo::class)
            ->fillForm([
                'permalink' => 'https://www.instagram.com/reel/SPEAKER123/',
                'shortcode' => 'SPEAKER123',
                'speaker_name' => 'Prof. Dr. Nevzat Tarhan',
                'caption' => 'Özel Editör Başlığı',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('instagram_videos', [
            'shortcode' => 'SPEAKER123',
            'speaker_name' => 'Prof. Dr. Nevzat Tarhan',
            'caption' => 'Özel Editör Başlığı',
            'is_active' => true,
        ]);
    }

    public function test_instagram_unified_center_page_renders_four_tabs_and_single_sidebar_item()
    {
        $this->actingAs($this->admin);

        // Access the unified Instagram Management Hub page via InstagramVideoResource index
        $response = $this->get(InstagramVideoResource::getUrl('index'));
        $response->assertStatus(200);
        $response->assertSee('Reels');
        $response->assertSee('Kategoriler');
        $response->assertSee('Haftalık Plan');
        $response->assertSee('Gösterim Ayarları');

        // Verify that separate sidebar items are hidden from navigation registration
        $this->assertFalse(\App\Filament\Resources\InstagramCategories\InstagramCategoryResource::shouldRegisterNavigation());
        $this->assertFalse(\App\Filament\Pages\ManageInstagramSchedule::shouldRegisterNavigation());
        $this->assertTrue(\App\Filament\Resources\InstagramVideos\InstagramVideoResource::shouldRegisterNavigation());
    }

    public function test_speaker_name_and_edited_caption_sync_protection()
    {
        config(['services.instagram.access_token' => 'test_token']);
        putenv('INSTAGRAM_ACCOUNT_ID=178414000000000');

        $existing = InstagramVideo::create([
            'instagram_media_id' => '18000999888777',
            'shortcode' => 'C_SYNC222',
            'permalink' => 'https://www.instagram.com/reel/C_SYNC222/',
            'thumbnail_url' => 'https://scontent.cdninstagram.com/old_thumb.jpg',
            'speaker_name' => 'Prof. Dr. Halis Aydemir',
            'caption' => 'Editör Tarafından Değiştirilmiş Özel Açıklama',
            'is_active' => true,
        ]);

        Http::fake([
            'https://graph.facebook.com/v19.0/178414000000000/media*' => Http::response([
                'data' => [
                    [
                        'id' => '18000999888777',
                        'media_type' => 'REELS',
                        'media_product_type' => 'REELS',
                        'permalink' => 'https://www.instagram.com/reel/C_SYNC222/',
                        'thumbnail_url' => 'https://scontent.cdninstagram.com/new_thumb.jpg',
                        'caption' => 'Instagram\'dan Gelen Ham Caption Metni',
                        'timestamp' => '2026-08-27T10:00:00+0000',
                        'username' => 'dosttv',
                    ],
                ],
            ], 200),
        ]);

        $syncService = app(InstagramSyncService::class);
        $stats = $syncService->syncLatestReels();

        $this->assertEquals(1, $stats['updated']);

        // Verify that speaker_name and edited caption were NOT overwritten by sync
        $existing->refresh();
        $this->assertEquals('Prof. Dr. Halis Aydemir', $existing->speaker_name);
        $this->assertEquals('Editör Tarafından Değiştirilmiş Özel Açıklama', $existing->caption);
        $this->assertEquals('https://scontent.cdninstagram.com/new_thumb.jpg', $existing->thumbnail_url);
    }

    public function test_cover_image_render_priority_and_manual_upload_fallback()
    {
        Storage::fake('public');

        // Priority 1: Manual cover image overrides thumbnail_url and media_url
        $videoWithManualCover = InstagramVideo::create([
            'shortcode' => 'MANUAL1',
            'permalink' => 'https://www.instagram.com/reel/MANUAL1/',
            'cover_image' => 'instagram-covers/custom_1080x1920.jpg',
            'thumbnail_url' => 'https://images.instagram.com/api_thumb.jpg',
            'media_url' => 'https://images.instagram.com/api_media.mp4',
            'is_active' => true,
        ]);

        $this->assertEquals(\App\Support\ResponsiveImage::url('instagram-covers/custom_1080x1920.jpg', 640), $videoWithManualCover->cover_image_url);

        // Priority 2: Fallback to thumbnail_url when cover_image is null
        $videoWithApiThumb = InstagramVideo::create([
            'shortcode' => 'APITHUMB2',
            'permalink' => 'https://www.instagram.com/reel/APITHUMB2/',
            'cover_image' => null,
            'thumbnail_url' => 'https://images.instagram.com/api_thumb2.jpg',
            'is_active' => true,
        ]);

        $this->assertEquals('https://images.instagram.com/api_thumb2.jpg', $videoWithApiThumb->cover_image_url);

        // Priority 3: Fallback to media_url when cover_image and thumbnail_url are null
        $videoWithMediaUrl = InstagramVideo::create([
            'shortcode' => 'MEDIA3',
            'permalink' => 'https://www.instagram.com/reel/MEDIA3/',
            'cover_image' => null,
            'thumbnail_url' => null,
            'media_url' => 'https://images.instagram.com/api_media3.jpg',
            'is_active' => true,
        ]);

        $this->assertEquals('https://images.instagram.com/api_media3.jpg', $videoWithMediaUrl->cover_image_url);

        // No image at all -> cover_image_url is null and excluded from public resolution
        $videoNoImage = InstagramVideo::create([
            'shortcode' => 'NOIMG4',
            'permalink' => 'https://www.instagram.com/reel/NOIMG4/',
            'cover_image' => null,
            'thumbnail_url' => null,
            'media_url' => null,
            'is_active' => true,
        ]);

        $this->assertNull($videoNoImage->cover_image_url);

        $block = [
            'uuid' => 'ig_priority_test',
            'block_type' => 'instagram_videos',
            'title' => 'Instagram Videolarımız',
            'show_title' => true,
            'visible' => true,
        ];

        $service = app(HomepageDataService::class);
        $resolved = $service->resolveInstagramVideoBlockData($block);

        $this->assertCount(3, $resolved);
        $shortcodes = $resolved->pluck('shortcode')->toArray();
        $this->assertContains('MANUAL1', $shortcodes);
        $this->assertContains('APITHUMB2', $shortcodes);
        $this->assertContains('MEDIA3', $shortcodes);
        $this->assertNotContains('NOIMG4', $shortcodes);
    }

    public function test_safe_embed_html_accessor_sanitizes_malicious_scripts()
    {
        $video = InstagramVideo::create([
            'shortcode' => 'XSS123',
            'permalink' => 'https://www.instagram.com/reel/XSS123/',
            'embed_html' => '<blockquote class="instagram-media" data-instgrm-permalink="https://www.instagram.com/reel/XSS123/">Valid</blockquote><script>alert(1)</script>',
            'is_active' => true,
        ]);

        $safeHtml = $video->safe_embed_html;
        $this->assertStringContainsString('instagram-media', $safeHtml);
        $this->assertStringNotContainsString('alert(1)', $safeHtml);
    }

    public function test_public_instagram_videos_page_renders_single_stream_in_order()
    {
        $oldVideo = InstagramVideo::create([
            'shortcode' => 'OLD111',
            'permalink' => 'https://www.instagram.com/reel/OLD111/',
            'thumbnail_url' => 'https://images.instagram.com/old111.jpg',
            'is_active' => true,
            'posted_at' => '2025-01-01 10:00:00',
        ]);

        $newVideo = InstagramVideo::create([
            'shortcode' => 'NEW222',
            'permalink' => 'https://www.instagram.com/reel/NEW222/',
            'thumbnail_url' => 'https://images.instagram.com/new222.jpg',
            'is_active' => true,
            'posted_at' => '2026-08-26 12:00:00',
        ]);

        $inactiveVideo = InstagramVideo::create([
            'shortcode' => 'HIDDEN333',
            'permalink' => 'https://www.instagram.com/reel/HIDDEN333/',
            'thumbnail_url' => 'https://images.instagram.com/hidden333.jpg',
            'is_active' => false,
            'posted_at' => '2026-08-26 13:00:00',
        ]);

        $response = $this->get(route('instagram-videos.index'));

        $response->assertStatus(200);
        $response->assertSee('DOST TV Instagram Videoları');
        $response->assertSee('https://www.instagram.com/reel/NEW222/');
        $response->assertSee('https://www.instagram.com/reel/OLD111/');
        $response->assertDontSee('https://www.instagram.com/reel/HIDDEN333/');
    }

    public function test_homepage_data_service_deduplicates_duplicate_instagram_videos()
    {
        InstagramVideo::create([
            'shortcode' => 'DUP999',
            'permalink' => 'https://www.instagram.com/reel/DUP999/',
            'thumbnail_url' => 'https://images.instagram.com/dup999.jpg',
            'is_active' => true,
        ]);
        InstagramVideo::create([
            'shortcode' => 'DUP999',
            'permalink' => 'https://www.instagram.com/p/DUP999/?hl=tr',
            'thumbnail_url' => 'https://images.instagram.com/dup999.jpg',
            'is_active' => true,
        ]);

        $block = [
            'uuid' => 'ig_dedupe_1',
            'block_type' => 'instagram_videos',
            'title' => 'Instagram Videolarımız',
            'show_title' => true,
            'visible' => true,
        ];

        $service = app(HomepageDataService::class);
        $resolved = $service->resolveInstagramVideoBlockData($block);

        $this->assertCount(1, $resolved);
        $this->assertEquals('DUP999', $resolved->first()->shortcode);
    }

    public function test_weekly_schedule_resolves_assigned_category_reels_for_today()
    {
        $catHocalar = InstagramCategory::create(['name' => 'Hocalar', 'slug' => 'hocalar', 'is_active' => true]);
        $catAyetler = InstagramCategory::create(['name' => 'Ayetler', 'slug' => 'ayetler', 'is_active' => true]);

        $vHoca = InstagramVideo::create([
            'shortcode' => 'HOCA1',
            'permalink' => 'https://www.instagram.com/reel/HOCA1/',
            'thumbnail_url' => 'https://images.instagram.com/hoca1.jpg',
            'is_active' => true,
        ]);
        $vHoca->categories()->attach($catHocalar->id);

        $vAyet = InstagramVideo::create([
            'shortcode' => 'AYET1',
            'permalink' => 'https://www.instagram.com/reel/AYET1/',
            'thumbnail_url' => 'https://images.instagram.com/ayet1.jpg',
            'is_active' => true,
        ]);
        $vAyet->categories()->attach($catAyetler->id);

        $now = now('Europe/Istanbul');
        $dayOfWeek = $now->dayOfWeekIso; // 1 to 7

        $dayFields = [
            1 => 'monday_category_id',
            2 => 'tuesday_category_id',
            3 => 'wednesday_category_id',
            4 => 'thursday_category_id',
            5 => 'friday_category_id',
            6 => 'saturday_category_id',
            7 => 'sunday_category_id',
        ];

        $todayField = $dayFields[$dayOfWeek];

        // Assign Hocalar category to today's schedule
        $schedule = InstagramReelsSchedule::current();
        $schedule->update([
            $todayField => $catHocalar->id,
            'sort_mode' => 'latest',
        ]);

        $block = [
            'uuid' => 'ig_sched_test',
            'block_type' => 'instagram_videos',
            'title' => 'Instagram Videolarımız',
            'visible' => true,
        ];

        $service = app(HomepageDataService::class);
        $resolved = $service->resolveInstagramVideoBlockData($block);

        $this->assertCount(1, $resolved);
        $this->assertEquals('HOCA1', $resolved->first()->shortcode);
    }

    public function test_sorting_modes_manual_latest_oldest_and_deterministic_random()
    {
        $cat = InstagramCategory::create(['name' => 'Tefekkür', 'slug' => 'tefokkur', 'is_active' => true]);

        $vOld = InstagramVideo::create([
            'shortcode' => 'SORT_OLD',
            'permalink' => 'https://www.instagram.com/reel/SORT_OLD/',
            'thumbnail_url' => 'https://images.instagram.com/old.jpg',
            'is_active' => true,
            'posted_at' => '2025-01-01 10:00:00',
        ]);
        $vNew = InstagramVideo::create([
            'shortcode' => 'SORT_NEW',
            'permalink' => 'https://www.instagram.com/reel/SORT_NEW/',
            'thumbnail_url' => 'https://images.instagram.com/new.jpg',
            'is_active' => true,
            'posted_at' => '2026-08-27 10:00:00',
        ]);

        $vOld->categories()->attach($cat->id, ['sort_order' => 2]);
        $vNew->categories()->attach($cat->id, ['sort_order' => 1]);

        $dayOfWeek = now('Europe/Istanbul')->dayOfWeekIso;
        $dayFields = [1 => 'monday_category_id', 2 => 'tuesday_category_id', 3 => 'wednesday_category_id', 4 => 'thursday_category_id', 5 => 'friday_category_id', 6 => 'saturday_category_id', 7 => 'sunday_category_id'];
        $schedule = InstagramReelsSchedule::current();
        $schedule->update([$dayFields[$dayOfWeek] => $cat->id]);

        $service = app(HomepageDataService::class);

        // 1. Manuel mode (pivot sort_order: vNew first)
        $schedule->update(['sort_mode' => 'manual']);
        $resManual = $service->resolveInstagramVideoBlockData(['block_type' => 'instagram_videos']);
        $this->assertEquals('SORT_NEW', $resManual->first()->shortcode);

        // 2. Latest mode (posted_at desc: vNew first)
        $schedule->update(['sort_mode' => 'latest']);
        $resLatest = $service->resolveInstagramVideoBlockData(['block_type' => 'instagram_videos']);
        $this->assertEquals('SORT_NEW', $resLatest->first()->shortcode);

        // 3. Oldest mode (posted_at asc: vOld first)
        $schedule->update(['sort_mode' => 'oldest']);
        $resOldest = $service->resolveInstagramVideoBlockData(['block_type' => 'instagram_videos']);
        $this->assertEquals('SORT_OLD', $resOldest->first()->shortcode);

        // 4. Random mode (deterministic seed: constant across multiple resolutions on the same day)
        $schedule->update(['sort_mode' => 'random']);
        $resRandom1 = $service->resolveInstagramVideoBlockData(['block_type' => 'instagram_videos']);
        $resRandom2 = $service->resolveInstagramVideoBlockData(['block_type' => 'instagram_videos']);
        $this->assertEquals($resRandom1->pluck('shortcode')->toArray(), $resRandom2->pluck('shortcode')->toArray());
    }

    public function test_inactive_category_falls_back_safely_to_all_active_reels()
    {
        $inactiveCat = InstagramCategory::create(['name' => 'Pasif Kategori', 'slug' => 'pasif-cat', 'is_active' => false]);

        $vActive = InstagramVideo::create([
            'shortcode' => 'FB_ACTIVE',
            'permalink' => 'https://www.instagram.com/reel/FB_ACTIVE/',
            'thumbnail_url' => 'https://images.instagram.com/fb.jpg',
            'is_active' => true,
        ]);
        $vActive->categories()->attach($inactiveCat->id);

        $dayOfWeek = now('Europe/Istanbul')->dayOfWeekIso;
        $dayFields = [1 => 'monday_category_id', 2 => 'tuesday_category_id', 3 => 'wednesday_category_id', 4 => 'thursday_category_id', 5 => 'friday_category_id', 6 => 'saturday_category_id', 7 => 'sunday_category_id'];
        $schedule = InstagramReelsSchedule::current();
        $schedule->update([$dayFields[$dayOfWeek] => $inactiveCat->id]);

        $service = app(HomepageDataService::class);
        $resolved = $service->resolveInstagramVideoBlockData(['block_type' => 'instagram_videos']);

        // Since the assigned category is inactive, system falls back to active Reels pool
        $this->assertNotEmpty($resolved);
        $this->assertContains('FB_ACTIVE', $resolved->pluck('shortcode')->toArray());
    }

    public function test_inactive_reels_are_excluded_from_category_pool()
    {
        $cat = InstagramCategory::create(['name' => 'Dualar', 'slug' => 'dualar', 'is_active' => true]);

        $vPassive = InstagramVideo::create([
            'shortcode' => 'REEL_PASSIVE',
            'permalink' => 'https://www.instagram.com/reel/REEL_PASSIVE/',
            'thumbnail_url' => 'https://images.instagram.com/pass.jpg',
            'is_active' => false,
        ]);
        $vPassive->categories()->attach($cat->id);

        $dayOfWeek = now('Europe/Istanbul')->dayOfWeekIso;
        $dayFields = [1 => 'monday_category_id', 2 => 'tuesday_category_id', 3 => 'wednesday_category_id', 4 => 'thursday_category_id', 5 => 'friday_category_id', 6 => 'saturday_category_id', 7 => 'sunday_category_id'];
        $schedule = InstagramReelsSchedule::current();
        $schedule->update([$dayFields[$dayOfWeek] => $cat->id]);

        $service = app(HomepageDataService::class);
        $resolved = $service->resolveInstagramVideoBlockData(['block_type' => 'instagram_videos']);

        $this->assertNotContains('REEL_PASSIVE', $resolved->pluck('shortcode')->toArray());
    }
}

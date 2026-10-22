<?php

namespace Tests\Feature;

use App\Support\ResponsiveImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ResponsiveImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function storePng(string $path, int $width, int $height): void
    {
        Storage::disk('public')->put($path, UploadedFile::fake()->image('x.png', $width, $height)->getContent());
    }

    public function test_url_points_to_resize_route_for_public_disk_images(): void
    {
        $this->assertSame(url('/img/480/programs/abc.png'), ResponsiveImage::url('programs/abc.png', 480));
        $this->assertSame(url('/img/640/programs/abc.png'), ResponsiveImage::url('programs/abc.png', 600));
    }

    public function test_external_and_non_image_paths_are_left_untouched(): void
    {
        $this->assertSame('https://i.ytimg.com/vi/x/hqdefault.jpg', ResponsiveImage::url('https://i.ytimg.com/vi/x/hqdefault.jpg', 480));
        $this->assertNull(ResponsiveImage::srcset('https://example.com/a.png', [320]));
        $this->assertNull(ResponsiveImage::srcset('docs/file.pdf', [320]));
        $this->assertNull(ResponsiveImage::url(null, 320));
    }

    public function test_srcset_lists_each_width(): void
    {
        $this->assertSame(
            url('/img/320/programs/a.png').' 320w, '.url('/img/640/programs/a.png').' 640w',
            ResponsiveImage::srcset('programs/a.png', [320, 640])
        );
    }

    public function test_route_resizes_to_webp_caches_variant_and_sets_cache_headers(): void
    {
        $this->storePng('programs/big.png', 2000, 1000);

        $response = $this->get('/img/480/programs/big.png');

        $response->assertOk()
            ->assertHeader('Content-Type', 'image/webp')
            ->assertHeader('Cache-Control', 'immutable, max-age=31536000, public');
        $this->assertEmpty($response->headers->getCookies(), 'Resized images must not set cookies so the CDN can cache them.');

        $variant = ResponsiveImage::variantPath('programs/big.png', 480);
        Storage::disk('public')->assertExists($variant);
        [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($variant));
        $this->assertSame([480, 240], [$width, $height]);

        // Second request is served from the stored variant.
        $this->get('/img/480/programs/big.png')->assertOk()->assertHeader('Content-Type', 'image/webp');
    }

    public function test_route_never_upscales(): void
    {
        $this->storePng('programs/small.png', 300, 200);

        $this->get('/img/960/programs/small.png')->assertOk();

        [$width] = getimagesizefromstring(Storage::disk('public')->get(ResponsiveImage::variantPath('programs/small.png', 960)));
        $this->assertSame(300, $width);
    }

    public function test_route_rejects_unknown_widths_traversal_and_missing_files(): void
    {
        $this->storePng('programs/a.png', 100, 100);

        $this->get('/img/123/programs/a.png')->assertNotFound();
        $this->get('/img/480/../.env')->assertNotFound();
        $this->get('/img/480/programs/missing.png')->assertNotFound();
        $this->get('/img/480/programs/a.txt')->assertNotFound();
    }

    public function test_corrupt_image_redirects_to_original(): void
    {
        Storage::disk('public')->put('programs/broken.png', 'not an image');

        $this->get('/img/480/programs/broken.png')
            ->assertRedirect(Storage::disk('public')->url('programs/broken.png'));
    }
}

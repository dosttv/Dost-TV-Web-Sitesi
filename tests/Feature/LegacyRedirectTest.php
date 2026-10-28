<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Episode;
use App\Models\Page;
use App\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegacyRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_tv_show_slug_redirects_permanently_to_program_show(): void
    {
        $program = Program::create([
            'name' => 'Hayat Güzeldir',
            'slug' => 'hayat-guzeldir',
            'show_on_public' => true,
            'status' => 'active',
        ]);

        $response = $this->get('/tv-show/hayat-guzeldir');

        $response->assertStatus(301);
        $response->assertRedirect(route('programs.show', $program));
    }

    public function test_tv_show_slug_with_trailing_slash_redirects_permanently_to_program_show(): void
    {
        $program = Program::create([
            'name' => 'Bir Garip Yolcu',
            'slug' => 'bir-garip-yolcu',
            'show_on_public' => true,
            'status' => 'active',
        ]);

        $response = $this->get('/tv-show/bir-garip-yolcu/');

        $response->assertStatus(301);
        $response->assertRedirect(route('programs.show', $program));
    }

    public function test_non_existent_tv_show_slug_redirects_permanently_to_programs_index(): void
    {
        $response = $this->get('/tv-show/olmayan-bir-program-123');

        $response->assertStatus(301);
        $response->assertRedirect(route('programs.index'));
    }

    public function test_bare_tv_show_url_redirects_permanently_to_programs_index(): void
    {
        $response = $this->get('/tv-show');

        $response->assertStatus(301);
        $response->assertRedirect(route('programs.index'));
    }

    public function test_existing_genre_redirects_permanently_to_programs_filtered_by_category(): void
    {
        $category = Category::create([
            'name' => 'Sohbet',
            'slug' => 'sohbet',
            'is_active' => true,
        ]);

        $response = $this->get('/tv-show-genre/sohbet');

        $response->assertStatus(301);
        $response->assertRedirect(route('programs.index', ['kategori' => 'sohbet']));
    }

    public function test_non_existent_genre_redirects_permanently_to_programs_index(): void
    {
        $response = $this->get('/tv-show-genre/bilinmeyen-kategori');

        $response->assertStatus(301);
        $response->assertRedirect(route('programs.index'));
    }

    public function test_legacy_category_url_redirects_to_category_filter(): void
    {
        $category = Category::create([
            'name' => 'Dini',
            'slug' => 'dini',
            'is_active' => true,
        ]);

        $response = $this->get('/category/dini');

        $response->assertStatus(301);
        $response->assertRedirect(route('programs.index', ['kategori' => 'dini']));
    }

    public function test_legacy_tag_url_redirects_to_programs_index(): void
    {
        $response = $this->get('/tag/ramazan');

        $response->assertStatus(301);
        $response->assertRedirect(route('programs.index'));
    }

    public function test_existing_episode_redirects_permanently_to_parent_program(): void
    {
        $program = Program::create([
            'name' => 'Ana Program',
            'slug' => 'ana-program',
            'show_on_public' => true,
            'status' => 'active',
        ]);

        Episode::create([
            'program_id' => $program->id,
            'title' => 'Eski Bölüm 1',
            'slug' => 'eski-bolum-slug-1',
            'is_active' => true,
        ]);

        $response = $this->get('/episode/eski-bolum-slug-1');

        $response->assertStatus(301);
        $response->assertRedirect(route('programs.show', $program));
    }

    public function test_existing_video_redirects_permanently_to_parent_program(): void
    {
        $program = Program::create([
            'name' => 'Video Program',
            'slug' => 'video-program',
            'show_on_public' => true,
            'status' => 'active',
        ]);

        Episode::create([
            'program_id' => $program->id,
            'title' => 'Eski Video 1',
            'slug' => 'eski-video-slug-1',
            'is_active' => true,
        ]);

        $response = $this->get('/video/eski-video-slug-1');

        $response->assertStatus(301);
        $response->assertRedirect(route('programs.show', $program));
    }

    public function test_video_url_with_feed_and_fuzzy_program_slug_resolves_to_program(): void
    {
        $program = Program::create([
            'name' => 'Çocuk ve Biz',
            'slug' => 'cocuk-ve-biz',
            'show_on_public' => true,
            'status' => 'active',
        ]);

        $response = $this->get('/video/cocuk-ve-biz-aile-ve-toplumda-nefret-1-bolum/feed/');

        $response->assertStatus(301);
        $response->assertRedirect(route('programs.show', $program));
    }

    public function test_videolar_multi_segment_resolves_to_program(): void
    {
        $program = Program::create([
            'name' => 'Bir Garip Yolcu',
            'slug' => 'bir-garip-yolcu',
            'show_on_public' => true,
            'status' => 'active',
        ]);

        $response = $this->get('/videolar/bir-garip-yolcu/nas-suresi-1-bolum/26.03.2015/30417');

        $response->assertStatus(301);
        $response->assertRedirect(route('programs.show', $program));
    }

    public function test_non_existent_episode_redirects_permanently_to_programs_index(): void
    {
        $response = $this->get('/episode/bulunamayan-bolum');

        $response->assertStatus(301);
        $response->assertRedirect(route('programs.index'));
    }

    public function test_direct_program_slug_fallback_on_root_redirects_to_program_show(): void
    {
        $program = Program::create([
            'name' => 'Tecdid-i İman',
            'slug' => 'tecdid-i-iman',
            'show_on_public' => true,
            'status' => 'active',
        ]);

        $response = $this->get('/tecdid-i-iman');

        $response->assertStatus(301);
        $response->assertRedirect(route('programs.show', $program));
    }

    public function test_paginated_program_archive_redirects_to_program_show(): void
    {
        $program = Program::create([
            'name' => 'Tecdid-i İman',
            'slug' => 'tecdid-i-iman',
            'show_on_public' => true,
            'status' => 'active',
        ]);

        $response = $this->get('/tecdid-i-iman/page/59/');

        $response->assertStatus(301);
        $response->assertRedirect(route('programs.show', $program));
    }

    public function test_numeric_old_post_id_redirects_to_home(): void
    {
        $this->get('/11')->assertStatus(301)->assertRedirect('/');
        $this->get('/331/')->assertStatus(301)->assertRedirect('/');
        $this->get('/17')->assertStatus(301)->assertRedirect('/');
    }

    public function test_old_asp_and_aspx_pages_redirect_to_relevant_destinations(): void
    {
        $this->get('/bizeulasin.aspx')->assertStatus(301)->assertRedirect('/iletisim');
        $this->get('/default.aspx')->assertStatus(301)->assertRedirect('/');
        $this->get('/Default.aspx')->assertStatus(301)->assertRedirect('/');
        $this->get('/Icerik.aspx')->assertStatus(301)->assertRedirect('/');
        $this->get('/program_detay.aspx?id=185')->assertStatus(301)->assertRedirect(route('programs.index'));
    }

    public function test_frequency_categories_redirect_to_kunye(): void
    {
        $response = $this->get('/categories/dost-tv-yeni-frekansi-ve-kanal-kurulumu/?order_post=latest');

        $response->assertStatus(301);
        $response->assertRedirect('/yayinci-kunye-bilgisi');
    }

    public function test_static_aliases_redirect_permanently(): void
    {
        $this->get('/hakkimizda')->assertStatus(301)->assertRedirect('/neden-dost-tv');
        $this->get('/kunye')->assertStatus(301)->assertRedirect('/yayinci-kunye-bilgisi');
        $this->get('/dost-fm')->assertStatus(301)->assertRedirect('/canli-radyo');
        $this->get('/dost-fm-canli')->assertStatus(301)->assertRedirect('/canli-radyo');
        $this->get('/yayin-akisi-deneme')->assertStatus(301)->assertRedirect('/yayin-akisi');
        $this->get('/canli-yayin-eski')->assertStatus(301)->assertRedirect('/canli-yayin');
        $this->get('/tv-shows')->assertStatus(301)->assertRedirect('/programlar');
        $this->get('/tv-programlari')->assertStatus(301)->assertRedirect('/programlar');
        $this->get('/programlarimiz')->assertStatus(301)->assertRedirect('/programlar');
        $this->get('/episodes')->assertStatus(301)->assertRedirect('/programlar');
        $this->get('/videos')->assertStatus(301)->assertRedirect('/programlar');
        $this->get('/insan-kaynaklari')->assertStatus(301)->assertRedirect('/iletisim');
        $this->get('/mukabele')->assertStatus(301)->assertRedirect('/programlar');
    }

    public function test_valid_page_renders_normally(): void
    {
        $page = Page::create([
            'title' => 'Neden Dost TV',
            'slug' => 'neden-dost-tv',
            'content' => 'Hakkımızda içeriği',
            'is_active' => true,
        ]);

        $response = $this->get('/neden-dost-tv');

        $response->assertOk();
        $response->assertSee('Neden Dost TV');
    }

    public function test_non_existent_arbitrary_url_returns_404_with_custom_view(): void
    {
        $response = $this->get('/bu-adres-kesinlikle-yok-12345');

        $response->assertStatus(404);
        $response->assertSee('Aradığınız Sayfa Bulunamadı');
        $response->assertSee('Canlı Yayın İzle');
        $response->assertSee('Yayın Akışı');
        $response->assertSee('Tüm Programlar');
    }
}

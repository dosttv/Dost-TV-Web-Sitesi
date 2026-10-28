<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegacySubdomainRedirectTest extends TestCase
{
    use RefreshDatabase;
    public function test_root_request_on_yeni_subdomain_redirects_permanently_to_dosttv_com(): void
    {
        $response = $this->get('https://yeni.dosttv.com/');

        $response->assertStatus(301);
        $response->assertRedirect('https://dosttv.com/');
    }

    public function test_canli_tv_request_on_yeni_subdomain_redirects_permanently_to_dosttv_com(): void
    {
        $response = $this->get('https://yeni.dosttv.com/canli-tv');

        $response->assertStatus(301);
        $response->assertRedirect('https://dosttv.com/canli-yayin');
    }

    public function test_canli_yayin_request_on_yeni_subdomain_redirects_directly_to_canli_yayin(): void
    {
        $response = $this->get('https://yeni.dosttv.com/canli-yayin');

        $response->assertStatus(301);
        $response->assertRedirect('https://dosttv.com/canli-yayin');
    }

    public function test_path_and_query_string_are_preserved_on_redirect(): void
    {
        $response = $this->get('https://yeni.dosttv.com/programlar?kategori=dini&sayfa=2');

        $response->assertStatus(301);
        $response->assertRedirect('https://dosttv.com/programlar?kategori=dini&sayfa=2');
    }

    public function test_normal_dosttv_com_domain_is_not_redirected(): void
    {
        $response = $this->get('https://dosttv.com/');

        $response->assertOk();
    }

    public function test_canonical_url_never_contains_yeni_subdomain(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertDontSee('yeni.dosttv.com');
    }
}

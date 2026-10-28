<?php

namespace App\Console\Commands;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Support\SiteCache;
use Illuminate\Console\Command;

class ImportLegacyFooterData extends Command
{
    protected $signature = 'footer:import-legacy-data {--dry-run : Previews the import without modifying the database}';

    protected $description = 'Imports legacy footer contact, social media, copyright data, and connects footer menus safely.';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $this->info($dryRun ? '🔍 Running Legacy Footer Data Import (DRY-RUN MODE)...' : '🚀 Running Legacy Footer Data Import...');

        $setting = SiteSetting::current();

        // 1. Discover legacy data from Pages, ThemeSettings, and defaults
        $legacyData = $this->discoverLegacyData();

        // 2. Prepare backfill payload (only empty target fields)
        $payload = [];
        $skippedFields = [];

        foreach ($legacyData as $field => $value) {
            $currentValue = $setting->{$field};

            // Field is considered empty if null, empty string, or default placeholder like '000000000' or 'ankara'
            $isEmpty = blank($currentValue) || in_array(trim((string) $currentValue), ['000000000', 'ankara'], true);

            if ($isEmpty && ! blank($value)) {
                $payload[$field] = $value;
            } elseif (! blank($currentValue)) {
                $skippedFields[$field] = $currentValue;
            }
        }

        // 3. Dry-Run Reporting
        $this->table(
            ['Field Name', 'Discovered Legacy Value', 'Current Target Status', 'Action'],
            collect($legacyData)->map(function ($value, $field) use ($payload, $skippedFields) {
                if (isset($payload[$field])) {
                    return [$field, $value, 'Empty', 'Import to SiteSetting'];
                }
                if (isset($skippedFields[$field])) {
                    return [$field, $value, 'Filled (' . $skippedFields[$field] . ')', 'Skip (Preserve Existing)'];
                }

                return [$field, $value ?: '-', 'Empty', 'No Source Data'];
            })->toArray()
        );

        if ($dryRun) {
            $this->warn('DRY-RUN completed. Database was NOT modified.');

            return Command::SUCCESS;
        }

        // 4. Perform actual database backfill
        if (! empty($payload)) {
            $setting->update($payload);
            $this->info('✅ SiteSetting footer fields updated with legacy data: ' . implode(', ', array_keys($payload)));
        } else {
            $this->comment('ℹ️ No empty SiteSetting fields required updating.');
        }

        // 5. Connect / Seed Footer Menus Idempotently
        $this->syncFooterMenus();

        // 6. Clear caches
        SiteCache::forgetSiteSetting();
        SiteCache::forgetAllMenus();

        $this->info('🎉 Legacy footer data import completed successfully!');

        return Command::SUCCESS;
    }

    private function discoverLegacyData(): array
    {
        $data = [
            'phone' => '+90 (312) 341 21 21',
            'email' => 'iletisim@dosttv.com',
            'address' => null,
            'kep_address' => 'dosttv@hs01.kep.tr',
            'facebook_url' => 'https://facebook.com/dosttv',
            'instagram_url' => 'https://instagram.com/dosttv',
            'x_url' => 'https://x.com/dosttv',
            'youtube_url' => 'https://www.youtube.com/@DostRadyoTV',
            'whatsapp_url' => null,
            'telegram_url' => null,
            'copyright_text' => '© {year} Dost TV. Tüm hakları saklıdır.',
            'footer_description' => 'Dost TV - Uydu üzerinden yayın yapan Türkçe tematik TV kanalı.',
            'footer_logo' => null,
        ];

        // Parse İletişim & Künye pages if available
        $iletisimPage = Page::firstWhere('slug', 'iletisim');
        if ($iletisimPage) {
            if (preg_match('/\((0312)\s*341\s*21\s*21\)/', $iletisimPage->content) || str_contains($iletisimPage->content, '341 21 21')) {
                $data['phone'] = '+90 (312) 341 21 21';
            }
            if (preg_match('/Zübeyde Hanım, İstanbul Cad\.[^<]+/u', $iletisimPage->content, $matches)) {
                $data['address'] = trim($matches[0]);
            }
            if (preg_match('/https:\/\/www\.youtube\.com\/@[A-Za-z0-9_.-]+/i', $iletisimPage->content, $matches)) {
                $data['youtube_url'] = trim($matches[0]);
            }
        }

        $kunyePage = Page::firstWhere('slug', 'yayinci-kunye-bilgisi');
        if ($kunyePage) {
            if (empty($data['phone']) && preg_match('/\+90\s*312\s*341\s*21\s*21/', $kunyePage->content)) {
                $data['phone'] = '+90 312 341 21 21';
            }
            if (empty($data['address']) && preg_match('/İstanbul Caddesi Devrez Sok No 1, İskitler\/Ankara/u', $kunyePage->content, $matches)) {
                $data['address'] = trim($matches[0]);
            }
        }

        if (empty($data['address'])) {
            $data['address'] = 'Zübeyde Hanım, İstanbul Cad., Devrez Sok. No:1, 06070 Altındağ/Ankara';
        }

        return $data;
    }

    private function syncFooterMenus(): void
    {
        // 1. Ensure footer_primary menu exists and has core items
        $footerPrimary = Menu::firstOrCreate(
            ['location' => 'footer_primary'],
            ['name' => 'Footer Ana Menü', 'is_active' => true]
        );

        $primaryItems = [
            ['title' => 'Canlı TV', 'url' => '/canli-yayin', 'item_type' => 'custom'],
            ['title' => 'Canlı Radyo', 'url' => '/canli-radyo', 'item_type' => 'custom'],
            ['title' => 'Yayın Akışı', 'url' => '/yayin-akisi', 'item_type' => 'custom'],
            ['title' => 'Programlar', 'url' => '/programlar', 'item_type' => 'custom'],
            ['title' => 'İletişim', 'url' => '/iletisim', 'item_type' => 'custom'],
        ];

        foreach ($primaryItems as $index => $itemData) {
            $exists = $footerPrimary->items()->where('title', $itemData['title'])->exists();
            if (! $exists) {
                MenuItem::create([
                    'menu_id' => $footerPrimary->id,
                    'title' => $itemData['title'],
                    'url' => $itemData['url'],
                    'item_type' => $itemData['item_type'],
                    'sort_order' => $index + 1,
                    'is_active' => true,
                    'show_on_desktop' => true,
                    'show_on_mobile' => true,
                ]);
            }
        }

        // 2. Ensure footer_legal menu exists and has core yasal items
        $footerLegal = Menu::firstOrCreate(
            ['location' => 'footer_legal'],
            ['name' => 'Yasal Menü', 'is_active' => true]
        );

        $legalItems = [
            ['title' => 'KVKK Aydınlatma Metni', 'slug' => 'kisisel-verilerin-korunmasi-ve-gizlilik-politikasi'],
            ['title' => 'Yayın İlkeleri', 'slug' => 'dost-tv-yayin-ilkeleri'],
            ['title' => 'Künye Bilgisi', 'slug' => 'yayinci-kunye-bilgisi'],
        ];

        foreach ($legalItems as $index => $itemData) {
            $exists = $footerLegal->items()->where('title', $itemData['title'])->exists();
            if (! $exists) {
                $page = Page::firstWhere('slug', $itemData['slug']);
                MenuItem::create([
                    'menu_id' => $footerLegal->id,
                    'title' => $itemData['title'],
                    'item_type' => $page ? 'page' : 'custom',
                    'linked_model_type' => $page ? 'page' : null,
                    'linked_model_id' => $page?->id,
                    'url' => $page ? null : '/' . $itemData['slug'],
                    'sort_order' => $index + 1,
                    'is_active' => true,
                    'show_on_desktop' => true,
                    'show_on_mobile' => true,
                ]);
            }
        }
    }
}

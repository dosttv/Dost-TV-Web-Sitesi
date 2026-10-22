<?php

namespace App\Models;

use App\Support\SiteCache;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class SiteSetting extends Model
{
    protected $fillable = [
        'site_name',
        'logo',
        'favicon',
        'logo_alt_text',
        'live_button_text',
        'live_button_is_visible',
        'search_is_visible',
        'header_is_sticky',
        'footer_logo',
        'footer_description',
        'phone',
        'email',
        'address',
        'kep_address',
        'facebook_url',
        'instagram_url',
        'x_url',
        'youtube_url',
        'whatsapp_url',
        'telegram_url',
        'copyright_text',
        'recommended_sites',
        'footer_show_socials',
        'footer_show_contact',
        'footer_show_bank_link',
        'custom_css',
        'homepage_sections',
        'youtube_page_settings',
        'live_tv_type',
        'live_tv_url',
        'live_tv_title',
        'live_tv_description',
        'live_tv_backup_url',
        'live_tv_poster',
        'live_tv_maintenance_message',
        'live_tv_error_message',
        'live_tv_is_active',
        'live_tv_is_public',
        'radio_stream_url',
        'radio_name',
        'radio_description',
        'radio_backup_url',
        'radio_image',
        'radio_maintenance_message',
        'radio_error_message',
        'radio_is_active',
        'radio_is_public',
        'title_suffix',
        'system_email',
        'default_meta_description',
        'default_og_image',
        'search_engine_indexing',
        'canonical_url_mode',
        'google_analytics_id',
        'google_tag_manager_id',
        'google_site_verification',
        'custom_head_code',
        'custom_body_code',
        'header_responsive_settings',
        'theme_settings',
        'mail_enabled',
        'mail_host',
        'mail_port',
        'mail_encryption',
        'mail_username',
        'mail_password',
        'mail_from_address',
        'mail_from_name',
    ];

    protected $hidden = [
        'mail_password',
    ];

    protected $casts = [
        'live_button_is_visible' => 'boolean',
        'search_is_visible' => 'boolean',
        'header_is_sticky' => 'boolean',
        'footer_show_socials' => 'boolean',
        'footer_show_contact' => 'boolean',
        'footer_show_bank_link' => 'boolean',
        'live_tv_is_active' => 'boolean',
        'live_tv_is_public' => 'boolean',
        'radio_is_active' => 'boolean',
        'radio_is_public' => 'boolean',
        'search_engine_indexing' => 'boolean',
        'homepage_sections' => 'array',
        'youtube_page_settings' => 'array',
        'header_responsive_settings' => 'array',
        'recommended_sites' => 'array',
        'theme_settings' => 'array',
        'mail_enabled' => 'boolean',
        'mail_port' => 'integer',
    ];

    /**
     * Encrypted at rest; tolerates values that can no longer be decrypted
     * (e.g. after an APP_KEY rotation) instead of breaking every page.
     */
    protected function mailPassword(): Attribute
    {
        return Attribute::make(
            get: function (?string $value) {
                if (blank($value)) {
                    return null;
                }

                try {
                    return Crypt::decryptString($value);
                } catch (DecryptException $e) {
                    Log::warning('SiteSetting.mail_password could not be decrypted (APP_KEY mismatch or corrupted data); treating as empty.');

                    return null;
                }
            },
            set: fn (?string $value) => blank($value) ? null : Crypt::encryptString($value),
        );
    }

    public static function getDefaultThemeSettings(): array
    {
        return [
            'mode' => 'dark',
            'dark' => [
                'background' => '#030712',
                'surface' => '#0f172a',
                'accent' => '#f43f5e',
            ],
            'light' => [
                'background' => '#f8fafc',
                'surface' => '#ffffff',
                'accent' => '#e11d48',
            ],
            'page_gradient' => [
                'enabled' => false,
                'top_dark_hold' => 25,
                'top_fade_start' => 25,
                'light_zone_start' => 50,
                'light_zone_end' => 65,
                'bottom_fade_start' => 65,
                'bottom_dark_at' => 100,
                'light_color' => '#F5F2E8',
                'bottom_color' => '#030712',
            ],
        ];
    }

    public function getNormalizedThemeSettingsAttribute(): array
    {
        $raw = $this->theme_settings;
        $defaults = static::getDefaultThemeSettings();

        if (! is_array($raw)) {
            return $defaults;
        }

        $rawGradient = (array) ($raw['page_gradient'] ?? []);
        $gradient = [
            'enabled' => filter_var($rawGradient['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'top_dark_hold' => (int) ($rawGradient['top_dark_hold'] ?? $rawGradient['dark_hold_until'] ?? 25),
            'top_fade_start' => (int) ($rawGradient['top_fade_start'] ?? $rawGradient['fade_start'] ?? 25),
            'light_zone_start' => (int) ($rawGradient['light_zone_start'] ?? 50),
            'light_zone_end' => (int) ($rawGradient['light_zone_end'] ?? 65),
            'bottom_fade_start' => (int) ($rawGradient['bottom_fade_start'] ?? $rawGradient['light_zone_end'] ?? 65),
            'bottom_dark_at' => (int) ($rawGradient['bottom_dark_at'] ?? $rawGradient['light_end'] ?? 100),
            'light_color' => ! empty($rawGradient['light_color']) ? $rawGradient['light_color'] : '#F5F2E8',
            'bottom_color' => ! empty($rawGradient['bottom_color']) ? $rawGradient['bottom_color'] : '#030712',
        ];

        return [
            'mode' => $raw['mode'] ?? $defaults['mode'],
            'dark' => array_merge($defaults['dark'], (array) ($raw['dark'] ?? [])),
            'light' => array_merge($defaults['light'], (array) ($raw['light'] ?? [])),
            'page_gradient' => array_merge($defaults['page_gradient'], $gradient),
        ];
    }

    public function renderThemeCss(?array $themeSettings = null): string
    {
        $settings = $themeSettings ?? $this->normalized_theme_settings;
        $defaults = static::getDefaultThemeSettings();

        $dark = array_merge($defaults['dark'], (array) ($settings['dark'] ?? []));
        $light = array_merge($defaults['light'], (array) ($settings['light'] ?? []));

        $rawGradient = (array) ($settings['page_gradient'] ?? []);
        $pageGradient = array_merge($defaults['page_gradient'], [
            'enabled' => filter_var($rawGradient['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'top_dark_hold' => (int) ($rawGradient['top_dark_hold'] ?? $rawGradient['dark_hold_until'] ?? 25),
            'top_fade_start' => (int) ($rawGradient['top_fade_start'] ?? $rawGradient['fade_start'] ?? 25),
            'light_zone_start' => (int) ($rawGradient['light_zone_start'] ?? 50),
            'light_zone_end' => (int) ($rawGradient['light_zone_end'] ?? 65),
            'bottom_fade_start' => (int) ($rawGradient['bottom_fade_start'] ?? $rawGradient['light_zone_end'] ?? 65),
            'bottom_dark_at' => (int) ($rawGradient['bottom_dark_at'] ?? $rawGradient['light_end'] ?? 100),
            'light_color' => ! empty($rawGradient['light_color']) ? $rawGradient['light_color'] : '#F5F2E8',
            'bottom_color' => ! empty($rawGradient['bottom_color']) ? $rawGradient['bottom_color'] : '#030712',
        ]);

        $darkBg = $dark['background'] ?: '#030712';
        $darkSurface = $dark['surface'] ?: '#0f172a';
        $darkAccent = $dark['accent'] ?: '#f43f5e';

        $lightBg = $light['background'] ?: '#f8fafc';
        $lightSurface = $light['surface'] ?: '#ffffff';
        $lightAccent = $light['accent'] ?: '#e11d48';

        $gradientEnabled = $pageGradient['enabled'];
        $gradientEnabledVal = $gradientEnabled ? 1 : 0;
        $topDarkHold = $pageGradient['top_dark_hold'];
        $topFadeStart = $pageGradient['top_fade_start'];
        $lightZoneStart = $pageGradient['light_zone_start'];
        $lightZoneEnd = $pageGradient['light_zone_end'];
        $bottomFadeStart = $pageGradient['bottom_fade_start'];
        $bottomDarkAt = $pageGradient['bottom_dark_at'];
        $lightColor = $pageGradient['light_color'];
        $bottomColor = $pageGradient['bottom_color'];

        $gradientBackground = $gradientEnabled
            ? "linear-gradient(to bottom, var(--color-bg, {$darkBg}) 0%, var(--color-bg, {$darkBg}) {$topDarkHold}%, color-mix(in srgb, var(--color-bg, {$darkBg}) 85%, {$lightColor} 15%) {$topFadeStart}%, {$lightColor} {$lightZoneStart}%, {$lightColor} {$lightZoneEnd}%, color-mix(in srgb, {$lightColor} 50%, {$bottomColor} 50%) {$bottomFadeStart}%, {$bottomColor} {$bottomDarkAt}%, {$bottomColor} 100%)"
            : "var(--color-bg, {$darkBg})";

        return "
            :root {
                --color-bg: {$darkBg};
                --color-surface: {$darkSurface};
                --color-surface-2: #1e293b;
                --color-accent: {$darkAccent};
                --color-text: #f8fafc;
                --color-text-muted: #94a3b8;
                --color-border: rgba(255, 255, 255, 0.1);
                --color-hover: rgba(244, 63, 94, 0.15);
                --color-input: {$darkSurface};
                --color-card: {$darkSurface};

                --gradient-enabled: {$gradientEnabledVal};
                --gradient-background: {$gradientBackground};
                --gradient-top-dark-hold: {$topDarkHold}%;
                --gradient-top-fade-start: {$topFadeStart}%;
                --gradient-light-zone-start: {$lightZoneStart}%;
                --gradient-light-zone-end: {$lightZoneEnd}%;
                --gradient-bottom-fade-start: {$bottomFadeStart}%;
                --gradient-bottom-dark-at: {$bottomDarkAt}%;
                --gradient-light-color: {$lightColor};
                --gradient-bottom-color: {$bottomColor};

                /* Backward compatibility aliases */
                --gradient-dark-hold: {$topDarkHold}%;
                --gradient-fade-start: {$topFadeStart}%;
                --gradient-light-end: {$bottomDarkAt}%;
            }

            [data-theme='dark'], .dark {
                --color-bg: {$darkBg};
                --color-surface: {$darkSurface};
                --color-surface-2: #1e293b;
                --color-accent: {$darkAccent};
                --color-text: #f8fafc;
                --color-text-muted: #94a3b8;
                --color-border: rgba(255, 255, 255, 0.1);
                --color-hover: rgba(244, 63, 94, 0.15);
                --color-input: {$darkSurface};
                --color-card: {$darkSurface};
                color-scheme: dark;
            }

            [data-theme='light'], .light {
                --color-bg: {$lightBg};
                --color-surface: {$lightSurface};
                --color-surface-2: #f1f5f9;
                --color-accent: {$lightAccent};
                --color-text: #0f172a;
                --color-text-muted: #64748b;
                --color-border: rgba(0, 0, 0, 0.08);
                --color-hover: rgba(225, 29, 72, 0.1);
                --color-input: {$lightSurface};
                --color-card: {$lightSurface};
                color-scheme: light;
            }

            @media (prefers-color-scheme: dark) {
                [data-theme='system'] {
                    --color-bg: {$darkBg};
                    --color-surface: {$darkSurface};
                    --color-surface-2: #1e293b;
                    --color-accent: {$darkAccent};
                    --color-text: #f8fafc;
                    --color-text-muted: #94a3b8;
                    --color-border: rgba(255, 255, 255, 0.1);
                    --color-hover: rgba(244, 63, 94, 0.15);
                    --color-input: {$darkSurface};
                    --color-card: {$darkSurface};
                    color-scheme: dark;
                }
            }

            @media (prefers-color-scheme: light) {
                [data-theme='system'] {
                    --color-bg: {$lightBg};
                    --color-surface: {$lightSurface};
                    --color-surface-2: #f1f5f9;
                    --color-accent: {$lightAccent};
                    --color-text: #0f172a;
                    --color-text-muted: #64748b;
                    --color-border: rgba(0, 0, 0, 0.08);
                    --color-hover: rgba(225, 29, 72, 0.1);
                    --color-input: {$lightSurface};
                    --color-card: {$lightSurface};
                }
            ";
    }

    public static function getDefaultHeaderResponsiveSettings(): array
    {
        return [
            'desktop' => [
                'header_height' => 80,
                'logo_width' => 140,
                'nav_icon_size' => 20,
                'live_button_height' => 40,
                'live_button_font_size' => 14,
                'header_horizontal_padding' => 24,
            ],
            'tablet' => [
                'header_height' => 72,
                'logo_width' => 120,
                'nav_icon_size' => 18,
                'live_button_height' => 36,
                'live_button_font_size' => 13,
                'header_horizontal_padding' => 16,
            ],
            'mobile' => [
                'header_height' => 60,
                'logo_width' => 105,
                'nav_icon_size' => 18,
                'live_button_height' => 32,
                'live_button_font_size' => 12,
                'header_horizontal_padding' => 12,
            ],
        ];
    }

    public function getNormalizedHeaderResponsiveSettingsAttribute(): array
    {
        $raw = $this->header_responsive_settings;
        $defaults = static::getDefaultHeaderResponsiveSettings();

        if (! is_array($raw)) {
            return $defaults;
        }

        return [
            'desktop' => array_merge($defaults['desktop'], (array) ($raw['desktop'] ?? [])),
            'tablet' => array_merge($defaults['tablet'], (array) ($raw['tablet'] ?? [])),
            'mobile' => array_merge($defaults['mobile'], (array) ($raw['mobile'] ?? [])),
        ];
    }

    public static function getDefaultYoutubePageSettings(): array
    {
        return [
            'page_title' => 'DOST TV YouTube Kanalları',
            'show_page_title' => true,
            'page_subtitle' => 'Tüm programlarımızı, sohbetlerimizi ve özel videolarımızı YouTube kanallarımız üzerinden takip edebilirsiniz.',
            'show_page_subtitle' => true,
            'video_section_title' => 'YouTube Videolarımız',
            'show_video_section_title' => true,
            'shelves_view_mode' => 'shelf',
            'shelves_rows' => 1,
            'shelves_columns' => 4,
            'shelves_gap_size' => 'md',
            'shelves_show_arrows' => true,
            'video_collection_shelves' => [],
            'category_shelves' => [],
        ];
    }

    public function getNormalizedYoutubePageSettingsAttribute(): array
    {
        $raw = $this->youtube_page_settings;
        $defaults = static::getDefaultYoutubePageSettings();

        if (! is_array($raw)) {
            return $defaults;
        }

        return [
            'page_title' => $raw['page_title'] ?? ($raw['title'] ?? $defaults['page_title']),
            'show_page_title' => (bool) ($raw['show_page_title'] ?? ($raw['show_title'] ?? $defaults['show_page_title'])),
            'page_subtitle' => $raw['page_subtitle'] ?? ($raw['subtitle'] ?? $defaults['page_subtitle']),
            'show_page_subtitle' => (bool) ($raw['show_page_subtitle'] ?? ($raw['show_subtitle'] ?? $defaults['show_page_subtitle'])),
            'video_section_title' => $raw['video_section_title'] ?? $defaults['video_section_title'],
            'show_video_section_title' => (bool) ($raw['show_video_section_title'] ?? $defaults['show_video_section_title']),
            'shelves_view_mode' => $raw['shelves_view_mode'] ?? $defaults['shelves_view_mode'],
            'shelves_rows' => (int) ($raw['shelves_rows'] ?? $defaults['shelves_rows']),
            'shelves_columns' => (int) ($raw['shelves_columns'] ?? $defaults['shelves_columns']),
            'shelves_gap_size' => $raw['shelves_gap_size'] ?? $defaults['shelves_gap_size'],
            'shelves_show_arrows' => (bool) ($raw['shelves_show_arrows'] ?? $defaults['shelves_show_arrows']),
            'video_collection_shelves' => is_array($raw['video_collection_shelves'] ?? null) ? $raw['video_collection_shelves'] : [],
            'category_shelves' => is_array($raw['category_shelves'] ?? null) ? $raw['category_shelves'] : [],
        ];
    }

    public const CANONICAL_HOMEPAGE_SECTIONS = [
        'hero' => 'Hero Slider',
        'live_intro' => 'Canlı Yayın ve Tanıtım',
        'today_schedule' => 'Bugünün Yayın Akışı',
        'featured_programs' => 'Öne Çıkan Programlar',
    ];

    public static function getDefaultHomepageSections(): array
    {
        return [
            ['key' => 'hero', 'visible' => true],
            ['key' => 'live_intro', 'visible' => true],
            ['key' => 'today_schedule', 'visible' => true],
            ['key' => 'featured_programs', 'visible' => true],
        ];
    }

    public function getNormalizedHomepageSectionsAttribute(): array
    {
        $raw = $this->homepage_sections;
        if (! is_array($raw)) {
            return static::getDefaultHomepageSections();
        }

        $canonicalKeys = array_keys(self::CANONICAL_HOMEPAGE_SECTIONS);
        $normalized = [];
        $seenKeys = [];

        foreach ($raw as $item) {
            if (! is_array($item) || ! isset($item['key'])) {
                continue;
            }

            $key = (string) $item['key'];
            if (! in_array($key, $canonicalKeys, true) || isset($seenKeys[$key])) {
                continue;
            }

            $seenKeys[$key] = true;
            $normalized[] = [
                'key' => $key,
                'visible' => (bool) ($item['visible'] ?? true),
            ];
        }

        foreach ($canonicalKeys as $canonicalKey) {
            if (! isset($seenKeys[$canonicalKey])) {
                $normalized[] = [
                    'key' => $canonicalKey,
                    'visible' => true,
                ];
            }
        }

        return $normalized;
    }

    protected static function booted(): void
    {
        static::saved(function () {
            SiteCache::forgetSiteSetting();
        });
    }

    public static function current(): self
    {
        return static::query()->first() ?? static::query()->forceCreate([
            'id' => 1,
            'site_name' => 'Dost TV',
            'title_suffix' => '| DOST TV',
            'search_engine_indexing' => true,
            'canonical_url_mode' => 'current_url',
            'live_tv_type' => 'iframe',
            'live_button_text' => 'Canlı İzle',
            'live_button_is_visible' => true,
            'search_is_visible' => true,
            'header_is_sticky' => true,
            'footer_show_socials' => true,
            'footer_show_contact' => true,
            'footer_show_bank_link' => true,
        ]);
    }
}

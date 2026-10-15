<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Single source of truth for every cache key used by the dynamic site
 * management layer (menus, theme settings, category tree, site settings, homepage).
 */
class SiteCache
{
    private const MENU_PREFIX = 'site:menu:';

    private const THEME_KEY = 'site:theme:active';

    private const SITE_SETTING_KEY = 'site:setting:active';

    private const CATEGORY_TREE_KEY = 'site:categories:tree';

    private const HOMEPAGE_SECTIONS_KEY = 'site:homepage:sections';

    private const HOME_BANNERS_KEY = 'site:homepage:banners';

    private const HOME_FEATURED_PROGRAMS_KEY = 'site:homepage:featured_programs';

    private const HOME_HERO_PROGRAMS_KEY = 'site:homepage:hero_programs';

    private const PROGRAM_DETAIL_LAYOUT_KEY = 'site:program_detail:layout';

    private const TTL_SECONDS = 86400;

    public static function getHomepageTtl(): int
    {
        return (int) config('site.homepage_cache_ttl', 1800);
    }

    public static function menuKey(string $location): string
    {
        return self::MENU_PREFIX.$location;
    }

    public static function rememberMenu(string $location, Closure $callback): mixed
    {
        return Cache::remember(self::menuKey($location), self::TTL_SECONDS, $callback);
    }

    public static function forgetMenu(string $location): void
    {
        Cache::forget(self::menuKey($location));
    }

    public static function forgetAllMenus(): void
    {
        Cache::forget(self::menuKey('header_primary'));
        Cache::forget(self::menuKey('header_secondary'));
        Cache::forget(self::menuKey('mobile'));
        Cache::forget(self::menuKey('footer_primary'));
        Cache::forget(self::menuKey('footer_secondary'));
        Cache::forget(self::menuKey('footer_legal'));
        Cache::forget(self::menuKey('sidebar'));
    }

    public static function rememberTheme(Closure $callback): mixed
    {
        return Cache::remember(self::THEME_KEY, self::TTL_SECONDS, $callback);
    }

    public static function forgetTheme(): void
    {
        Cache::forget(self::THEME_KEY);
    }

    public static function forgetSiteSetting(): void
    {
        Cache::forget(self::SITE_SETTING_KEY);
    }

    public static function forgetHomepage(): void
    {
        Cache::forget(self::HOMEPAGE_SECTIONS_KEY);
        Cache::forget(self::HOME_BANNERS_KEY);
        Cache::forget(self::HOME_FEATURED_PROGRAMS_KEY);
        self::forgetHomeHeroPrograms();
    }

    public static function rememberHomeBanners(Closure $callback): mixed
    {
        return Cache::remember(self::HOME_BANNERS_KEY, self::getHomepageTtl(), $callback);
    }

    public static function forgetHomeBanners(): void
    {
        Cache::forget(self::HOME_BANNERS_KEY);
    }

    public static function rememberHomeFeaturedPrograms(Closure $callback): mixed
    {
        return Cache::remember(self::HOME_FEATURED_PROGRAMS_KEY, self::getHomepageTtl(), $callback);
    }

    public static function forgetHomeFeaturedPrograms(): void
    {
        Cache::forget(self::HOME_FEATURED_PROGRAMS_KEY);
    }

    public static function rememberHomeHeroPrograms(Closure $callback): mixed
    {
        $dateKey = now()->format('Y-m-d');
        return Cache::remember(self::HOME_HERO_PROGRAMS_KEY.':'.$dateKey, self::getHomepageTtl(), $callback);
    }

    public static function forgetHomeHeroPrograms(): void
    {
        $baseDate = now();
        for ($i = -2; $i <= 7; $i++) {
            Cache::forget(self::HOME_HERO_PROGRAMS_KEY.':'.$baseDate->copy()->addDays($i)->format('Y-m-d'));
        }
        Cache::forget(self::HOME_HERO_PROGRAMS_KEY);
    }

    public static function rememberCategoryTree(Closure $callback): mixed
    {
        return Cache::remember(self::CATEGORY_TREE_KEY, self::TTL_SECONDS, $callback);
    }

    public static function forgetCategoryTree(): void
    {
        Cache::forget(self::CATEGORY_TREE_KEY);
    }

    public static function rememberProgramDetailLayout(Closure $callback): mixed
    {
        return Cache::remember(self::PROGRAM_DETAIL_LAYOUT_KEY, self::getHomepageTtl(), $callback);
    }

    public static function forgetProgramDetailLayout(): void
    {
        Cache::forget(self::PROGRAM_DETAIL_LAYOUT_KEY);
    }
}

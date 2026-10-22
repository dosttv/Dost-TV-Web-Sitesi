<?php

namespace App\View\Components\Site;

use App\Models\SiteSetting;
use App\Services\Menu\MenuService;
use App\Services\Menu\ProgramMegaMenuService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\View\Component;

class Menu extends Component
{
    public Collection $items;
    public array $megaMenuData;

    public function __construct(
        public string $location = 'header_primary',
        public ?string $logoUrl = null,
        public ?string $logoAltText = null,
        public bool $preview = false,
        ?MenuService $menuService = null,
        ?ProgramMegaMenuService $megaMenuService = null
    ) {
        $service = $menuService ?? app(MenuService::class);
        $megaService = $megaMenuService ?? app(ProgramMegaMenuService::class);

        $this->items = $service->forLocation($location);
        $this->megaMenuData = $megaService->getMenuData();
    }

    public function render(): View
    {
        $siteSettings = SiteSetting::current();
        $liveButtonVisible = ($siteSettings->live_button_is_visible ?? true) && ($siteSettings->live_tv_is_public ?? true);

        $logoUrl = $this->logoUrl;
        if (empty($logoUrl) && !empty($siteSettings->logo)) {
            $rawLogo = is_array($siteSettings->logo) ? reset($siteSettings->logo) : $siteSettings->logo;
            if (is_string($rawLogo) && !empty(trim($rawLogo))) {
                $logoUrl = \App\Support\ResponsiveImage::url($rawLogo, 640);
            }
        }

        $logoAltText = $this->logoAltText ?? ($siteSettings->logo_alt_text ?: ($siteSettings->site_name ?? 'Dost TV'));

        return view('components.site.menu', [
            'liveButtonVisible' => $liveButtonVisible,
            'logoUrl' => $logoUrl,
            'logoAltText' => $logoAltText,
            'preview' => $this->preview,
            'megaMenuCategories' => $this->megaMenuData['categories'] ?? collect(),
            'megaMenuCategoryDetails' => $this->megaMenuData['category_details'] ?? [],
        ]);
    }
}

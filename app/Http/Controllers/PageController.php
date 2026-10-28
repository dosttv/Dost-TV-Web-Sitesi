<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Page;
use App\Models\Program;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class PageController extends Controller
{
    public function show(mixed $page): View|RedirectResponse
    {
        $slug = $page instanceof Page ? $page->slug : (string) $page;
        $pageModel = $page instanceof Page ? $page : Page::where('slug', $slug)->first();

        if ($pageModel) {
            $isPreviewActive = false;
            $previewToken = request('page_preview_token') ?? session('page_preview_token');

            if ($previewToken && auth()->check() && auth()->user()?->hasAnyRole(['super_admin', 'administrator', 'designer', 'editor'])) {
                $previewData = Cache::get('page_preview_' . $previewToken) ?? session('page_preview_data');
                if ($previewData && is_array($previewData)) {
                    $previewPageId = $previewData['page_id'] ?? null;
                    $previewSlug = $previewData['page_slug'] ?? null;

                    if ($previewPageId === $pageModel->id || $previewSlug === $pageModel->slug) {
                        $isPreviewActive = true;
                        session()->put('page_preview_token', $previewToken);
                        session()->put('page_preview_data', $previewData);
                    }
                }
            }

            return view('pages.show', [
                'page' => $pageModel,
                'isPreviewActive' => $isPreviewActive,
            ]);
        }

        // 1. Program kontrolü (Örn: /tecdid-i-iman -> /programlar/tecdid-i-iman)
        $program = Cache::remember("page_prog_fallback_{$slug}", 3600, function () use ($slug) {
            return Program::query()
                ->where('slug', $slug)
                ->where('is_active', true)
                ->where('show_on_public', true)
                ->where('status', '!=', 'archived')
                ->first(['id', 'slug']);
        });

        if ($program) {
            return redirect()->route('programs.show', ['program' => $program->slug], 301);
        }

        // 2. Kategori kontrolü (Örn: /sohbet -> /programlar?kategori=sohbet)
        $category = Cache::remember("page_cat_fallback_{$slug}", 3600, function () use ($slug) {
            return Category::query()
                ->where('slug', $slug)
                ->where('is_active', true)
                ->first(['id', 'slug']);
        });

        if ($category) {
            return redirect()->to(route('programs.index', ['kategori' => $category->slug]), 301);
        }

        abort(404);
    }
}

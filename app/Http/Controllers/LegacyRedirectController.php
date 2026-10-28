<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Episode;
use App\Models\Program;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class LegacyRedirectController extends Controller
{
    /**
     * Eski sitedeki /tv-show/{slug} adreslerini yeni /programlar/{slug} adresine kalıcı (301) yönlendirir.
     * Program bulunamazsa /programlar sayfasına yönlendirir.
     */
    public function tvShow(?string $slug = null): RedirectResponse
    {
        $cleanSlug = $this->cleanSlug($slug);

        if ($cleanSlug === '') {
            return redirect()->route('programs.index', [], 301);
        }

        $program = Cache::remember("legacy_tv_show_{$cleanSlug}", 3600, function () use ($cleanSlug) {
            return Program::query()
                ->where('slug', $cleanSlug)
                ->where('is_active', true)
                ->where('show_on_public', true)
                ->where('status', '!=', 'archived')
                ->first(['id', 'slug']);
        });

        if ($program) {
            return redirect()->route('programs.show', ['program' => $program->slug], 301);
        }

        // Program bulunamazsa prefix/arama dene
        $matchedProgSlug = $this->resolveProgramSlugFromVideo($cleanSlug);
        if ($matchedProgSlug) {
            return redirect()->route('programs.show', ['program' => $matchedProgSlug], 301);
        }

        return redirect()->route('programs.index', [], 301);
    }

    /**
     * Eski sitedeki /tv-show-genre/{slug} ve /category/{slug} adreslerini
     * /programlar?kategori={slug} adresine kalıcı (301) yönlendirir.
     */
    public function tvShowGenre(?string $slug = null): RedirectResponse
    {
        $cleanSlug = $this->cleanSlug($slug);

        if ($cleanSlug === '') {
            return redirect()->route('programs.index', [], 301);
        }

        $category = Cache::remember("legacy_genre_{$cleanSlug}", 3600, function () use ($cleanSlug) {
            return Category::query()
                ->where('slug', $cleanSlug)
                ->where('is_active', true)
                ->first(['id', 'slug']);
        });

        if ($category) {
            return redirect()->to(route('programs.index', ['kategori' => $category->slug]), 301);
        }

        return redirect()->route('programs.index', [], 301);
    }

    /**
     * Eski WordPress categories/... URL'leri (ör. frekans sayfası veya kategori arşivleri)
     */
    public function categories(?string $slug = null): RedirectResponse
    {
        $cleanSlug = $this->cleanSlug($slug);

        if (str_contains($cleanSlug, 'frekans') || str_contains($cleanSlug, 'kurulum')) {
            return redirect()->to('/yayinci-kunye-bilgisi', 301);
        }

        return $this->tvShowGenre($slug);
    }

    /**
     * Eski sitedeki /video/{slug} ve /videolar/{slug} adreslerini ilgili programın
     * detay sayfasına veya genel /programlar sayfasına kalıcı (301) yönlendirir.
     */
    public function episodeOrVideo(?string $slug = null): RedirectResponse
    {
        $cleanSlug = $this->cleanSlug($slug);

        if ($cleanSlug === '') {
            return redirect()->route('programs.index', [], 301);
        }

        $programSlug = $this->resolveProgramSlugFromVideo($cleanSlug);

        if ($programSlug) {
            return redirect()->route('programs.show', ['program' => $programSlug], 301);
        }

        return redirect()->route('programs.index', [], 301);
    }

    /**
     * Eski sayfalama URL'leri (ör. /mukabele/page/2/ veya /tecdid-i-iman/page/59/)
     */
    public function paginatedArchive(string $slug, ?string $page = null): RedirectResponse
    {
        $cleanSlug = $this->cleanSlug($slug);

        $program = Program::query()
            ->where('slug', $cleanSlug)
            ->where('is_active', true)
            ->where('show_on_public', true)
            ->first(['id', 'slug']);

        if ($program) {
            return redirect()->route('programs.show', ['program' => $program->slug], 301);
        }

        $category = Category::query()
            ->where('slug', $cleanSlug)
            ->where('is_active', true)
            ->first(['id', 'slug']);

        if ($category) {
            return redirect()->to(route('programs.index', ['kategori' => $category->slug]), 301);
        }

        return redirect()->route('programs.index', [], 301);
    }

    /**
     * Eski ASP/ASPX ve PHP sayfalarını yeni karşılıklarına kalıcı 301 yönlendirir.
     */
    public function legacyScript(Request $request): RedirectResponse
    {
        $path = strtolower(trim($request->path(), '/'));

        if (str_contains($path, 'bizeulasin') || str_contains($path, 'insan-kaynaklari')) {
            return redirect()->to('/iletisim', 301);
        }

        if (str_contains($path, 'program_detay') || str_contains($path, 'dost_online')) {
            return redirect()->route('programs.index', [], 301);
        }

        return redirect()->to('/', 301);
    }

    /**
     * Video/Bölüm slug'ından en uygun Program slug'ını çözer.
     */
    protected function resolveProgramSlugFromVideo(string $slug): ?string
    {
        return Cache::remember("legacy_video_prog_{$slug}", 3600, function () use ($slug) {
            // 1. Bölüm veritabanında tam slug eşleşmesi
            $episode = Episode::query()
                ->with(['program' => fn ($q) => $q->select(['id', 'slug', 'is_active', 'show_on_public', 'status'])])
                ->where('slug', $slug)
                ->first(['id', 'program_id', 'slug']);

            if ($episode && $episode->program && $episode->program->is_active && $episode->program->show_on_public && $episode->program->status !== 'archived') {
                return $episode->program->slug;
            }

            // 2. Program veritabanında tam slug eşleşmesi
            $program = Program::query()
                ->where('slug', $slug)
                ->where('is_active', true)
                ->where('show_on_public', true)
                ->where('status', '!=', 'archived')
                ->first(['id', 'slug']);

            if ($program) {
                return $program->slug;
            }

            // 3. Program slug'ı video slug'ının başında veya içinde geçiyor mu?
            // Uzun slug'lar önce eşleşecek şekilde sıralı
            $programSlugs = Cache::remember('active_program_slugs_sorted_by_len', 3600, function () {
                $slugs = Program::query()
                    ->where('is_active', true)
                    ->where('show_on_public', true)
                    ->where('status', '!=', 'archived')
                    ->pluck('slug')
                    ->toArray();

                usort($slugs, fn ($a, $b) => strlen($b) <=> strlen($a));
                return $slugs;
            });

            // İlk segmenti al (videolar/merak-ettiklerimiz/... veya slug-foo-bar)
            $firstSegment = explode('/', $slug)[0];

            foreach ($programSlugs as $pSlug) {
                if ($firstSegment === $pSlug || str_starts_with($firstSegment, $pSlug . '-') || str_contains($firstSegment, '-' . $pSlug)) {
                    return $pSlug;
                }
            }

            return null;
        });
    }

    /**
     * URL slug'ını temizler (/feed, /page/X, uzantılar ve fazlalıkları siler).
     */
    protected function cleanSlug(?string $slug): string
    {
        if ($slug === null) {
            return '';
        }

        $clean = preg_replace('#/feed/?$#i', '', $slug);
        $clean = preg_replace('#/page/\d+/?$#i', '', $clean);
        $clean = preg_replace('#\.(html|htm|php|aspx?)$#i', '', $clean);

        return trim($clean, '/');
    }
}

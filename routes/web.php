<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\LiveController;
use App\Http\Controllers\LegacyRedirectController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProgramController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\InstagramVideoController;
use App\Http\Controllers\ProgramCollectionController;
use App\Http\Controllers\VideoCollectionController;
use App\Http\Controllers\YoutubeChannelController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/login', fn () => redirect()->route('filament.admin.auth.login'))->name('login');

Route::middleware(['web', 'auth'])->get('/admin', function () {
    $user = auth()->user();
    if (! $user || ! $user->is_active || $user->trashed()) {
        abort(403);
    }
    return redirect(\App\Filament\Resources\Programs\ProgramResource::getUrl('index'));
});

Route::get('/youtube-kanallari', [YoutubeChannelController::class, 'index'])->name('youtube-channels.index');
Route::get('/instagram-videolari', [InstagramVideoController::class, 'index'])->name('instagram-videos.index');
Route::get('/programlar', [ProgramController::class, 'index'])->name('programs.index');
Route::permanentRedirect('/programlar/dost_online.php', '/programlar');
Route::get('/programlar/{program:slug}', [ProgramController::class, 'show'])->name('programs.show');

Route::get('/program-koleksiyonlari/{slug}', [ProgramCollectionController::class, 'show'])->name('program.collections.show');
Route::get('/koleksiyonlar/{collection:slug}', [VideoCollectionController::class, 'show'])->name('collections.show');

Route::get('/arama', [SearchController::class, 'index'])->name('search.index');

Route::get('/yayin-akisi', [ScheduleController::class, 'index'])->name('schedule.index');

Route::permanentRedirect('/canli-tv', '/canli-yayin');
Route::get('/canli-yayin', [LiveController::class, 'tv'])->name('live.tv');
Route::get('/canli-radyo', [LiveController::class, 'radio'])->name('live.radio');

use App\Http\Controllers\Admin\CategoryReorderController;
use App\Http\Controllers\Admin\HomepagePreviewController;
use App\Http\Controllers\Admin\MenuItemReorderController;
use App\Http\Controllers\Admin\ThemePreviewController;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/admin/theme-preview/toggle-mode/{mode}', [ThemePreviewController::class, 'toggleMode'])->name('theme.preview.toggle-mode');
    Route::get('/admin/theme-preview/close', [ThemePreviewController::class, 'close'])->name('theme.preview.close');
    Route::get('/admin/site-layout/preview-frame/{homepageLayout}', [HomepagePreviewController::class, 'frame'])->name('admin.site-layout.preview-frame');
    Route::post('/admin/categories/reorder', [CategoryReorderController::class, 'reorder'])->name('admin.categories.reorder');
    Route::post('/admin/menu-items/reorder', [MenuItemReorderController::class, 'reorder'])->name('admin.menu-items.reorder');

    Route::get('/admin/schedule/download-template', function (\App\Services\Schedule\ScheduleExcelImportService $service) {
        $path = $service->generateSampleTemplate();
        return response()->download($path, 'Yayın_Akışı_Excel_Şablonu.xlsx')->deleteFileAfterSend(true);
    })->name('admin.schedule.download-template');

    Route::get('/admin/schedule/download-errors', function (\Illuminate\Http\Request $request, \App\Services\Schedule\ScheduleExcelImportService $service) {
        $key = $request->query('key');
        $errors = [];
        if ($key) {
            $errors = json_decode(base64_decode($key), true) ?: [];
        }
        $path = $service->generateErrorExport($errors);
        return response()->download($path, 'Yayın_Akışı_İçe_Aktarma_Hataları.xlsx')->deleteFileAfterSend(true);
    })->name('schedule.excel.errors');
});

use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\ContactController;

Route::middleware(['web', 'throttle:10,1'])->group(function () {
    Route::get('/davet/{token}', [InvitationController::class, 'show'])->name('invitation.accept');
    Route::post('/davet/{token}', [InvitationController::class, 'accept'])->name('invitation.accept.post');
});

Route::post('/iletisim', [ContactController::class, 'store'])
    ->middleware(['web', 'throttle:5,1'])
    ->name('contact.store');

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/admin/page-preview/close/{slug}', [\App\Http\Controllers\Admin\PagePreviewController::class, 'close'])->name('page.preview.close');
    Route::get('/admin/collection-preview/close-video/{slug}', [\App\Http\Controllers\Admin\CollectionPreviewController::class, 'closeVideoCollection'])->name('video.collection.preview.close');
    Route::get('/admin/collection-preview/close-program/{slug}', [\App\Http\Controllers\Admin\CollectionPreviewController::class, 'closeProgramCollection'])->name('program.collection.preview.close');
});

use Illuminate\Validation\Rule;

Route::post('/api/track-event', function (\Illuminate\Http\Request $request) {
    $validated = $request->validate([
        'event_name' => [
            'required',
            'string',
            Rule::in([
                'hero_program_click',
                'live_tv_click',
                'program_card_click',
                'video_card_click',
                'view_all_schedule_click',
                'collection_click',
            ]),
        ],
        'entity_type' => 'nullable|string|max:50',
        'entity_id' => 'nullable|integer',
        'metadata' => 'nullable|array',
    ]);

    $metadata = isset($validated['metadata']) && is_array($validated['metadata'])
        ? array_slice($validated['metadata'], 0, 10, true)
        : [];

    try {
        app(\App\Services\Analytics\AnalyticsService::class)->logEvent(
            $validated['event_name'],
            $validated['entity_type'] ?? null,
            $validated['entity_id'] ?? null,
            $metadata
        );
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::warning('Track event failed: ' . $e->getMessage());
    }

    return response()->json(['status' => 'ok']);
})->middleware(['web', 'throttle:60,1'])->name('api.track-event');

// Eski WordPress / OTT tema kalıcı 301 yönlendirmeleri
Route::permanentRedirect('/tv-shows', '/programlar');
Route::permanentRedirect('/episodes', '/programlar');
Route::permanentRedirect('/videos', '/programlar');
Route::permanentRedirect('/tv-programlari', '/programlar');
Route::permanentRedirect('/programlarimiz', '/programlar');
Route::permanentRedirect('/hakkimizda', '/neden-dost-tv');
Route::permanentRedirect('/kunye', '/yayinci-kunye-bilgisi');
Route::permanentRedirect('/dost-fm', '/canli-radyo');
Route::permanentRedirect('/dost-fm-canli', '/canli-radyo');
Route::permanentRedirect('/yayin-akisi-deneme', '/yayin-akisi');
Route::permanentRedirect('/canli-yayin-eski', '/canli-yayin');
Route::permanentRedirect('/insan-kaynaklari', '/iletisim');
Route::permanentRedirect('/mukabele', '/programlar');
Route::permanentRedirect('/osmanlica-dersleri', '/programlar');
Route::permanentRedirect('/insanlik-hali', '/programlar');
Route::permanentRedirect('/yaza-dair', '/programlar');

// Eski ASP, ASPX, PHP ve statik dosya yönlendirmeleri
Route::match(['get', 'head'], '/{file}', [LegacyRedirectController::class, 'legacyScript'])
    ->where('file', '(?i).*\.(aspx|asp|php)$');

Route::get('/categories/{slug?}', [LegacyRedirectController::class, 'categories'])->where('slug', '.*');
Route::get('/tv-show/{slug?}', [LegacyRedirectController::class, 'tvShow'])->where('slug', '.*');
Route::get('/tv-show-genre/{slug?}', [LegacyRedirectController::class, 'tvShowGenre'])->where('slug', '.*');
Route::get('/category/{slug?}', [LegacyRedirectController::class, 'tvShowGenre'])->where('slug', '.*');
Route::get('/tag/{slug?}', fn () => redirect()->route('programs.index', [], 301))->where('slug', '.*');
Route::get('/tv-show-tag/{slug?}', fn () => redirect()->route('programs.index', [], 301))->where('slug', '.*');
Route::get('/episode/{slug?}', [LegacyRedirectController::class, 'episodeOrVideo'])->where('slug', '.*');
Route::get('/video/{slug?}', [LegacyRedirectController::class, 'episodeOrVideo'])->where('slug', '.*');
Route::get('/videolar/{slug?}', [LegacyRedirectController::class, 'episodeOrVideo'])->where('slug', '.*');

// Eski WordPress bot ve sistem yolları
Route::get('/wp-json/{any?}', fn () => redirect()->to('/', 301))->where('any', '.*');
Route::get('/wp-content/{any?}', fn () => redirect()->to('/', 301))->where('any', '.*');
Route::get('/Dosttv', fn () => redirect()->to('/', 301));
Route::get('/TR/default.asp', fn () => redirect()->to('/', 301));
Route::get('/sümüklü böcek film/{any?}', fn () => redirect()->route('programs.index', [], 301))->where('any', '.*');

// Eski sayfalama URL'leri: /{slug}/page/{page}
Route::get('/{slug}/page/{page?}', [LegacyRedirectController::class, 'paginatedArchive'])->where('page', '[0-9]+');

// Eski numerik post ID'leri: /{id} (örn. /11, /331, /17)
Route::get('/{id}', fn () => redirect()->to('/', 301))->where('id', '[0-9]+');

// Statik sayfalar (İletişim, Hakkımızda, Yayın İlkeleri vb.) - en sonda, catch-all olarak
Route::get('/{page}', [PageController::class, 'show'])->name('pages.show');




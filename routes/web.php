<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\LiveController;
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

// Statik sayfalar (İletişim, Hakkımızda, Yayın İlkeleri vb.) - en sonda, catch-all olarak
Route::get('/{page:slug}', [PageController::class, 'show'])->name('pages.show');


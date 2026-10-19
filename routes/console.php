<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('youtube:sync-playlists')
    ->dailyAt('04:00')
    ->timezone('Europe/Istanbul')
    ->withoutOverlapping();

Schedule::command('audit:prune')
    ->daily()
    ->withoutOverlapping();

Schedule::command('analytics:prune')
    ->daily()
    ->withoutOverlapping();

Schedule::job(new \App\Jobs\SyncGa4MetricsJob)
    ->hourly()
    ->withoutOverlapping();

Schedule::job(new \App\Jobs\SyncGoogleAdsMetricsJob)
    ->hourly()
    ->withoutOverlapping();

Schedule::job(new \App\Jobs\SyncMetaAdsMetricsJob)
    ->hourly()
    ->withoutOverlapping();

Schedule::job(new \App\Jobs\EvaluateAnalyticsAlertsJob)
    ->hourly()
    ->withoutOverlapping();

Schedule::call(function () {
    \Illuminate\Support\Facades\Cache::put('scheduler_last_heartbeat_at', now()->toIso8601String(), 86400 * 7);
})->everyMinute();

Artisan::command('schedule:status', function () {
    $lastHeartbeat = \Illuminate\Support\Facades\Cache::get('scheduler_last_heartbeat_at');
    $lastYoutubeSync = \Illuminate\Support\Facades\Cache::get('youtube_sync_last_run_at');
    $lastStats = \Illuminate\Support\Facades\Cache::get('youtube_sync_last_stats');

    $this->info('=== LARAVEL SCHEDULER & YOUTUBE SYNC STATUS ===');
    $this->line('Zamanlayıcı Kalp Atışı (Son Çalışma): ' . ($lastHeartbeat ?: 'Kayıt yok (veya henüz tetiklenmedi)'));
    $this->line('Son YouTube Senkronizasyonu: ' . ($lastYoutubeSync ?: 'Kayıt yok'));
    if ($lastStats) {
        $this->line('Son Senkronizasyon İstatistikleri: ' . json_encode($lastStats, JSON_UNESCAPED_UNICODE));
    }
})->purpose('Show Laravel scheduler heartbeat and YouTube sync status');



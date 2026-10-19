<?php

namespace App\Console\Commands;

use App\Services\YouTube\YouTubePlaylistSyncService;
use Illuminate\Console\Command;

class SyncYoutubePlaylistsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'youtube:sync-playlists {--dry-run : Run sync in simulation mode without writing to DB}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize YouTube playlists configured on programs to create missing episode records.';

    /**
     * Execute the console command.
     */
    public function handle(YouTubePlaylistSyncService $syncService): int
    {
        $isDryRun = (bool) $this->option('dry-run');

        if ($isDryRun) {
            $this->info('--- SIMULATION MODE (--dry-run) ---');
        }

        $stats = $syncService->syncAllPlaylists($isDryRun);

        $this->line("Kontrol edilen program: {$stats['checked_programs']}");
        $this->line("Kontrol edilen playlist: {$stats['checked_playlists']}");
        $this->line("Yeni video bulunan: {$stats['new_videos_found']}");
        $this->line("Oluşturulan bölüm: {$stats['created_episodes']}");
        $this->line("Atlanan mevcut video: {$stats['skipped_existing']}");
        $this->line("Hata: {$stats['errors']}");

        \Illuminate\Support\Facades\Log::info("YouTube Otomatik Senkronizasyon Tamamlandı: {$stats['created_episodes']} yeni bölüm eklendi.", [
            'checked_programs' => $stats['checked_programs'],
            'checked_playlists' => $stats['checked_playlists'],
            'new_videos_found' => $stats['new_videos_found'],
            'created_episodes' => $stats['created_episodes'],
            'skipped_existing' => $stats['skipped_existing'],
            'errors' => $stats['errors'],
            'dry_run' => $isDryRun,
        ]);

        if (! $isDryRun) {
            \Illuminate\Support\Facades\Cache::put('youtube_sync_last_run_at', now()->toIso8601String(), 86400 * 30);
            \Illuminate\Support\Facades\Cache::put('youtube_sync_last_stats', $stats, 86400 * 30);

            \App\Services\Audit\AuditLogger::log(
                action: 'synced',
                message: "Otomatik YouTube Senkronizasyonu tamamlandı: {$stats['created_episodes']} yeni bölüm eklendi ({$stats['checked_playlists']} playlist kontrol edildi, {$stats['skipped_existing']} video atlandı, {$stats['errors']} hata).",
                subjectLabel: 'YouTube Senkronizasyonu',
                metadata: [
                    'type' => 'automated_scheduler',
                    'checked_programs' => $stats['checked_programs'],
                    'checked_playlists' => $stats['checked_playlists'],
                    'new_videos_found' => $stats['new_videos_found'],
                    'created_episodes' => $stats['created_episodes'],
                    'skipped_existing' => $stats['skipped_existing'],
                    'errors' => $stats['errors'],
                ]
            );
        }

        if ($isDryRun && ! empty($stats['details'])) {
            $this->newLine();
            $this->info('--- DRY RUN DETAYLARI ---');
            foreach ($stats['details'] as $detail) {
                if (($detail['new_videos'] ?? 0) > 0) {
                    $this->line("[{$detail['program_name']}] -> {$detail['new_videos']} yeni bölüm oluşturulacaktı.");
                    foreach ($detail['items'] as $item) {
                        $this->line("   + B{$item['episode_number']}: {$item['title']} ({$item['youtube_url']})");
                    }
                }
            }
        }

        return Command::SUCCESS;
    }
}

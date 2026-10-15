<?php

namespace App\Filament\Widgets;

use App\Services\Schedule\BroadcastScheduleResolver;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

class TodayScheduleWidget extends Widget
{
    protected string $view = 'filament.widgets.today-schedule-widget';

    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $now = now();
        $resolver = app(BroadcastScheduleResolver::class);
        $rawBroadcasts = $resolver->getScheduleForDate($now);

        $turkishMonths = [
            1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan',
            5 => 'Mayıs', 6 => 'Haziran', 7 => 'Temmuz', 8 => 'Ağustos',
            9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık',
        ];

        $turkishDays = [
            1 => 'Pazartesi', 2 => 'Salı', 3 => 'Çarşamba',
            4 => 'Perşembe', 5 => 'Cuma', 6 => 'Cumartesi', 7 => 'Pazar',
        ];

        $turkishFormattedDate = $now->day . ' ' . ($turkishMonths[$now->month] ?? '') . ' ' . $now->year . ', ' . ($turkishDays[$now->dayOfWeekIso] ?? '');

        $currentTime = $now->format('H:i');
        $broadcasts = [];
        $nextUpcomingFound = false;

        foreach ($rawBroadcasts as $item) {
            $startTime = Carbon::parse($item->start_time)->format('H:i');
            $endTime = $item->end_time ? Carbon::parse($item->end_time)->format('H:i') : null;

            $isNowPlaying = false;
            if ($endTime === null || $endTime === '00:00') {
                $isNowPlaying = ($currentTime >= $startTime);
            } elseif ($endTime > $startTime) {
                $isNowPlaying = ($currentTime >= $startTime && $currentTime < $endTime);
            } else {
                $isNowPlaying = ($currentTime >= $startTime || $currentTime < $endTime);
            }

            if ($isNowPlaying) {
                $statusKey = 'now_playing';
                $statusLabel = 'Şu Anda';
            } elseif ($startTime > $currentTime && ! $nextUpcomingFound) {
                $statusKey = 'next_upcoming';
                $statusLabel = 'Sıradaki';
                $nextUpcomingFound = true;
            } elseif ($endTime && $endTime <= $currentTime) {
                $statusKey = 'finished';
                $statusLabel = 'Tamamlandı';
            } else {
                $statusKey = 'upcoming';
                $statusLabel = 'Bekleyen';
            }

            $broadcasts[] = [
                'id' => $item->id,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'display_title' => $item->display_title,
                'is_live' => (bool) ($item->is_live ?? false),
                'is_repeat' => (bool) ($item->is_repeat ?? false),
                'is_package' => (bool) ($item->is_package ?? false),
                'status_key' => $statusKey,
                'status_label' => $statusLabel,
            ];
        }

        return [
            'formattedDate' => $turkishFormattedDate,
            'broadcasts' => $broadcasts,
            'hasBroadcasts' => count($broadcasts) > 0,
            'scheduleCalendarUrl' => '/admin/schedule-calendar',
        ];
    }
}

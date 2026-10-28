<?php

namespace App\Services\Announcement;

use App\Models\Announcement;
use Illuminate\Database\Eloquent\Collection;

class AnnouncementService
{
    /**
     * Get all currently visible announcements for a given placement (including global ones).
     * Ordered by pinned status first, then sort_order, latest starts_at, and newest id.
     */
    public function getVisibleForPlacement(string $placement): Collection
    {
        return Announcement::query()
            ->currentlyVisible()
            ->forPlacement($placement)
            ->orderByDesc('is_pinned')
            ->orderBy('sort_order')
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Resolve the placement string for the current request.
     */
    public static function resolveCurrentPlacement(): string
    {
        $routeName = request()->route()?->getName();
        $path = trim(request()->path(), '/');

        return match (true) {
            $routeName === 'home' || $path === '' => 'home',
            $routeName === 'live.tv' || $path === 'canli-tv' || $path === 'canli-yayin' => 'live_tv',
            $routeName === 'live.radio' || $path === 'canli-radyo' => 'live_radio',
            $routeName === 'schedule.index' || $path === 'yayin-akisi' => 'schedule',
            default => 'other',
        };
    }

    /**
     * Get the single highest priority active popup announcement for a given placement.
     * Order: is_pinned DESC, sort_order ASC, starts_at DESC, id DESC.
     */
    public function getTopPopupForPlacement(string $placement): ?Announcement
    {
        return Announcement::query()
            ->currentlyVisible()
            ->forPlacement($placement)
            ->orderByDesc('is_pinned')
            ->orderBy('sort_order')
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->first();
    }

    public function forHome(): Collection
    {
        return $this->getVisibleForPlacement('home');
    }

    public function forLiveTv(): Collection
    {
        return $this->getVisibleForPlacement('live_tv');
    }

    public function forLiveRadio(): Collection
    {
        return $this->getVisibleForPlacement('live_radio');
    }

    public function forSchedule(): Collection
    {
        return $this->getVisibleForPlacement('schedule');
    }

    public function global(): Collection
    {
        return Announcement::query()
            ->currentlyVisible()
            ->where('placement', 'global')
            ->orderByDesc('is_pinned')
            ->orderBy('sort_order')
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->get();
    }
}

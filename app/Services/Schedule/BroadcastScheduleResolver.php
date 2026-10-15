<?php

namespace App\Services\Schedule;

use App\Models\ScheduleException;
use App\Models\ScheduleTemplate;
use App\Models\ScheduleTemplateItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class BroadcastScheduleResolver
{
    /**
     * Resolves schedule items for a specific date (e.g. today).
     */
    public function getScheduleForDate(?Carbon $date = null): Collection
    {
        $date = $date ? $date->copy() : now();
        $dateString = $date->toDateString();

        // 1. Check for published Exception Day
        $exception = ScheduleException::query()
            ->whereDate('exception_date', $dateString)
            ->where('status', 'published')
            ->with('items.program')
            ->first();

        if ($exception && $exception->override_type === 'replace_all') {
            return $exception->items
                ->where('is_active', true)
                ->map(fn ($item) => $this->mapExceptionItemToScheduleFormat($item));
        }

        // 2. Resolve Active Published Template
        $template = $this->getActivePublishedTemplateForDate($date);

        $templateItems = collect();

        if ($template) {
            $dayOfWeekIso = $date->dayOfWeekIso - 1; // 0 = Pazartesi, 6 = Pazar
            $templateItems = ScheduleTemplateItem::query()
                ->where('schedule_template_id', $template->id)
                ->where('day_of_week', $dayOfWeekIso)
                ->where('is_active', true)
                ->with('program.categories')
                ->orderBy('start_time')
                ->get();
        }

        // 3. Merge additional exception items if any
        if ($exception && $exception->override_type === 'additional') {
            $additionalItems = $exception->items
                ->where('is_active', true)
                ->map(fn ($item) => $this->mapExceptionItemToScheduleFormat($item));

            return $templateItems->concat($additionalItems)->sortBy('start_time')->values();
        }

        return $templateItems;
    }

    /**
     * Resolves the full weekly schedule grouped by day_of_week (0-6).
     */
    public function getWeeklySchedule(?Carbon $referenceDate = null): Collection
    {
        $referenceDate = $referenceDate ? $referenceDate->copy() : now();
        $startOfWeek = $referenceDate->copy()->startOfWeek();

        $weeklyMap = collect();

        for ($i = 0; $i < 7; $i++) {
            $currentDate = $startOfWeek->copy()->addDays($i);
            $weeklyMap->put($i, $this->getScheduleForDate($currentDate));
        }

        return $weeklyMap;
    }

    /**
     * Resolves published template for a given date based on validity & priority.
     */
    public function getActivePublishedTemplateForDate(Carbon $date): ?ScheduleTemplate
    {
        $dateString = $date->toDateString();

        // 1. Specific range match for the given date
        $template = ScheduleTemplate::query()
            ->where('status', 'published')
            ->where('is_active', true)
            ->where(function ($q) use ($dateString) {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', $dateString);
            })
            ->where(function ($q) use ($dateString) {
                $q->whereNull('valid_until')->orWhere('valid_until', '>=', $dateString);
            })
            ->orderBy('priority', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        if (! $template) {
            // 2. Fallback to active template whose valid_from date has arrived
            $template = ScheduleTemplate::query()
                ->where('status', 'published')
                ->where('is_active', true)
                ->where(function ($q) use ($dateString) {
                    $q->whereNull('valid_from')->orWhere('valid_from', '<=', $dateString);
                })
                ->orderBy('priority', 'desc')
                ->orderBy('id', 'desc')
                ->first();
        }

        if (! $template) {
            // 3. Final fallback to any published active template
            $template = ScheduleTemplate::query()
                ->where('status', 'published')
                ->where('is_active', true)
                ->orderBy('priority', 'desc')
                ->orderBy('id', 'desc')
                ->first();
        }

        return $template;
    }

    protected function mapExceptionItemToScheduleFormat($item)
    {
        return (object) [
            'id' => $item->id,
            'program_id' => $item->program_id,
            'program' => $item->program,
            'start_time' => $item->start_time,
            'end_time' => $item->end_time,
            'custom_title' => $item->custom_title,
            'is_live' => $item->is_live,
            'is_repeat' => $item->is_repeat,
            'is_package' => (bool) ($item->is_package ?? false),
            'is_active' => $item->is_active,
            'note' => $item->note,
            'display_title' => $item->custom_title ?: ($item->program?->name ?? 'Özel Yayın'),
        ];
    }
}

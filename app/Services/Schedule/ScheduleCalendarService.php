<?php

namespace App\Services\Schedule;

use App\Models\Schedule;
use App\Models\ScheduleTemplate;
use App\Models\ScheduleTemplateItem;
use App\Models\ScheduleVersionHistory;
use App\Support\SiteCache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ScheduleCalendarService
{
    public function getTemplates(): Collection
    {
        return ScheduleTemplate::query()
            ->where('is_active', true)
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getActiveOrSelectedTemplate(?int $templateId = null): ?ScheduleTemplate
    {
        // Note: 'versionHistories' is intentionally not eager-loaded here - it is
        // not displayed or used anywhere on this page, and its snapshot_data
        // column can be large enough (100s of KB per row) that sorting it via
        // the relation's default orderBy('version_number', 'desc') can exceed
        // MySQL's sort buffer ("Out of sort memory") on every page load.
        if ($templateId) {
            $template = ScheduleTemplate::with(['items.program'])->find($templateId);
            if ($template) {
                return $template;
            }
        }

        return ScheduleTemplate::with(['items.program'])
            ->where('status', 'published')
            ->where('is_active', true)
            ->orderBy('priority', 'desc')
            ->first()
            ?: ScheduleTemplate::with(['items.program'])->first();
    }

    public function getDayCounts(ScheduleTemplate $template): array
    {
        $counts = [];
        for ($day = 0; $day < 7; $day++) {
            $counts[$day] = $template->items()->where('day_of_week', $day)->count();
        }
        return $counts;
    }

    public function checkOverlap(int $templateId, int $dayOfWeek, string $startTime, ?string $endTime, ?int $ignoreItemId = null): bool
    {
        $query = ScheduleTemplateItem::query()
            ->where('schedule_template_id', $templateId)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true);

        if ($ignoreItemId) {
            $query->where('id', '!=', $ignoreItemId);
        }

        $start = Carbon::parse($startTime)->format('H:i:s');
        $end = ($endTime && $endTime !== '00:00') ? Carbon::parse($endTime)->format('H:i:s') : '23:59:59';

        return $query->where(function ($q) use ($start, $end) {
            $q->where(function ($q2) use ($start) {
                $q2->where('start_time', '<=', $start)
                   ->whereRaw("COALESCE(NULLIF(end_time, '00:00:00'), '23:59:59') > ?", [$start]);
            })->orWhere(function ($q3) use ($end) {
                $q3->where('start_time', '<', $end)
                   ->whereRaw("COALESCE(NULLIF(end_time, '00:00:00'), '23:59:59') >= ?", [$end]);
            })->orWhere(function ($q4) use ($start, $end) {
                $q4->where('start_time', '>=', $start)
                   ->whereRaw("COALESCE(NULLIF(end_time, '00:00:00'), '23:59:59') <= ?", [$end]);
            });
        })->exists();
    }

    public function shiftTimes(ScheduleTemplate $template, int $minutes, ?int $targetDay = null): void
    {
        $itemsQuery = $template->items();
        if ($targetDay !== null) {
            $itemsQuery->where('day_of_week', $targetDay);
        }

        $items = $itemsQuery->get();

        foreach ($items as $item) {
            $start = Carbon::parse($item->start_time)->addMinutes($minutes)->format('H:i');
            $end = $item->end_time ? Carbon::parse($item->end_time)->addMinutes($minutes)->format('H:i') : null;

            $item->update([
                'start_time' => $start,
                'end_time' => $end,
            ]);
        }

        if ($template->status === 'published') {
            $template->update(['status' => 'draft']);
        }

        SiteCache::forgetMenu('header_primary');
    }

    public function copyDay(ScheduleTemplate $template, int $sourceDay, int $targetDay): void
    {
        $sourceItems = $template->items()->where('day_of_week', $sourceDay)->get();

        $template->items()->where('day_of_week', $targetDay)->delete();

        foreach ($sourceItems as $item) {
            $newItem = $item->replicate(['schedule_template_id', 'day_of_week']);
            $newItem->schedule_template_id = $template->id;
            $newItem->day_of_week = $targetDay;
            $newItem->save();
        }

        if ($template->status === 'published') {
            $template->update(['status' => 'draft']);
        }

        SiteCache::forgetMenu('header_primary');
    }

    public function copyWeek(ScheduleTemplate $sourceTemplate, int $targetTemplateId): void
    {
        $targetTemplate = ScheduleTemplate::find($targetTemplateId);
        if (! $targetTemplate) {
            return;
        }

        $targetTemplate->items()->delete();

        foreach ($sourceTemplate->items as $item) {
            $newItem = $item->replicate(['schedule_template_id']);
            $newItem->schedule_template_id = $targetTemplate->id;
            $newItem->save();
        }

        if ($targetTemplate->status === 'published') {
            $targetTemplate->update(['status' => 'draft']);
        }

        SiteCache::forgetMenu('header_primary');
    }

    public function publishTemplate(ScheduleTemplate $template, ?int $userId = null, ?string $summary = null): ScheduleVersionHistory
    {
        SiteCache::forgetMenu('header_primary');
        return $template->publish($userId, $summary);
    }

    public function getExceptionForDate(string $date): ?\App\Models\ScheduleException
    {
        return \App\Models\ScheduleException::query()
            ->whereDate('exception_date', $date)
            ->where('status', 'published')
            ->with('items.program')
            ->first();
    }

    public function createSpecialBroadcast(array $data): \App\Models\ScheduleExceptionItem
    {
        $exceptionDate = $data['exception_date'];
        $overrideType = $data['override_type'] ?? 'replace_all';

        $exception = \App\Models\ScheduleException::query()
            ->whereDate('exception_date', $exceptionDate)
            ->first();

        if (! $exception) {
            $exception = \App\Models\ScheduleException::create([
                'exception_date' => $exceptionDate,
                'name' => 'Özel Yayın Akışı (' . Carbon::parse($exceptionDate)->format('d.m.Y') . ')',
                'description' => $data['note'] ?? 'Özel yayın akışı',
                'override_type' => $overrideType,
                'status' => 'published',
            ]);
        } else {
            $exception->update([
                'override_type' => $overrideType,
                'status' => 'published',
            ]);
        }

        $item = $exception->items()->create([
            'program_id' => $data['program_id'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'] ?? null,
            'custom_title' => $data['custom_title'] ?? null,
            'is_live' => (bool) ($data['is_live'] ?? false),
            'is_repeat' => (bool) ($data['is_repeat'] ?? false),
            'is_active' => true,
            'note' => $data['note'] ?? null,
        ]);

        \App\Services\Audit\AuditLogger::log(
            action: 'created',
            message: sprintf(
                'Özel yayın oluşturuldu (%s Tarihi, %s %s–%s). Mod: %s',
                Carbon::parse($exceptionDate)->format('d.m.Y'),
                $item->display_title,
                Carbon::parse($item->start_time)->format('H:i'),
                $item->end_time ? Carbon::parse($item->end_time)->format('H:i') : '23:59',
                $overrideType === 'replace_all' ? 'Tüm Akış Değiştirildi' : 'Mevcut Akışa Eklendi'
            ),
            subject: $item,
            subjectLabel: 'Özel Yayın Akışı'
        );

        SiteCache::forgetMenu('header_primary');
        SiteCache::forgetHomeHeroPrograms();

        return $item;
    }

    public function deleteException(int $exceptionId): void
    {
        $exception = \App\Models\ScheduleException::find($exceptionId);
        if (! $exception) {
            return;
        }

        $dateStr = Carbon::parse($exception->exception_date)->format('d.m.Y');
        $exception->delete();

        \App\Services\Audit\AuditLogger::log(
            action: 'deleted',
            message: sprintf('%s tarihli özel yayın akışı kaldırıldı. Normal yayın akışına dönüldü.', $dateStr),
            subjectLabel: 'Özel Yayın Akışı'
        );

        SiteCache::forgetMenu('header_primary');
        SiteCache::forgetHomeHeroPrograms();
    }

    public function activateTemplate(ScheduleTemplate $template, ?string $userName = null): void
    {
        DB::transaction(function () use ($template) {
            ScheduleTemplate::query()
                ->whereKeyNot($template->getKey())
                ->update(['is_active' => false]);

            $template->update([
                'status' => 'published',
                'is_active' => true,
            ]);
        });

        $user = $userName ?? (auth()->user()?->name ?? 'Kullanıcı');
        \App\Services\Audit\AuditLogger::log(
            action: 'activated',
            message: "{$user}, {$template->name} dönemini gösterime aldı.",
            subject: $template,
            subjectLabel: $template->name,
        );

        // Explicit cache invalidation after transaction committed
        SiteCache::forgetHomeHeroPrograms();
        SiteCache::forgetHomepage();
    }
}


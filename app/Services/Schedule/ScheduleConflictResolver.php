<?php

namespace App\Services\Schedule;

use App\Models\ScheduleTemplateItem;
use App\Services\Audit\AuditLogger;
use App\Support\SiteCache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ScheduleConflictResolver
{
    /**
     * Analyze conflicts between a proposed new item time range and existing active items.
     *
     * @param Collection $existingActiveItems Existing active ScheduleTemplateItem records for the same template & day
     * @param string $newStart 'HH:MM' or 'HH:MM:SS'
     * @param string|null $newEnd 'HH:MM' or 'HH:MM:SS'
     * @param int|null $ignoreItemId ID of item being edited (if any)
     * @return array Structure containing has_conflict, is_resolvable, conflict_type, items_to_update, error_message, etc.
     */
    public function analyze(
        Collection $existingActiveItems,
        string $newStart,
        ?string $newEnd,
        ?int $ignoreItemId = null
    ): array {
        $startStr = Carbon::parse($newStart)->format('H:i:s');
        $endStr = ($newEnd && $newEnd !== '00:00') ? Carbon::parse($newEnd)->format('H:i:s') : '23:59:59';

        $conflicts = [];
        $hasMidSplit = false;

        foreach ($existingActiveItems as $item) {
            if ($ignoreItemId && (int) $item->id === (int) $ignoreItemId) {
                continue;
            }

            $itemStartStr = Carbon::parse($item->start_time)->format('H:i:s');
            $itemEndStr = ($item->end_time && $item->end_time !== '00:00:00')
                ? Carbon::parse($item->end_time)->format('H:i:s')
                : '23:59:59';

            // Check if there is any overlap
            $isOverlap = ($itemStartStr < $endStr) && ($itemEndStr > $startStr);

            if (! $isOverlap) {
                continue;
            }

            // Categorize overlap
            if ($itemStartStr < $startStr && $itemEndStr > $endStr) {
                // Type D: Mid-split (New item is inside existing item)
                $hasMidSplit = true;
                $conflicts[] = [
                    'item' => $item,
                    'type' => 'mid_split',
                    'action' => 'none',
                    'description' => sprintf(
                        '%s (%s–%s) programını ikiye böleceği için otomatik düzenlenemez.',
                        $item->display_title,
                        Carbon::parse($itemStartStr)->format('H:i'),
                        Carbon::parse($itemEndStr)->format('H:i')
                    ),
                ];
            } elseif ($itemStartStr >= $startStr && $itemEndStr <= $endStr) {
                // Type C: Full Encapsulation (New item covers existing item)
                $conflicts[] = [
                    'item' => $item,
                    'type' => 'full_encapsulate',
                    'action' => 'passivate',
                    'new_is_active' => false,
                    'description' => sprintf(
                        '%s (%s–%s) yayını tamamen kapanacak ve akışta gizli duruma getirilecek.',
                        $item->display_title,
                        Carbon::parse($itemStartStr)->format('H:i'),
                        Carbon::parse($itemEndStr)->format('H:i')
                    ),
                ];
            } elseif ($itemStartStr < $startStr && $itemEndStr > $startStr && $itemEndStr <= $endStr) {
                // Type A: End-Trim (New item overlaps end of existing item)
                $proposedEndTime = Carbon::parse($startStr)->format('H:i');
                $conflicts[] = [
                    'item' => $item,
                    'type' => 'end_trim',
                    'action' => 'update_end_time',
                    'new_end_time' => $proposedEndTime,
                    'description' => sprintf(
                        '%s (%s–%s) yayınının bitiş saati %s olarak güncellenecek.',
                        $item->display_title,
                        Carbon::parse($itemStartStr)->format('H:i'),
                        Carbon::parse($itemEndStr)->format('H:i'),
                        $proposedEndTime
                    ),
                ];
            } elseif ($itemStartStr >= $startStr && $itemStartStr < $endStr && $itemEndStr > $endStr) {
                // Type B: Start-Shift (New item overlaps start of existing item)
                $proposedStartTime = Carbon::parse($endStr)->format('H:i');
                $conflicts[] = [
                    'item' => $item,
                    'type' => 'start_shift',
                    'action' => 'update_start_time',
                    'new_start_time' => $proposedStartTime,
                    'description' => sprintf(
                        '%s (%s–%s) yayınının başlangıç saati %s olarak güncellenecek.',
                        $item->display_title,
                        Carbon::parse($itemStartStr)->format('H:i'),
                        Carbon::parse($itemEndStr)->format('H:i'),
                        $proposedStartTime
                    ),
                ];
            }
        }

        $hasConflict = ! empty($conflicts);
        $isResolvable = $hasConflict && ! $hasMidSplit;

        return [
            'has_conflict' => $hasConflict,
            'is_resolvable' => $isResolvable,
            'has_mid_split' => $hasMidSplit,
            'conflicts' => $conflicts,
        ];
    }

    /**
     * Apply resolution plan and save/update target item inside a database transaction.
     */
    public function applyResolutionInTransaction(array $conflicts, callable $createOrUpdateItemCallback): mixed
    {
        return DB::transaction(function () use ($conflicts, $createOrUpdateItemCallback) {
            foreach ($conflicts as $conflict) {
                $item = $conflict['item'];
                $action = $conflict['action'];

                if ($action === 'passivate') {
                    $item->update(['is_active' => false]);
                    AuditLogger::log(
                        action: 'updated',
                        message: sprintf('%s yayını çakışma nedeniyle pasife alındı.', $item->display_title),
                        subject: $item,
                        subjectLabel: 'Yayın Akışı'
                    );
                } elseif ($action === 'update_end_time') {
                    $oldEnd = $item->end_time;
                    $item->update(['end_time' => $conflict['new_end_time']]);
                    AuditLogger::log(
                        action: 'updated',
                        message: sprintf(
                            '%s yayınının bitiş saati çakışma düzenlemesi ile %s yapıldı (Önceki: %s).',
                            $item->display_title,
                            $conflict['new_end_time'],
                            $oldEnd ? Carbon::parse($oldEnd)->format('H:i') : '—'
                        ),
                        subject: $item,
                        subjectLabel: 'Yayın Akışı'
                    );
                } elseif ($action === 'update_start_time') {
                    $oldStart = $item->start_time;
                    $item->update(['start_time' => $conflict['new_start_time']]);
                    AuditLogger::log(
                        action: 'updated',
                        message: sprintf(
                            '%s yayınının başlangıç saati çakışma düzenlemesi ile %s yapıldı (Önceki: %s).',
                            $item->display_title,
                            $conflict['new_start_time'],
                            Carbon::parse($oldStart)->format('H:i')
                        ),
                        subject: $item,
                        subjectLabel: 'Yayın Akışı'
                    );
                }
            }

            $result = $createOrUpdateItemCallback();

            SiteCache::forgetHomeHeroPrograms();
            SiteCache::forgetMenu('header_primary');

            return $result;
        });
    }
}

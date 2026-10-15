<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ScheduleTemplate extends Model
{
    use HasFactory;

    protected $attributes = [
        'version' => 1,
        'status' => 'draft',
        'priority' => 1,
        'is_active' => false,
        'show_program_names' => true,
    ];

    protected $fillable = [
        'name',
        'slug',
        'description',
        'valid_from',
        'valid_until',
        'priority',
        'status',
        'version',
        'is_active',
        'show_program_names',
    ];

    protected $casts = [
        'valid_from' => 'date',
        'valid_until' => 'date',
        'priority' => 'integer',
        'version' => 'integer',
        'is_active' => 'boolean',
        'show_program_names' => 'boolean',
    ];

    public const STATUSES = [
        'draft' => 'Taslak',
        'ready' => 'Hazır',
        'active' => 'Gösterimde',
    ];

    public function getDisplayStatusAttribute(): string
    {
        if ($this->is_active && $this->status === 'published') {
            return 'Gösterimde';
        }

        if (! $this->is_active && $this->status === 'published') {
            return 'Hazır';
        }

        return 'Taslak';
    }

    public static function generateUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($name) ?: 'yayin-akisi';
        $slug = $baseSlug;
        $count = 1;

        while (static::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = "{$baseSlug}-{$count}";
            $count++;
        }

        return $slug;
    }

    protected static function booted(): void
    {
        static::saving(function (ScheduleTemplate $template) {
            if (blank($template->slug) || static::where('slug', $template->slug)->where('id', '!=', $template->id ?? 0)->exists()) {
                $template->slug = static::generateUniqueSlug($template->name ?: 'yayin-akisi', $template->id);
            }

            if ($template->is_active) {
                static::where('id', '!=', $template->id ?? 0)->where('is_active', true)->update(['is_active' => false]);
            }
        });

        static::saved(function () {
            \App\Support\SiteCache::forgetHomeHeroPrograms();
            \App\Support\SiteCache::forgetHomepage();
        });

        static::deleted(function () {
            \App\Support\SiteCache::forgetHomeHeroPrograms();
            \App\Support\SiteCache::forgetHomepage();
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(ScheduleTemplateItem::class)->orderBy('day_of_week')->orderBy('start_time');
    }

    public function versionHistories(): HasMany
    {
        return $this->hasMany(ScheduleVersionHistory::class)->orderBy('version_number', 'desc');
    }

    /**
     * Publishes current template state as a new version and snapshot.
     */
    public function publish(?int $userId = null, ?string $changeSummary = null): ScheduleVersionHistory
    {
        $this->version = $this->version + 1;
        $this->status = 'published';
        $this->save();

        $snapshot = [
            'template' => $this->toArray(),
            'items' => $this->items()->with('program')->get()->toArray(),
        ];

        return ScheduleVersionHistory::create([
            'schedule_template_id' => $this->id,
            'version_number' => $this->version,
            'snapshot_data' => $snapshot,
            'published_by_user_id' => $userId,
            'change_summary' => $changeSummary ?: ('Sürüm v' . $this->version . ' yayınlandı.'),
        ]);
    }

    /**
     * Duplicates this template for another year or season.
     */
    public function duplicateForNextYear(string $newName, int $yearOffset = 1): ScheduleTemplate
    {
        $newTemplate = $this->replicate(['slug', 'version', 'status']);
        $newTemplate->name = $newName;
        $newTemplate->slug = static::generateUniqueSlug($newName);
        $newTemplate->version = 1;
        $newTemplate->status = 'draft';

        if ($this->valid_from) {
            $newTemplate->valid_from = $this->valid_from->addYears($yearOffset);
        }
        if ($this->valid_until) {
            $newTemplate->valid_until = $this->valid_until->addYears($yearOffset);
        }

        $newTemplate->save();

        foreach ($this->items as $item) {
            $newItem = $item->replicate(['schedule_template_id']);
            $newItem->schedule_template_id = $newTemplate->id;
            $newItem->save();
        }

        return $newTemplate;
    }
}

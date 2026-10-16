<?php

namespace App\Models;

use App\Support\SiteCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ScheduleTemplateItem extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::saved(function () {
            SiteCache::forgetHomeHeroPrograms();
            SiteCache::forgetHomepage();
            SiteCache::forgetMenu('header_primary');
        });

        static::deleted(function () {
            SiteCache::forgetHomeHeroPrograms();
            SiteCache::forgetHomepage();
            SiteCache::forgetMenu('header_primary');
        });
    }

    protected $fillable = [
        'schedule_template_id',
        'program_id',
        'day_of_week',
        'start_time',
        'end_time',
        'custom_title',
        'is_live',
        'is_repeat',
        'is_package',
        'is_active',
        'note',
        'image',
        'description',
        'link_type',
        'episode_id',
        'external_url',
        'stream_url',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'is_live' => 'boolean',
        'is_repeat' => 'boolean',
        'is_package' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(ScheduleTemplate::class, 'schedule_template_id');
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function episode(): BelongsTo
    {
        return $this->belongsTo(Episode::class);
    }

    public function getDayNameAttribute(): string
    {
        return Schedule::DAYS[$this->day_of_week] ?? '';
    }

    public function getDisplayTitleAttribute(): string
    {
        return $this->custom_title ?: ($this->program?->name ?? 'Program');
    }

    public function getEffectiveImageAttribute(): string
    {
        if (filled($this->image)) {
            return (str_starts_with($this->image, 'http://') || str_starts_with($this->image, 'https://'))
                ? $this->image
                : Storage::disk('public')->url($this->image);
        }

        if ($this->episode && filled($this->episode->cover_image ?? null)) {
            $epImage = $this->episode->cover_image;
            return (str_starts_with($epImage, 'http://') || str_starts_with($epImage, 'https://'))
                ? $epImage
                : Storage::disk('public')->url($epImage);
        }

        if ($this->program && filled($this->program->cover_image ?? null)) {
            $progImage = $this->program->cover_image;
            return (str_starts_with($progImage, 'http://') || str_starts_with($progImage, 'https://'))
                ? $progImage
                : Storage::disk('public')->url($progImage);
        }

        return 'https://dosttv.com/wp-content/uploads/2022/02/dost_logo.png';
    }

    public function getTargetUrlAttribute(): ?string
    {
        return match ($this->link_type) {
            'program' => $this->program ? route('programs.show', $this->program) : null,
            'episode' => $this->episode ? ($this->program ? route('programs.show', $this->program) : null) : null,
            'external' => $this->external_url,
            'live' => $this->stream_url ?: route('live.tv'),
            'none' => null,
            default => $this->program ? route('programs.show', $this->program) : null,
        };
    }
}

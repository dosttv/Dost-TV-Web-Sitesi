<?php

namespace App\Models;

use App\Support\SiteCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleExceptionItem extends Model
{
    use HasFactory;

    protected array $explicitlyPassedAttributes = [];

    public function setAttribute($key, $value)
    {
        $this->explicitlyPassedAttributes[$key] = true;

        return parent::setAttribute($key, $value);
    }

    public function wasExplicitlySet(string $key): bool
    {
        return isset($this->explicitlyPassedAttributes[$key]);
    }

    protected static function booted(): void
    {
        static::creating(function (self $item) {
            if ($item->is_live && ! $item->wasExplicitlySet('show_in_hero')) {
                $item->show_in_hero = true;
            }
        });

        static::updating(function (self $item) {
            if ($item->isDirty('is_live') && $item->is_live && ! $item->wasExplicitlySet('show_in_hero')) {
                $item->show_in_hero = true;
            }
        });

        static::saved(function (self $item) {
            $item->explicitlyPassedAttributes = [];
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
        'schedule_exception_id',
        'program_id',
        'start_time',
        'end_time',
        'custom_title',
        'is_live',
        'is_repeat',
        'is_package',
        'is_active',
        'show_in_hero',
        'note',
    ];

    protected $casts = [
        'is_live' => 'boolean',
        'is_repeat' => 'boolean',
        'is_package' => 'boolean',
        'is_active' => 'boolean',
        'show_in_hero' => 'boolean',
    ];

    public function exception(): BelongsTo
    {
        return $this->belongsTo(ScheduleException::class, 'schedule_exception_id');
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }
}

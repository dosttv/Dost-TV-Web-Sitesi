<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Schedule extends Model
{
    /** @use HasFactory<\Database\Factories\ScheduleFactory> */
    use HasFactory;

    public const DAYS = [
        0 => 'Pazartesi',
        1 => 'Salı',
        2 => 'Çarşamba',
        3 => 'Perşembe',
        4 => 'Cuma',
        5 => 'Cumartesi',
        6 => 'Pazar',
    ];

    protected $fillable = [
        'program_id',
        'day_of_week',
        'start_time',
        'end_time',
        'is_live',
        'is_repeat',
        'is_package',
        'is_active',
        'show_in_hero',
        'sort_order',
        'custom_title',
        'note',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'is_live' => 'boolean',
        'is_repeat' => 'boolean',
        'is_package' => 'boolean',
        'is_active' => 'boolean',
        'show_in_hero' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function getDayNameAttribute(): string
    {
        return self::DAYS[$this->day_of_week] ?? '';
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDay($query, int $day)
    {
        return $query->where('day_of_week', $day);
    }
}

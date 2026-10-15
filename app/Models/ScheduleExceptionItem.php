<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleExceptionItem extends Model
{
    use HasFactory;

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
        'note',
    ];

    protected $casts = [
        'is_live' => 'boolean',
        'is_repeat' => 'boolean',
        'is_package' => 'boolean',
        'is_active' => 'boolean',
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

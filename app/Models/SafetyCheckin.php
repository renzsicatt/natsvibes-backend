<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SafetyCheckin extends Model
{
    protected $fillable = [
        'user_id',
        'hangout_id',
        'checkin_time',
        'status',
        'reminder_time',
    ];

    protected $casts = [
        'checkin_time' => 'datetime',
        'reminder_time' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hangout(): BelongsTo
    {
        return $this->belongsTo(Hangout::class);
    }
}

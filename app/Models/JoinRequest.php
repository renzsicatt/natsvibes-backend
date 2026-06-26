<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JoinRequest extends Model
{
    protected $fillable = [
        'hangout_id',
        'user_id',
        'status',
        'notes',
        'decided_by',
        'decided_at',
        'cancelled_at',
    ];

    protected $casts = ['decided_at' => 'datetime', 'cancelled_at' => 'datetime'];

    public function hangout(): BelongsTo
    {
        return $this->belongsTo(Hangout::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

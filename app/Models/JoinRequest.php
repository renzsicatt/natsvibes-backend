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
    ];

    public function hangout(): BelongsTo
    {
        return $this->belongsTo(Hangout::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

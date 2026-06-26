<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupMessage extends Model
{
    protected $fillable = [
        'hangout_id',
        'sender_id',
        'message_text',
        'type',
        'reported_at',
    ];

    protected $casts = ['reported_at' => 'datetime'];

    public function hangout(): BelongsTo
    {
        return $this->belongsTo(Hangout::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class GroupMessage extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'hangout_id',
        'sender_id',
        'message_text',
        'type',
        'reported_at',
        'reply_to_id',
        'edited_at',
        'deleted_by',
    ];

    protected $casts = ['reported_at' => 'datetime', 'edited_at' => 'datetime'];

    public function hangout(): BelongsTo
    {
        return $this->belongsTo(Hangout::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_id')->withTrashed();
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(MessageReaction::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Hangout extends Model
{
    protected $fillable = [
        'host_id',
        'venue_id',
        'title',
        'description',
        'date_time',
        'area',
        'group_size_limit',
        'budget_range',
        'status',
    ];

    protected $casts = [
        'date_time' => 'datetime',
    ];

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'hangout_members')->withTimestamps();
    }

    public function joinRequests(): HasMany
    {
        return $this->hasMany(JoinRequest::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(GroupMessage::class);
    }
}

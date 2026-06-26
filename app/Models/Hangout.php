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
        'rules',
        'host_notes',
        'request_cutoff_at',
        'timezone',
        'budget_min',
        'budget_max',
        'currency',
        'previous_status',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'date_time' => 'datetime',
        'request_cutoff_at' => 'datetime',
        'cancelled_at' => 'datetime',
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
        return $this->belongsToMany(User::class, 'hangout_members')
            ->withPivot(['role', 'status', 'joined_at', 'left_at'])
            ->withTimestamps();
    }

    public function joinRequests(): HasMany
    {
        return $this->hasMany(JoinRequest::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(GroupMessage::class);
    }

    public function vibeTags(): BelongsToMany
    {
        return $this->belongsToMany(VibeTag::class, 'hangout_vibe_tags');
    }

    public function activeMembers(): BelongsToMany
    {
        return $this->members()->wherePivot('status', 'active');
    }
}

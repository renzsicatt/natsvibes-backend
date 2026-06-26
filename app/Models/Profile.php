<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Profile extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'display_name',
        'age',
        'city',
        'bio',
        'avatar_url',
        'completion_status',
        'verification_status',
        'safety_preference',
        'going_out_style',
        'availability',
        'photo_review_status',
        'host_verification_status',
    ];

    protected $appends = ['is_verified'];

    public function getIsVerifiedAttribute(): bool
    {
        return $this->verification_status === 'approved'
            && $this->photo_review_status === 'approved';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vibeTags(): BelongsToMany
    {
        return $this->belongsToMany(VibeTag::class, 'profile_vibe_tags');
    }
}

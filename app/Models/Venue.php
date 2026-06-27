<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Venue extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'area',
        'city',
        'address',
        'maps_link',
        'venue_type',
        'price_range',
        'reservation_required',
        'status',
        'google_maps_url',
        'instagram_url',
        'budget_min',
        'budget_max',
        'currency',
        'opening_hours',
        'reservation_notes',
        'group_capacity_min',
        'group_capacity_max',
        'is_verified',
        'is_featured',
    ];

    protected $casts = [
        'opening_hours' => 'array',
        'reservation_required' => 'boolean',
        'is_verified' => 'boolean',
        'is_featured' => 'boolean',
    ];

    public function photos(): HasMany
    {
        return $this->hasMany(VenuePhoto::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(VenueTag::class, 'venue_tag_map');
    }

    public function favorites()
    {
        return $this->morphMany(Favorite::class, 'favoritable');
    }
}

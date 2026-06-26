<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Venue extends Model
{
    protected $fillable = [
        'name',
        'area',
        'address',
        'maps_link',
        'venue_type',
        'price_range',
        'reservation_required',
        'status',
    ];

    public function photos(): HasMany
    {
        return $this->hasMany(VenuePhoto::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(VenueTag::class, 'venue_tag_map');
    }
}

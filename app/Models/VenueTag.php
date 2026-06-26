<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class VenueTag extends Model
{
    protected $fillable = [
        'name',
        'slug',
    ];

    public function venues(): BelongsToMany
    {
        return $this->belongsToMany(Venue::class, 'venue_tag_map');
    }
}

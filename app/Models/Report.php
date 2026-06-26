<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    protected $fillable = [
        'reporter_id',
        'reported_user_id',
        'reported_hangout_id',
        'reported_venue_id',
        'reason',
        'details',
        'status',
    ];

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function reportedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_user_id');
    }

    public function reportedHangout(): BelongsTo
    {
        return $this->belongsTo(Hangout::class, 'reported_hangout_id');
    }

    public function reportedVenue(): BelongsTo
    {
        return $this->belongsTo(Venue::class, 'reported_venue_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PeerReview extends Model
{
    protected $fillable = ['hangout_id', 'reviewer_id', 'reviewed_user_id', 'rating', 'attendance', 'safety_concern', 'private_notes'];

    protected $casts = ['safety_concern' => 'boolean'];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrustedContact extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'phone_number',
        'email',
        'relation',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

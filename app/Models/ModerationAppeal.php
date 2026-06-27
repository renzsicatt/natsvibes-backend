<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModerationAppeal extends Model
{
    protected $fillable = ['user_id', 'account_status', 'statement', 'status', 'decided_by', 'decision_notes', 'decided_at'];

    protected $casts = ['decided_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}

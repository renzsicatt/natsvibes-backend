<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    protected $fillable = ['push_enabled', 'email_enabled', 'join_updates', 'hangout_updates', 'safety_updates'];

    protected function casts(): array
    {
        return ['push_enabled' => 'boolean', 'email_enabled' => 'boolean', 'join_updates' => 'boolean', 'hangout_updates' => 'boolean', 'safety_updates' => 'boolean'];
    }
}

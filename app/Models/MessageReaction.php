<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageReaction extends Model
{
    protected $fillable = ['user_id', 'emoji'];
}

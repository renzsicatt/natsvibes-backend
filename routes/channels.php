<?php

use App\Models\Hangout;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('hangouts.{hangout}', function (User $user, Hangout $hangout): bool {
    return $user->isAdmin() || $hangout->activeMembers()->whereKey($user->id)->exists();
});

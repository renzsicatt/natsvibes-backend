<?php

namespace App\Events;

use App\Models\GroupMessage;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GroupMessageCreated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public GroupMessage $message) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('hangouts.'.$this->message->hangout_id)];
    }

    public function broadcastAs(): string
    {
        return 'group.message.created';
    }

    public function broadcastWith(): array
    {
        return ['message' => $this->message->loadMissing('sender.profile')->toArray()];
    }
}

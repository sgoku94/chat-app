<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoomMemberChangeEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $changeInfo;

    public function __construct($changeInfo)
    {
        $this->changeInfo = $changeInfo;
    }
    public function broadcastOn()
    {
        return new Channel('changeMember.'.$this->changeInfo['room_id']);
    }

    public function broadcastAs()
    {
        return 'RoomMemberChangeEvent';
    }
}

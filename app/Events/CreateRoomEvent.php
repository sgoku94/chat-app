<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CreateRoomEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $roomInfo;
    public $friendId;

    public function __construct($roomInfo, $friendId)
    {
        $this->roomInfo = $roomInfo;
        $this->friendId = $friendId;
    }

    public function broadcastOn()
    {
        return new Channel('create_room.'.$this->friendId);
    }

    public function broadcastAs()
    {
        return 'CreateRoomEvent';
    }
}

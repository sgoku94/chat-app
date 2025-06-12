<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\User;

class FriendEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $userInfo;
    public $status;
    public $friend_id;

    public function __construct($userInfo ,$status, $friend_id)
    {
        $this->userInfo = [
            'id' => $userInfo->id,
            'name' => $userInfo->name,
            'email' => $userInfo->email
        ];
        $this->status = $status;
        $this->friend_id = $friend_id;
    }

    public function broadcastOn()
    {
        return new Channel('Friend.'.$this->friend_id);
    }

    public function broadcastAs()
    {
        return 'FriendEvent';
    }

}
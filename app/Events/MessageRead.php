<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\LastRead;

class MessageRead implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $roomId;
    public $messages_count;

    public function __construct($roomId, $messages_count)
    {
        $this->roomId = $roomId;
        $this->messages_count = $messages_count;
    }

    public function broadcastOn()
    {
        return new Channel('read.'.$this->roomId);
    }

    public function broadcastAs()
    {
        return 'MessageRead';
    }

}
<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Message;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    protected $name;
    protected $unread_count;
    protected $room_user_id;

    public function __construct(Message $message, $name = null, $unread_count = null, $room_user_id)
    {
        $this->message = $message;
        $this->name = $name;
        $this->unread_count = $unread_count;
        $this->room_user_id = $room_user_id;
    }

    public function broadcastOn()
    {
        return new Channel('chat.'.$this->room_user_id);
    }

    public function broadcastAs()
    {
        return 'MessageSent';
    }

    public function broadcastWith()
    {
        $messageArray = $this->message->toArray();

        $messageArray['name'] = $this->name;
        $messageArray['unread_count'] = $this->unread_count;

        return [
            'message' => $messageArray,
        ];
    }
}

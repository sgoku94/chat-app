<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Broadcasting\PresenceChannel;

class MessageRoom extends Model
{
    use HasFactory;

    protected $table = 'message_rooms';

    protected $fillable = [
        'room_type',
        'room_name',
    ];

    public function broadcastOn()
    {
        return new PresenceChannel('chat.'.$this->id);
    }

    public function messages()
    {
        return $this->hasMany(Message::class, 'room_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'message_room_users', 'room_id', 'user_id');
    }
}

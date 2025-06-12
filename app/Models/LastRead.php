<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LastRead extends Model
{
    protected $table = 'last_reads';

    protected $fillable = [
        'room_id',
        'user_id',
        'message_id',
        'read_at',
    ];

    public function room()
    {
        return $this->belongsTo(MessageRoom::class, 'room_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function message()
    {
        return $this->belongsTo(Message::class);
    }
}
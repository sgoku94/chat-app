<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;
use App\Models\MessageRoomUser;
/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

/* Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
}); */

// test주석
Broadcast::channel('presence_chat.{room_id}', function ($user, $room_id) {
    // 사용자가 해당 채팅방의 멤버인지 확인
    $isMember = MessageRoomUser::where('room_id', $room_id)
        ->where('user_id', $user->id)
        ->exists();
    
    if ($isMember) {
        return [
            'id' => $user->id,
            'room_id' => $room_id
        ];
    }
    
    return false;
});
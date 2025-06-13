<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Message;
use App\Models\LastRead;
use App\Models\MessageRoom;
use App\Models\MessageRoomUser;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Events\CreateRoomEvent;
use App\Events\RoomMemberChangeEvent;

class ChatRoomController extends Controller
{
    private function processChatRoom($roomInfo ,$friendId) {

        broadcast(new CreateRoomEvent($roomInfo, $friendId))->toOthers();

    }
    private function processMemberChange($member ,$count, $room_id) {

        $changeInfo = (object) [
            'member' => $member,
            'count' => $count,
            'room_id' => $room_id
        ];

        broadcast(new RoomMemberChangeEvent((array) $changeInfo))->toOthers();

    }

    public function chatJoin(Request $request){
        $request->validate([
            'friend_id' => 'required|exists:users,id'
        ]);

        try {
            // 이미 존재하는 채팅방 확인
            $existingRoom = MessageRoomUser::where(function ($query) use ($request) {
                $query->where('user_id', auth()->id())
                    ->where('friend_id', $request->friend_id);
            })->orWhere(function ($query) use ($request) {
                $query->where('user_id', $request->friend_id)
                    ->where('friend_id', auth()->id());
            })->first();

            if ($existingRoom) {
                return response()->json(['room_id' => $existingRoom->room_id]);
            }

            // 새로운 채팅방 생성
            $room = MessageRoom::create([
                'room_type' => 'private',
                'room_name' => ''
            ]);

            try {
                // 채팅방 사용자 추가
                $roomUser1 = MessageRoomUser::create([
                    'room_id' => $room->id,
                    'user_id' => auth()->id(),
                    'friend_id' => $request->friend_id,
                    'joined_at' => NULL
                ]);

                $roomUser2 = MessageRoomUser::create([
                    'room_id' => $room->id,
                    'user_id' => $request->friend_id,
                    'friend_id' => auth()->id(),
                    'joined_at' => NULL
                ]);

                return response()->json(['room_id' => $room->id]);

            } catch (\Exception $e) {
                Log::error('채팅방 사용자 추가 실패', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('채팅방 생성 실패: ' . $e->getMessage());
            return response()->json(['error' => '채팅방을 생성할 수 없습니다.'], 500);
        }
    }

    public function chatList()
    {
        $user_id = auth()->id();

        try {
            // Log::info('채팅방 목록 조회 시작');
            
            $chatList = MessageRoomUser::leftJoin('users', 'users.id', '=', 'message_room_users.friend_id')
                ->leftJoin('message_rooms', 'message_rooms.id', '=', 'message_room_users.room_id')
                ->leftJoin('messages', function($join) {
                    $join->on('message_rooms.id', '=', 'messages.room_id')
                        ->whereRaw('messages.created_at = (
                            SELECT MAX(m2.created_at)
                            FROM messages m2
                            WHERE m2.room_id = message_rooms.id
                        )');
                })
                ->where('message_room_users.user_id', auth()->id())
                ->whereNotNull('message_room_users.joined_at')
                ->select(
                    'users.name',
                    'message_rooms.id as room_id',
                    'message_rooms.room_name',
                    'message_rooms.room_type',
                    'message_room_users.joined_at',
                    'messages.content as last_message',
                    'messages.created_at as last_message_time',
                    DB::raw("(
                        SELECT COUNT(*) 
                        FROM messages AS m2 
                        WHERE m2.room_id = message_rooms.id
                        AND m2.id > IFNULL(
                            (
                                SELECT message_id 
                                FROM last_reads 
                                WHERE user_id = {$user_id} 
                                    AND room_id = message_rooms.id
                            ), 
                            0
                        )
                    ) AS unread_count")
                )
                ->orderBy('last_message_time', 'desc');

/*             Log::info('SQL 쿼리:', [
                'sql' => $chatList->toSql(),
                'bindings' => $chatList->getBindings()
            ]); */

            $result = $chatList->get();
            
            // Log::info('채팅방 목록 조회 성공', ['count' => $result->count()]);
            
            return response()->json($result);
        } catch (\Exception $e) {
/*             Log::error('채팅방 목록 조회 실패', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]); */
            return response()->json(['error' => '채팅방 목록을 불러올 수 없습니다.'], 500);
        }
    }

    public function createGroupChat(Request $request){
        $validated = $request->validate([
            'room_name' => 'required|string|max:255',
            'friends' => 'required|array',
            'friends.*' => 'exists:users,id',
        ]);

        $room_name = $validated['room_name'];
        $friends_arr = $validated['friends'];

        // 새로운 채팅방 생성
        $room = MessageRoom::create([
            'room_type' => 'group',
            'room_name' => $room_name
        ]);

        $friends = implode(',', $friends_arr);

        MessageRoomUser::create([
            'room_id' => $room->id,
            'user_id' => auth()->id(),
            'friend_id' => null,
            'joined_at' => now()
        ]);

        foreach ($friends_arr as $friendId) {
            MessageRoomUser::create([
                'room_id' => $room->id,
                'user_id' => $friendId,
                'friend_id' => null,
                'joined_at' => now()
            ]);

            $roomInfo = DB::table('message_rooms')
                ->where('message_rooms.id', $room->id)
                ->select(
                    'message_rooms.id as room_id',
                    'message_rooms.room_name',
                    'message_rooms.room_type',
                    'message_rooms.created_at as last_message_time',
                )
                ->first();

            if ($roomInfo) {
                $roomInfo->last_message = '';
                $roomInfo->joined_at = '';
                $roomInfo->unread_count = 0;
                $roomInfo->name = '';
            }
            $this -> processChatRoom((array) $roomInfo ,$friendId);
        }

        return response()->json([
            'success' => true,
            'room_id' => $room->id,
            'roomInfo' => $roomInfo,
        ]);
    }

    public function chat_leave(Request $request){
        $validated = $request->validate([
            'room_id' => 'required|string',
            'room_type' => 'required|string',
            'action' => 'required|string',
            'user_arr' => 'required|array'
        ]);
        $room_id = $validated['room_id'];
        $room_type = $validated['room_type'];
        $user_arr = $validated['user_arr'];
        $action = $validated['action'];

        if($room_type == 'private'){
            MessageRoomUser::where('user_id', auth()->id())
            ->where('room_id', $room_id)
            ->update(['joined_at' => null]);
        }else{
            $room_user_count = MessageRoomUser::where('room_id', $room_id)->count();
            if($room_user_count <= 1){
                MessageRoom::where('id', $room_id)
                ->delete();
            }

            MessageRoomUser::where('user_id', auth()->id())
            ->where('room_id', $room_id)
            ->delete();

            $ids = array_map(fn($j) => $j['id'], $user_arr);
            $room_info = DB::table('message_rooms as a')
                ->selectRaw('
                    COUNT(b.user_id) AS user_count,
                    GROUP_CONCAT(c.name) AS user_names
                ')
                ->leftJoin('message_room_users as b', 'b.room_id', '=', 'a.id')
                ->leftJoin('users as c', 'b.user_id', '=', 'c.id')
                ->where('a.id', $room_id)
                ->groupBy('a.id', 'a.room_type', 'a.room_name')
                ->limit(1)
                ->first();

            foreach ($ids as $userId) {
                if($userId !== auth()->id()){
                    $this -> processMemberChange( $room_info->user_names, $room_info->user_count, $room_id);
                }
            }
        }

        return response()->json([
            'success' => true,
            'room_id' => $room_id
        ]);
    }

    public function chat_invite(Request $request){

        $validated = $request->validate([
            'room_id' => 'required|string',
            'room_type' => 'required|string',
            'friend_arr' => 'required|array',
            'user_arr' => 'required|array',
            'action' => 'required|string'
        ]);
        $room_id = $validated['room_id'];
        $room_type = $validated['room_type'];
        $user_arr = $validated['user_arr'];
        $friend_arr = $validated['friend_arr'];
        $action = $validated['action'];
        $latestMessageId = Message::where('room_id', $room_id)
                            ->where('action','message')
                            ->latest('id')
                            ->value('id');
   
        foreach ($friend_arr as $friendId) {
        
            $chkJoined = MessageRoomUser::where('room_id', $room_id)
                        ->where('user_id', $friendId)
                        ->first();

            if(!$chkJoined){
                MessageRoomUser::create([
                    'room_id' => $room_id,
                    'user_id' => $friendId,
                    'friend_id' => null,
                    'joined_at' => now()
                ]);
    
                if ($latestMessageId > 0 && Message::find($latestMessageId)) {
                    LastRead::updateOrCreate(
                        [
                            'room_id' => $room_id,
                            'user_id' => $friendId,
                        ],
                        [
                            'message_id' => $latestMessageId ?? 0,
                            'read_at' => now(),
                        ]
                    );
                }

                $roomInfo = DB::table('message_rooms')
                    ->where('message_rooms.id', $room_id)
                    ->select(
                        'message_rooms.id as room_id',
                        'message_rooms.room_name',
                        'message_rooms.room_type',
                        'message_rooms.created_at as last_message_time',
                    )
                    ->first();

                if ($roomInfo) {
                    $roomInfo->last_message = '';
                    $roomInfo->joined_at = '';
                    $roomInfo->unread_count = 0;
                    $roomInfo->name = '';
                }
                $this -> processChatRoom((array) $roomInfo ,$friendId);
            }

        }

        $room_info = DB::table('message_rooms as a')
            ->selectRaw('
                COUNT(b.user_id) AS user_count,
                GROUP_CONCAT(c.name) AS user_names
            ')
            ->leftJoin('message_room_users as b', 'b.room_id', '=', 'a.id')
            ->leftJoin('users as c', 'b.user_id', '=', 'c.id')
            ->where('a.id', $room_id)
            ->groupBy('a.id', 'a.room_type', 'a.room_name')
            ->limit(1)
            ->first();

        $ids = array_map(fn($j) => $j['id'], $user_arr);
        foreach ($ids as $userId) {
            if($userId !== auth()->id()){
                $this -> processMemberChange( $room_info->user_names, $room_info->user_count, $room_id);
            }
        }

        return response()->json([
            'success' => true,
            'user_names' => $room_info->user_names,
            'user_count' => $room_info->user_count
        ]);
    }
}
<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Message;
use App\Models\LastRead;
use App\Models\MessageRoom;
use App\Models\MessageRoomUser;
use Illuminate\Http\Request;
use App\Events\MessageSent;
use App\Events\MessageRead;
use App\Events\CreateRoomEvent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MessageController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request)
    {
        $validated = $request->validate([
            'room_id' => 'required|string'
        ]);

        $room_id = $validated['room_id'];
        $user_id = auth()->id();

        try {
            $room_info = DB::table('message_rooms as a')
            ->select(
                'a.id',
                'a.room_type',
                'a.room_name',
                DB::raw('COUNT(b.user_id) AS user_count'),
                DB::raw("GROUP_CONCAT(c.name) AS user_names")
            )
            ->leftJoin('message_room_users as b', 'b.room_id', '=', 'a.id')
            ->leftJoin('users as c', 'b.user_id', '=', 'c.id')
            ->where('a.id', $room_id)
            ->groupBy('a.id', 'a.room_type', 'a.room_name')
            ->first();

            $totalMembers = $room_info->user_count;

            $this->processRead($room_id, $totalMembers);

            $messages = DB::table('messages as m')
                    ->leftJoin('users as u', 'u.id', '=', 'm.user_id')
                    ->leftJoin('message_room_users as r', function ($join) use ($user_id) {
                        $join->on('r.room_id', '=', 'm.room_id')
                            ->where('r.user_id', '=', $user_id);
                    })
                    ->select([
                        'm.*', 'u.name',
                        DB::raw("GREATEST(0, $totalMembers - (
                            SELECT COUNT(*) FROM last_reads as lr
                            WHERE lr.room_id = {$room_id}
                            AND lr.message_id >= m.id
                        )) AS unread_count")
                    ])
                    ->where('m.room_id', $room_id)
                    ->whereColumn('m.created_at', '>=', 'r.joined_at')
                    ->orderBy('m.created_at', 'asc')
                    ->get();

            return response()->json([
                'roomInfo' => $room_info,
                'messages' => $messages,
            ]);

        } catch (\Exception $e) {
            Log::error('메시지 목록 조회 실패: ' . $e->getMessage());
            return response()->json(['error' => '메시지 목록을 불러올 수 없습니다.'], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'content' => 'required|string',
                'room_id' => 'required|string',
                'action' => 'required|string',
                'user_arr' => 'required|array'
            ]);
            // return false;
            $room_id = $validated['room_id'];
            $action = $validated['action'];
            $join_user_arr = $validated['user_arr'];
            $name = User::where('id', Auth::id())->value('name');

            $message = Message::create([
                'content' => $validated['content'],
                'user_id' => Auth::id(),
                'room_id' => $validated['room_id'],
                'action' => $action
            ]);

            $room = MessageRoom::findOrFail($room_id);

            $totalMembers = $room->users()
                ->count();

            // 1:1 채팅방 경우 첫 메시지 발송 및 수신 채팅방 리스트에 노출 start
            if($action == 'message'){
                $first_msg_chk = MessageRoomUser::where('room_id', $room_id)
                                    ->whereNull('joined_at')
                                    ->get();
                if ($first_msg_chk->count() > 0) {
                    $roomInfo = (object) [
                        'room_id' => $room->id,
                        'room_type' => $room->room_type,
                        'last_message' => $message->content,
                        'last_message_time' => $message->created_at,
                        'joined_at' => now(),
                        'unread_count' => 0,
                        'room_name' => '',
                    ];
                    
                    foreach ($first_msg_chk as $user) {
    
                        $user->joined_at = now();
                        $user->save();
    
                        if ($user->user_id == auth()->id()) {
                            $otherName = User::where('id', $user->friend_id)->value('name');
                            $myRoomInfo = clone $roomInfo;
                            $myRoomInfo->name = $otherName;
                        } else {
                            $otherRoomInfo = clone $roomInfo;
                            $otherRoomInfo->name = $name;
    
                            broadcast(new CreateRoomEvent((array) $otherRoomInfo, $user->user_id))->toOthers();
                        }
                    }
                }
            }
            // 1:1 채팅방 경우 첫 메시지 발송 시 채팅방 리스트에 노출 end

            // 입장된 유저 수에 따른 카운터 처리
            $joinUserCount = count($join_user_arr);

            $unread_count = $totalMembers - $joinUserCount;

            $ids = array_map(fn($j) => $j['id'], $join_user_arr);
            foreach ($ids as $userId) {
                LastRead::updateOrCreate(
                    [
                        'room_id' => $room_id,
                        'user_id' => $userId,
                    ],
                    [
                        'message_id' => $message->id,
                        'read_at' => now(),
                    ]
                );
            }
            
            $room_users = MessageRoomUser::where('room_id', $room_id)
                            ->where('user_id', '!=', auth()->id())
                            ->select('user_id')
                            ->get();
            foreach ($room_users as $room_user) {
                $room_user_id = $room_user->user_id;

                broadcast(new MessageSent($message, $name, $unread_count,$room_user_id))->toOthers();
            }
            // 입장된 유저 수에 따른 카운터 처리 end

            $message['unread_count'] = $unread_count;
            $message['name'] = $name;
            return response()->json([
                'message' => $message,
                'myRoomInfo' =>$myRoomInfo ?? null
            ]);
        } catch (\Exception $e) {
            Log::error('메시지 저장 실패: ' . $e->getMessage());
            return response()->json(['error' => '메시지를 저장할 수 없습니다.'], 500);
        }
    }

    private function processChatRoom($roomInfo ,$friendId) {

        broadcast(new CreateRoomEvent($roomInfo, $friendId))->toOthers();

    }

    private function processRead($room_id, $totalMembers) {

        $user_id = auth()->id();

        $lastMessageId = Message::where('room_id', $room_id)
                        ->orderBy('id', 'desc')
                        ->value('id');

        if($lastMessageId){
            LastRead::updateOrCreate(
                [
                    'room_id' => $room_id,
                    'user_id' => auth()->id(),
                ],
                [
                    'message_id' => $lastMessageId,
                    'read_at' => now(),
                ]
            );
    
            $messages_count =  DB::table('messages as m')
                ->select([
                    'm.id as message_id',
                    DB::raw("$totalMembers - (
                        SELECT COUNT(*) FROM last_reads as lr
                        WHERE lr.room_id = {$room_id}
                        AND lr.message_id >= m.id
                    ) AS unread_count")
                ])
                ->where('m.room_id', $room_id)
                ->having('unread_count', '>', 0)
                ->orderBy('m.created_at', 'asc')
                ->get();
            // 읽음 이벤트 발생
            broadcast(new MessageRead($room_id, $messages_count))->toOthers();
        }


        return response()->json([
            'success' => true
        ]);

    }
}

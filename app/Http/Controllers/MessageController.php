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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

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
                DB::raw("GROUP_CONCAT(IF(c.id != {$user_id}, c.name, NULL)) AS user_names")
            )
            ->leftJoin('message_room_users as b', 'b.room_id', '=', 'a.id')
            ->leftJoin('users as c', 'b.user_id', '=', 'c.id')
            ->where('a.id', $room_id)
            ->groupBy('a.id', 'a.room_type', 'a.room_name')
            ->first();

            $key = "chat_room:{$room_id}:users";

            $userData = json_encode([
                'id' => $user_id,
                'joined_at' => now()->toDateTimeString(),
            ]);

            Redis::hset($key, $user_id, $userData);
            // Redis::expire($key, 300);

            $totalMembers = $room_info->user_count;

            $this->processRead($room_id, $totalMembers);

            $messages = DB::table('messages as m')
                    ->leftJoin('users as u', 'u.id', '=', 'm.user_id')
                    ->select([
                        'm.*', 'u.name',
                        DB::raw("$totalMembers - (
                            SELECT COUNT(*) FROM last_reads as lr
                            WHERE lr.room_id = {$room_id}
                            AND lr.message_id >= m.id
                        ) AS unread_count")
                    ])
                    ->where('m.room_id', $room_id)
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
                'room_id' => 'required|string'
            ]);

            $room_id = $validated['room_id'];
            // Log::info('메시지 저장 시도', ['content' => $validated['content']]);

            $message = Message::create([
                'content' => $validated['content'],
                'user_id' => Auth::id(),
                'room_id' => $validated['room_id']
            ]);

            $room = MessageRoom::findOrFail($room_id);

            $totalMembers = $room->users()
                ->count();


            // Log::info('메시지 저장 성공', ['message_id' => $message->id]);
            $key = "chat_room:{$room_id}:users";
            $joinUserDate = Redis::hgetall($key);
            $joinUserCount = Redis::hlen($key);

            $name = User::where('id', Auth::id())->value('name');
            $unread_count = $totalMembers - $joinUserCount;

            $ids = array_filter(array_map(fn($j) => json_decode($j, true)['id'] ?? null, $joinUserDate));

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

            $message['unread_count'] = $unread_count;
            $message['name'] = $name;
            return response()->json([
                $message
            ]);
        } catch (\Exception $e) {
            Log::error('메시지 저장 실패: ' . $e->getMessage());
            return response()->json(['error' => '메시지를 저장할 수 없습니다.'], 500);
        }
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

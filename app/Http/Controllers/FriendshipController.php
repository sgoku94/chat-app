<?php

namespace App\Http\Controllers;

use App\Models\Friendship;
use App\Models\User;
use Illuminate\Http\Request;
use App\Events\FriendEvent;

class FriendshipController extends Controller
{
    private function processFriend($status, $friend_id) {

        $me = User::select('id', 'name', 'email')->find(auth()->id());
        broadcast(new FriendEvent($me, $status, $friend_id))->toOthers();
    }

    public function addFriend(Request $request)
    {
        $validated = $request->validate([
            'friend_id' => 'required|exists:users,id'
        ]);

        $friend_id = $validated['friend_id'];

        // 자기 자신을 친구로 추가하는 것 방지
        if ($request->friend_id === auth()->id()) {
            return response()->json(['message' => '자기 자신을 친구로 추가할 수 없습니다.'], 400);
        }

        // 이미 친구 관계가 있는지 확인
        $existingFriendship = Friendship::where(function($query) use ($request) {
            $query->where('user_id', auth()->id())
                  ->where('friend_id', $request->friend_id);
        })->orWhere(function($query) use ($request) {
            $query->where('user_id', $request->friend_id)
                  ->where('friend_id', auth()->id());
        })->first();

        if ($existingFriendship) {
            return response()->json(['message' => '이미 친구 관계가 존재합니다.'], 400);
        }

        Friendship::create([
            'user_id' => auth()->id(),
            'friend_id' => $request->friend_id,
            'status' => 'request'
        ]);

        Friendship::create([
            'user_id' => $request->friend_id,
            'friend_id' => auth()->id(),
            'status' => 'pending'
        ]);

        $this -> processFriend('request', $friend_id);

        return response()->json([
            'message' => '친구 요청이 전송되었습니다.'
        ]);
    }

    public function removeFriend(Request $request)
    {
        $request->validate([
            'friend_id' => 'required|exists:users,id',
            'type' => 'required|string|max:255'
        ]);

        Friendship::where(function ($query) use ($request) {
            $query->where('user_id', auth()->id())
                ->where('friend_id', $request->friend_id);
            })->orWhere(function ($query) use ($request) {
                $query->where('user_id', $request->friend_id)
                    ->where('friend_id', auth()->id());
            })->delete();

        $this -> processFriend($request->type, $request->friend_id);

        return response()->json(['success' => true]);
    }

    public function requestFriend()
    {
        try {
            return Friendship::leftJoin('users', 'users.id', '=', 'friendships.user_id')
                ->where('friendships.status', 'request')
                ->where('friendships.friend_id', auth()->id())
                ->select('users.name','users.email','users.id')
                ->orderBy('friendships.created_at', 'asc')
                ->get();

        } catch (\Exception $e) {
            Log::error('친구 요청받은 목록 조회 실패: ' . $e->getMessage());
            return response()->json(['error' => '친구 요청받은 목록을 불러올 수 없습니다.'], 500);
        }
    }

    public function acceptFriend(Request $request){

        $validated = $request->validate([
            'friend_id' => 'required|exists:users,id'
        ]);

        try {
            $friend_id = $validated['friend_id'];

            Friendship::where(function ($query) use ($request) {
            $query->where('user_id', auth()->id())
                    ->where('friend_id', $request->friend_id);
            })->orWhere(function ($query) use ($request) {
                $query->where('user_id', $request->friend_id)
                    ->where('friend_id', auth()->id());
            })->update(['status' => 'accepted']);

            $friend = User::select('id', 'name', 'email')->find($friend_id);

            $this -> processFriend('accept', $friend_id);
            // broadcast(new FriendEvent($me, 'accept', $friend_id))->toOthers();
            
            return response()->json([
                'message' => '받은 요청을 수락하였습니다.',
                'user' => $friend
            ]);

        } catch (\Exception $e) {
            Log::error('받은 요청을 수락 실패: ' . $e->getMessage());
            return response()->json(['error' => '받은 요청을 수락에 실패했습니다.'], 500);
        }

    }

    public function rejectFriend(Request $request){

        $validated = $request->validate([
            'friend_id' => 'required|exists:users,id'
        ]);

        $friend_id = $validated['friend_id'];

        Friendship::where(function ($query) use ($request) {
        $query->where('user_id', auth()->id())
                ->where('friend_id', $request->friend_id);
        })->orWhere(function ($query) use ($request) {
            $query->where('user_id', $request->friend_id)
                ->where('friend_id', auth()->id());
        })->delete();

        $friend = User::select('id', 'name', 'email')->find($friend_id);

        return response()->json([
            'message' => '받은 요청을 거절하였습니다.',
            'user' => $friend
        ]);
    }

    public function listFriend(){
        try {
            return Friendship::leftJoin('users', 'users.id', '=', 'friendships.friend_id')
                    ->where('friendships.status', 'accepted')
                    ->where('friendships.user_id', auth()->id())
                    ->select('users.id','users.name','users.email')
                    ->orderBy('users.name', 'asc')
                    ->get();

        } catch (\Exception $e) {
            Log::error('친구 목록 조회 실패: ' . $e->getMessage());
            return response()->json(['error' => '친구 목록을 불러올 수 없습니다.'], 500);
        }
    }

/*     public function deleteFriend(Request $request){

        $request->validate([
            'friend_id' => 'required|exists:users,id'
        ]);

        try {

            Friendship::where(function ($query) use ($request) {
            $query->where('user_id', auth()->id())
                    ->where('friend_id', $request->friend_id);
            })->orWhere(function ($query) use ($request) {
                $query->where('user_id', $request->friend_id)
                    ->where('friend_id', auth()->id());
            })->delete();

            $this -> processFriend('delete', $request->friend_id);
                
            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            Log::error('친구 삭제 실패: ' . $e->getMessage());
            return response()->json(['error' => '친구 삭제 실패했습니다.'], 500);
        }
    } */
}
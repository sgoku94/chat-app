<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function search(Request $request)
    {
        try {
            $name = $request->query('name');
            
            if (empty($name)) {
                return response()->json([]);
            }

            $query = User::where('name', $name);
            
            // 현재 로그인한 사용자가 있는 경우에만 제외
            if (auth()->check()) {
                $query->where('id', '!=', auth()->id());
            }

            $user = $query->select('id', 'name', 'email')
                ->first();

            if($user){
                $user2 = User::leftJoin('friendships', function ($join) {
                    $join->on('friendships.friend_id', '=', 'users.id')
                        ->where('friendships.user_id', '=', auth()->id());
                })
                ->where('users.name', $name)
                ->select('users.id', 'users.name', 'users.email', 'friendships.status')
                ->get();

                return response()->json( $user2 );
            } else {
                return response()->json(['message' => '검색 결과가 없습니다.'], 404); // 사용자 없음 처리
            }

        } catch (\Exception $e) {
            \Log::error('User search error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\FriendshipController;
use App\Http\Controllers\ChatRoomController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// 인증 관련 라우트
Route::middleware('web')->group(function () {
    Route::post('/login', [LoginController::class, 'login']);
    Route::post('/register', [RegisterController::class, 'register']);
    Route::post('/logout', [LoginController::class, 'logout']);
});

// 인증이 필요한 라우트
Route::middleware('auth:sanctum')->group(function () {
    // 사용자 정보
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // 사용자 검색
    Route::get('/users/search', [UserController::class, 'search']);

    // 메시지 관련
    Route::get('/messages', [MessageController::class, 'index']);
    Route::post('/messages', [MessageController::class, 'store']);
    Route::post('/messages/read', [MessageController::class, 'read']);
    Route::post('/messages/unread-count', [MessageController::class, 'getUnreadCount']);
    
    // 친구 관련
    Route::prefix('friends')->group(function () {
        Route::get('/request', [FriendshipController::class, 'requestFriend']);
        Route::get('/list', [FriendshipController::class, 'listFriend']);
        Route::post('/add', [FriendshipController::class, 'addFriend']);
        Route::post('/remove', [FriendshipController::class, 'removeFriend']);
        Route::post('/accept', [FriendshipController::class, 'acceptFriend']);
        Route::post('/reject', [FriendshipController::class, 'rejectFriend']);
    });
    
    // 채팅방 관련
    Route::post('/chat/join', [ChatRoomController::class, 'chatJoin']);
    Route::get('/chat/list', [ChatRoomController::class, 'chatList']);
    Route::post('/chat/leave', [ChatRoomController::class, 'chat_leave']);
    Route::post('/chat/invite', [ChatRoomController::class, 'chat_invite']);
    Route::post('/chat/groupChat', [ChatRoomController::class, 'createGroupChat']);
});
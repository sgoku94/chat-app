<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatRoomController;
use App\Http\Controllers\Auth\LoginController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');

/* Route::middleware(['auth'])->group(function () {
    Route::post('/api/chat/join', [ChatRoomController::class, 'chatJoin']);
}); */

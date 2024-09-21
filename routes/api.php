<?php

use App\Http\Controllers\ConversationsController;
use App\Http\Controllers\MessagesController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {

    Route::group([
        'controller' => ConversationsController::class,
        'prefix'     => 'conversations',
    ], function () {
        Route::get('/',  'index');
        Route::get('/{conversation}',  'show');
        Route::put('/{conversation}/read',  'markAsRead');
        Route::post('/{conversation}/participants',  'appParticipant');
        Route::delete('/{conversation}/participants',  'removeParticipant');
    });

    Route::group([
        'controller' => MessagesController::class,
    ], function () {
        Route::post('messages', 'store');
        Route::delete('messages/{message_id}', 'destroy');
        Route::get('conversations/{conversation_id}/messages', 'index');
    });
});

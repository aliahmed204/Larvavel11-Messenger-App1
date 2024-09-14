<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MessengerController extends Controller
{
    public function index($conversation_id = null)
    {
        /** @var User $user */
        $user = Auth::user();
        $friends = User::query()
            ->where('id', '<>', $user->id)
            ->orderBy('name')
            ->paginate();

        $conversations = $user->conversations()->with([
            'participants' => fn($q) => $q->where('user_id', '<>', $user->id),
            'lastMessage'
        ])->get();

        $messages = [];
        $chat = null;
        // to enter conversation peer me to one
        if ($conversation_id)
        {
            $chat = $conversations->where('id', $conversation_id)->load([
                'participants' => fn($q) => $q->where('user_id', '<>', $user->id),
                'lastMessage'
            ])->first();
            $messages = $chat->messages()->with('user')->paginate();
        }

        return view('messenger', compact('friends', 'conversations', 'messages', 'chat'));
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\Request;
class ConversationsController extends Controller
{
    public function index()
    {
        /** @var User $user */

        $user = auth()->user();
        // order by last message sent in user conversations
        return $user->conversations()->with([
            'participants' => fn($q) => $q->where('user_id', '<>', $user->id),
            'lastMessage'
        ])->paginate();
    }

    public function show(Conversation $conversation)
    {
        return $conversation->load('participants');
    }

    public function appParticipant(Request $request, Conversation $conversation)
    {
        $request->validate([
            'user_id' => 'required|int|exists:users,id',
        ]);

        // should make sure user not existing already
        if ($check = $conversation->participants()->where('user_id', $request->user_id)->first()){
            return [
                'message' => 'User '.$check->name.' already in conversation',
            ];
        }

        // add user to conversation
        $conversation->participants()->attach([
            $request->user_id => ['joined_at' => now()]
        ]);

        $conversation->refresh();

        return $conversation->participants->map(fn($participant) => $participant->only(['id', 'name']));
    }
    public function removeParticipant(Request $request, Conversation $conversation)
    {
        $request->validate([
            'user_id' => 'required|int|exists:users,id',
        ]);

        $conversation->participants()->detach($request->user_id);

        $conversation->refresh();

        return $conversation->participants;
    }



}

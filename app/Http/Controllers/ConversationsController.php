<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Recipient;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
        ])
            ->withCount([
                'recipients as new_messages' => function($builder) use ($user) {
                    $builder->where('recipients.user_id', '=', $user->id)
                        ->whereNull('read_at');
                }
            ])
            ->paginate();
    }

    public function show($id)
    {
        $user = auth()->user();

        return $user->conversations()->with([
            'lastMessage',
            'participants' =>fn($q) => $q->where('user_id', '<>', $user->id),
            ])
            ->withCount([
                'recipients as new_messages' => function($builder) use ($user) {
                    $builder->where('recipients.user_id', '=', $user->id)
                        ->whereNull('read_at');
                }
            ])
            ->findOrFail($id);
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

    // when auth user open conversation -> mark all message to read in that conversation
    public function markAsRead($id)
    {
        $messages = Message::query()->select('id')->where('conversation_id', $id)->get();
        $recipients = Recipient::query()
            ->where('user_id', Auth::id())
            ->whereNull('read_at')
            ->whereIn('message_id', $messages->toArray())
            ->update([
                'read_at' => Carbon::now()
            ]);

        return [
            'message'    => 'Messages marked as read',
            'messages' => $recipients
        ];

        /*
           Recipient::query()
            ->where('user_id', Auth::id())
            ->whereNull('read_at')
            ->selectRaw('message_id IN (
                SELECT id FROM messages WHERE conversation_id = ?
            )', [$id])
            ->update([
                'read_at' => Carbon::now()
            ]);
        */

    }

    // delete all messages from conversation for one user only
    public function destroy($id)
    {
        Recipient::query()
            ->where('user_id', Auth::id())
            ->selectRaw('message_id IN (
                SELECT id FROM messages WHERE conversation_id = ?
            )', [$id])
            ->delete();

        return [
            'message' => 'Conversation deleted successfully'
        ];
    }

}

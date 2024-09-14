<?php

namespace App\Http\Controllers;

use App\Events\MessageCreated;
use App\Models\Conversation;
use App\Models\Recipients;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** @var User $user */
class MessagesController extends Controller
{
    /**
     * Display a listing of the Messages based on conversation.
     */
    public function index($conversation_id)
    {
        /** @var User $user */
        /** @var Conversation $conversation */

        $user = auth()->user();
        $conversation = $user->conversations()
            ->with([
                'participants' => fn($builder) => $builder->where('id', '<>', $user->id)
            ])
            ->findOrFail($conversation_id);

        $messages = $conversation->messages()
            ->with('user')
//            ->latest()
            ->paginate();

        return [
            'conversation' => $conversation,
            'messages' => $messages,
        ];
    }

    /**
     * me and user has only one conversation [peer]
     * user_id in conversation is the sender and in recipients table I put both sender and receivers in one conversation
     */
    public function store(Request $request)
    {
        $request->validate([
            'message' => ['required', 'string'],
            'conversation_id' => [
                Rule::requiredIf( ! $request->has('receiver_id')),
                'int',
                'exists:conversations,id'], // first time not exists
            'receiver_id' => [
                Rule::requiredIf( ! $request->has('conversation_id')),
                'int',
                'exists:users,id'
            ],
        ]);

        /** @var User $sender */
        /** @var Conversation $conversation */

        $sender = auth()->user(); //User::find(11); //

        $conversation_id = $request->post('conversation_id');
        $receiver_id = $request->post('receiver_id');

        DB::beginTransaction();

        try {

            if ($conversation_id) {
                $conversation = $sender->conversations()->findOrFail($request->conversation_id);
            }else{
                // if no conversation id in request
                // will try to get it through sender and receiver from participants table
                // if it is not found as peer conversation than will create new one

                $conversation = Conversation::query()
                    ->where('type', 'peer') // محادثات فردية
                    ->whereHas('participants', function ($query) use ($receiver_id, $sender) {
                        $query->join('participants as participants2', 'participants2.conversation_id', '=', 'participants.conversation_id')
                            ->where('participants.user_id', $sender->id)
                            ->where('participants2.user_id', $receiver_id);
                    })
                    ->first();

                if (! $conversation) {
                    $conversation = Conversation::create([
                        'user_id' => $sender->id,
                        'type' => 'peer',
                    ]);

                    $conversation->participants()->attach([
                        $sender->id => ['joined_at' => now()],
                        $receiver_id => ['joined_at' => now()]
                    ]);
                }
            }

            $message = $conversation->messages()->create([
                'user_id' => $sender->id, // auth user how sent message
                'body' => $request->message,
            ]);
            $conversation->update(['last_message_id' => $message->id]);

            DB::statement('
                INSERT INTO `recipients` (`user_id`, `message_id`)
                SELECT `user_id`, ? FROM participants
                WHERE conversation_id = ?
            ',[$message->id, $conversation->id]); // insert into recipients table column user_id, message_id
            // values from select { every user in table participants for this conversation_id }

            broadcast(new MessageCreated($message));

            DB::commit();
        }catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return $message;
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $message_id)
    {
        // edit message body
    }

    /**
     * Remove from recipients not the message itself
     */
    public function destroy(string $message_id)
    {
        /** @var User $user */
        // delete message from recipients table
        $user = auth()->user();
        $user->receivedMessages()->where('message_id', $message_id)->delete();

        return [
            'message' => 'deleted'
        ];
    }
}

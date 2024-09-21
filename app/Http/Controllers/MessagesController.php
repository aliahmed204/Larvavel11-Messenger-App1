<?php

namespace App\Http\Controllers;

use App\Events\MessageCreated;
use App\Models\Conversation;
use App\Models\Recipient;
use App\Models\User;
use Carbon\Carbon;
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
            ->where(function($query) use ($user) {
                $query
                    ->where(function($query) use ($user) {
                        $query->where('user_id', $user->id)
                            ->whereNull('deleted_at');
                    })
                    ->orWhereRaw('id IN (
                        SELECT message_id FROM recipients
                        WHERE recipients.message_id = messages.id
                        AND recipients.user_id = ?
                        AND recipients.deleted_at IS NULL
                    )', [$user->id]);
            })
            ->latest()
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
            'message' => ['required_without:attachment','nullable', 'string'],
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

        $sender = auth()->user(); //User::find(11); // // //

        $conversation_id = $request->post('conversation_id');
        $receiver_id = $request->post('receiver_id');

        DB::beginTransaction();

        try {

            if ($conversation_id) {
                // get it
                $conversation = $sender->conversations()->findOrFail($request->conversation_id);
            }else{
                // if no conversation id in request -> ceate new one
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

            $type = 'text';
            $msg = $request->post('message');
            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $msg = [
                    'file_name' => $file->getClientOriginalName(),
                    'file_size' => $file->getSize(),
                    'mimetype' => $file->getMimeType(),
                    'file_path' => $file->store('attachments', [
                        'disk' => 'public'
                    ]),
                ];
                $type = 'attachment';
            }
            $message = $conversation->messages()->create([
                'user_id' => $sender->id, // auth user how sent message
                'body' => $msg,
                'type' => $type,
            ]);
            $conversation->update(['last_message_id' => $message->id]);

            $message->refresh();

            // recipients that will receive the message sender should not be with them
            DB::statement('
                INSERT INTO `recipients` (`user_id`, `message_id`)
                SELECT `user_id`, ? FROM participants
                WHERE conversation_id = ?
                AND user_id <> ?
            ',[$message->id, $conversation->id, $sender->id]); // insert into recipients table column user_id, message_id
            // values from select { every user in table participants for this conversation_id }

            $message->load('user');

            broadcast(new MessageCreated($message));

            DB::commit();

            //return response()->json($message->load('user'));

        }catch (\Exception $e) {
            DB::rollBack();
            return $e;
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
    public function destroy(string $message_id, Request $request)
    {
        /** @var User $user */
        // delete message from recipients table
        $user = auth()->user();

        // if I want to delete message that I sent
        $user->sentMessages()
            ->where('id', '=', $message_id)
            ->update([
                'deleted_at' => Carbon::now(),
            ]);

        if ($request->target == 'me') {

            Recipient::where([
                'user_id' => $user->id,
                'message_id' => $message_id,
            ])->delete();

        } else {
            // if group of users are receivers
            Recipient::where([
                'message_id' => $message_id,
            ])->delete();
        }

        return [
            'message' => 'deleted',
        ];

    }
}

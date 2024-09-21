<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable =[
        'user_id','label', 'type','last_message_id'
    ];

    public function recipients()
    {
        return $this->hasManyThrough(
            Recipient::class,
            Message::class,
            'conversation_id',
            'message_id',
            'id',
            'id'
        );
    }

    public function participants()
    {
        return $this->belongsToMany(User::class, 'participants')
            ->withPivot([
                'role', 'joined_at'
            ]);
    }// participants => user has many conversations , conversation has many users

    public function messages()
    {
        return $this->hasMany(Message::class, 'conversation_id', 'id');
    }// messages => conversation has many messages , message belongs to one conversation

    public function lastMessage()
    {
        return $this->belongsTo(Message::class, 'last_message_id', 'id')
            ->withDefault();
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }// owner who created conversation
}

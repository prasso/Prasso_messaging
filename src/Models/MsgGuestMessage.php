<?php

namespace Prasso\Messaging\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * This model is for logging which messages have been sent to which guests.
 */
class MsgGuestMessage extends Model
{
    use HasFactory;


    protected $table = 'msg_guest_messages';

    protected $fillable = ['guest_id', 'message_id', 'is_sent'];

    // Relationships

    public function guest()
    {
        return $this->belongsTo(MsgGuest::class, 'guest_id');
    }

    public function message()
    {
        return $this->belongsTo(MsgMessage::class, 'message_id');
    }
}

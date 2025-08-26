<?php

namespace Prasso\Messaging\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MsgInboundMessage extends Model
{
    use HasFactory;

    protected $table = 'msg_inbound_messages';

    protected $fillable = [
        'team_id',
        'msg_guest_id',
        'from',
        'to',
        'body',
        'media',
        'provider_message_id',
        'received_at',
        'raw',
    ];

    protected $casts = [
        'media' => 'array',
        'raw' => 'array',
        'received_at' => 'datetime',
    ];

    public function guest()
    {
        return $this->belongsTo(MsgGuest::class, 'msg_guest_id');
    }
}

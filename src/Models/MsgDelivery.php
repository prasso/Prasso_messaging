<?php

namespace Prasso\Messaging\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Prasso\Messaging\Models\MsgGuest;

class MsgDelivery extends Model
{
    use HasFactory;

    protected $table = 'msg_deliveries';

    protected $fillable = [
        'team_id',
        'msg_message_id',
        'recipient_type',
        'recipient_id',
        'channel',
        'status',
        'provider_message_id',
        'error',
        'metadata',
        'send_at',
        'sent_at',
        'delivered_at',
        'failed_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'send_at' => 'datetime',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function message()
    {
        return $this->belongsTo(MsgMessage::class, 'msg_message_id');
    }

    public function recipient()
    {
        return $this->morphTo();
    }

    public function replies()
    {
        // Link to inbound messages that are replies to this specific delivery
        return $this->hasMany(MsgInboundMessage::class, 'msg_delivery_id', 'id');
    }
}

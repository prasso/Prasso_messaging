<?php

namespace Prasso\Messaging\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MsgDelivery extends Model
{
    use HasFactory;

    protected $table = 'msg_deliveries';

    protected $fillable = [
        'msg_message_id',
        'recipient_type',
        'recipient_id',
        'channel',
        'status',
        'provider_message_id',
        'error',
        'metadata',
        'sent_at',
        'delivered_at',
        'failed_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function message()
    {
        return $this->belongsTo(MsgMessage::class, 'msg_message_id');
    }
}

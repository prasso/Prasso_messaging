<?php

namespace Prasso\Messaging\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MsgConsentEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'msg_guest_id',
        'action',
        'method',
        'source',
        'ip',
        'user_agent',
        'occurred_at',
        'meta',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'meta' => 'array',
    ];

    public function guest()
    {
        return $this->belongsTo(MsgGuest::class, 'msg_guest_id');
    }
}

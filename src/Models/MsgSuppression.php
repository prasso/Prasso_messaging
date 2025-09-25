<?php

namespace Prasso\Messaging\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MsgSuppression extends Model
{
    use HasFactory;

    protected $table = 'msg_suppressions';

    protected $fillable = [
        'recipient_type',
        'recipient_id',
        'channel',
        'reason',
        'source',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];
}

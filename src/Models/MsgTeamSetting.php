<?php

namespace Prasso\Messaging\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MsgTeamSetting extends Model
{
    use HasFactory;

    protected $table = 'msg_team_settings';

    protected $fillable = [
        'team_id',
        'sms_from',
        'help_business_name',
        'help_purpose',
        'help_contact_phone',
        'help_contact_email',
        'help_contact_website',
        'help_disclaimer',
        'rate_batch_size',
        'rate_batch_interval_seconds',
        'verification_status',
        'verified_at',
        'verification_notes',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'verified_at' => 'datetime',
    ];
}

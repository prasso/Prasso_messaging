<?php

namespace Prasso\Messaging\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MsgTeamSetting extends Model
{
    use HasFactory;

    protected $table = 'msg_team_settings';

    protected $fillable = [
        'team_id',
        'sms_from',
        'whatsapp_enabled',
        'whatsapp_phone_number_id',
        'whatsapp_business_account_id',
        'whatsapp_access_token',
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
        'recipient_sources',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'verified_at' => 'datetime',
        'recipient_sources' => 'array',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Team::class, 'team_id');
    }
}

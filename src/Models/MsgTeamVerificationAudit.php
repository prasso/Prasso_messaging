<?php

namespace Prasso\Messaging\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MsgTeamVerificationAudit extends Model
{
    use HasFactory;

    protected $table = 'msg_team_verification_audits';

    public $timestamps = false; // created_at only

    protected $fillable = [
        'team_id',
        'status',
        'notes',
        'changed_by_user_id',
        'created_at',
    ];
}

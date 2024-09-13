<?php

namespace Prasso\Messaging\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * This model represents the responses to engagements such as polls, contests, or surveys.
 */
class MsgEngagementResponse extends Model
{
    use HasFactory;


    protected $table = 'msg_engagement_responses';

    protected $fillable = ['engagement_id', 'guest_id', 'response'];

    // Relationships

    public function engagement()
    {
        return $this->belongsTo(MsgEngagement::class, 'engagement_id');
    }

    public function guest()
    {
        return $this->belongsTo(MsgGuest::class, 'guest_id');
    }
}

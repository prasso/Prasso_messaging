<?php

namespace Prasso\Messaging\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/*
This model represents the relationship between campaigns and messages.
*/
class MsgCampaignMessage extends Model
{
    use HasFactory;


    protected $table = 'msg_campaign_messages';

    protected $fillable = ['campaign_id', 'message_id'];

    // Relationships

    public function campaign()
    {
        return $this->belongsTo(MsgCampaign::class, 'campaign_id');
    }

    public function message()
    {
        return $this->belongsTo(MsgMessage::class, 'message_id');
    }
}


<?php

namespace Prasso\Messaging\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MsgCampaign extends Model
{
    use HasFactory;
    protected $table = "msg_campaigns";

    protected $fillable = ['name', 'start_date', 'end_date', 'description'];

    public function messages()
    {
        return $this->belongsToMany(MsgMessage::class, 'campaign_messages')->withTimestamps();
    }
}

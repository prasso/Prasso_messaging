<?php

namespace Prasso\Messaging\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MsgMessage extends Model
{
    use HasFactory;
    protected $fillable = ['team_id', 'subject', 'body', 'type'];

    public function guests()
    {
        return $this->belongsToMany(MsgGuest::class, 'msg_guest_messages', 'msg_message_id', 'msg_guest_id')
            ->withTimestamps();
    }

    public function workflows()
    {
        return $this->belongsToMany(MsgWorkflow::class, 'msg_workflow_steps', 'msg_messages_id', 'msg_workflows_id')
            ->withTimestamps();
    }

    public function deliveries()
    {
        return $this->hasMany(MsgDelivery::class, 'msg_message_id');
    }
}

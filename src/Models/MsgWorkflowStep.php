<?php

namespace Prasso\Messaging\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * This model represents a single step in a workflow and connects a workflow to a message.
 */
class MsgWorkflowStep extends Model
{
    use HasFactory;


    protected $table = 'msg_workflow_steps';

    protected $fillable = ['workflow_id', 'message_id', 'delay_in_minutes'];

    // Relationships

    public function workflow()
    {
        return $this->belongsTo(MsgWorkflow::class, 'workflow_id');
    }

    public function message()
    {
        return $this->belongsTo(MsgMessage::class, 'message_id');
    }
}

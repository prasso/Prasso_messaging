<?php

namespace Prasso\Messaging\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MsgWorkflow extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description'];

    public function steps()
    {
        return $this->hasMany(MsgWorkflowStep::class, 'msg_workflows_id');
    }
}

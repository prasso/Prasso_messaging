<?php

namespace Prasso\Messaging\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MsgMessage extends Model
{
    use HasFactory;
    protected $fillable = ['type', 'content'];

    public function guests()
    {
        return $this->belongsToMany(MsgGuest::class, 'guest_messages')->withTimestamps();
    }

    public function workflows()
    {
        return $this->belongsToMany(MsgWorkflow::class, 'workflow_steps')->withTimestamps();
    }
}

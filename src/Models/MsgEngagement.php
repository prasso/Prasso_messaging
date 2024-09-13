<?php

namespace Prasso\Messaging\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MsgEngagement extends Model
{
    use HasFactory;


    protected $fillable = ['type', 'title', 'description'];

    public function responses()
    {
        return $this->hasMany(EngagementResponse::class);
    }
}

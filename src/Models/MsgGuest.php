<?php

namespace Prasso\Messaging\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MsgGuest extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'email', 'phone'];

    public function messages()
    {
        return $this->belongsToMany(MsgMessage::class, 'guest_messages')->withTimestamps();
    }

    public function engagementResponses()
    {
        return $this->hasMany(MsgEngagementResponse::class);
    }
}

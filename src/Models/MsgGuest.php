<?php

namespace Prasso\Messaging\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MsgGuest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'email',
        'phone',
        'is_subscribed',
        'last_message_at',
        'subscription_status_updated_at'
    ];

    protected $casts = [
        'is_subscribed' => 'boolean',
        'last_message_at' => 'datetime',
        'subscription_status_updated_at' => 'datetime'
    ];

    protected $attributes = [
        'is_subscribed' => true // Default to opted-in
    ];

    public function messages()
    {
        return $this->belongsToMany(MsgMessage::class, 'msg_guest_messages', 'msg_guest_id', 'msg_message_id')
            ->withTimestamps();
    }

    public function engagementResponses()
    {
        return $this->hasMany(MsgEngagementResponse::class);
    }
}

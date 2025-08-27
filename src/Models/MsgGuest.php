<?php

namespace Prasso\Messaging\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MsgGuest extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'user_id',
        'name',
        'email',
        'phone',
        'email_hash',
        'phone_hash',
        'is_subscribed',
        'last_message_at',
        'subscription_status_updated_at'
    ];

    protected $casts = [
        'email' => 'encrypted',
        'phone' => 'encrypted',
        'is_subscribed' => 'boolean',
        'last_message_at' => 'datetime',
        'subscription_status_updated_at' => 'datetime'
    ];

    protected $attributes = [
        'is_subscribed' => false // Default to pending (unsubscribed) until confirmed
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

    /**
     * Mutator: ensure email_hash stays in sync using SHA-256 over lowercase email.
     */
    public function setEmailAttribute($value): void
    {
        $this->attributes['email'] = $value;
        if (!empty($value)) {
            $this->attributes['email_hash'] = hash('sha256', strtolower(trim((string) $value)));
        }
    }

    /**
     * Mutator: ensure phone_hash stays in sync using SHA-256 over normalized phone.
     */
    public function setPhoneAttribute($value): void
    {
        $this->attributes['phone'] = $value;
        if (!empty($value)) {
            $normalized = preg_replace('/[^0-9]/', '', (string) $value);
            if (strlen($normalized) === 11 && str_starts_with($normalized, '1')) {
                $normalized = substr($normalized, 1);
            }
            $this->attributes['phone_hash'] = hash('sha256', $normalized);
        }
    }
}

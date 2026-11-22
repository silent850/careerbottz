<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutboundMessage extends Model
{
    protected $fillable = [
        'inbound_message_id',
        'user_id',
        'provider',
        'idempotency_key',
        'recipient_phone_e164',
        'content',
        'status',
        'provider_message_id',
        'attempt_count',
        'last_error',
        'sent_at',
        'unknown_at',
    ];

    protected function casts(): array
    {
        return [
            'attempt_count' => 'integer',
            'sent_at' => 'datetime',
            'unknown_at' => 'datetime',
        ];
    }

    public function inboundMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'inbound_message_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

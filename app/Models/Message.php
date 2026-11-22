<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Message extends Model
{
    protected $fillable = [
        'user_id',
        'conversation_id',
        'provider',
        'provider_message_id',
        'direction',
        'content',
        'status',
        'received_at',
        'processed_at',
        'attachment_path',
        'attachment_mime_type',
        'attachment_original_name',
        'attachment_size',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function outboundMessage(): HasOne
    {
        return $this->hasOne(OutboundMessage::class, 'inbound_message_id');
    }
}

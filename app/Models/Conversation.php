<?php

namespace App\Models;

use App\Services\Conversation\ConversationState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $fillable = [
        'user_id',
        'state',
        'previous_state',
        'status',
        'active_cv_id',
    ];

    protected function casts(): array
    {
        return [
            'state' => ConversationState::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Get the CV currently active in this conversation.
     */
    public function activeCv(): BelongsTo
    {
        return $this->belongsTo(Cv::class, 'active_cv_id');
    }
}
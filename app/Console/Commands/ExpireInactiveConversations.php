<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireInactiveConversations extends Command
{
    protected $signature = 'conversations:expire-inactive';

    protected $description = 'Expire conversations after 60 minutes of inactivity';

    private const INACTIVITY_MINUTES = 60;

    public function handle(): int
    {
        $cutoff = now()->subMinutes(self::INACTIVITY_MINUTES);

        $candidateIds = Conversation::query()
            ->where('status', 'active')
            ->where('updated_at', '<=', $cutoff)
            ->orderBy('id')
            ->pluck('id');

        $expired = 0;

        foreach ($candidateIds as $id) {
            $changed = DB::transaction(function () use ($id, $cutoff): bool {
                $conversation = Conversation::query()
                    ->lockForUpdate()
                    ->find($id);

                if (
                    $conversation === null
                    || $conversation->status !== 'active'
                    || $conversation->updated_at === null
                    || $conversation->updated_at->gt($cutoff)
                ) {
                    return false;
                }

                $hasProcessingInbound = Message::query()
                    ->where('user_id', $conversation->user_id)
                    ->where('direction', 'inbound')
                    ->where('status', 'processing')
                    ->whereNull('processed_at')
                    ->exists();

                if ($hasProcessingInbound) {
                    return false;
                }

                $conversation->status = 'expired';
                $conversation->save();

                return true;
            });

            if ($changed) {
                $expired++;
            }
        }

        $this->info(
            "Expiration complete. Expired {$expired} inactive conversation(s)."
        );

        return self::SUCCESS;
    }
}

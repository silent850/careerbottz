<?php

namespace App\Console\Commands;

use App\Models\OutboundMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecoverStaleOutboundMessages extends Command
{
    protected $signature = 'outbound:recover-stale
                            {--minutes=5 : Minimum age in minutes before a sending record is considered stale}';

    protected $description = 'Mark stale outbound sending attempts as unknown without resending them';

    public function handle(): int
    {
        $minutes = filter_var(
            $this->option('minutes'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]],
        );

        if ($minutes === false) {
            $this->error('The --minutes option must be a positive integer.');

            return self::FAILURE;
        }

        $cutoff = now()->subMinutes($minutes);

        $candidateIds = OutboundMessage::query()
            ->where('status', 'sending')
            ->where('updated_at', '<=', $cutoff)
            ->orderBy('id')
            ->pluck('id');

        $recovered = 0;

        foreach ($candidateIds as $id) {
            $changed = DB::transaction(function () use ($id, $cutoff): bool {
                $outbound = OutboundMessage::query()
                    ->lockForUpdate()
                    ->find($id);

                if (
                    $outbound === null
                    || $outbound->status !== 'sending'
                    || $outbound->updated_at === null
                    || $outbound->updated_at->gt($cutoff)
                ) {
                    return false;
                }

                $outbound->status = 'unknown';
                $outbound->unknown_at = now();
                $outbound->last_error =
                    'Delivery outcome unknown: outbound attempt remained in sending '
                    . 'beyond the configured recovery threshold. Reconcile delivery '
                    . 'before considering any retry.';
                $outbound->save();

                return true;
            });

            if ($changed) {
                $recovered++;
            }
        }

        $this->info(
            "Recovery complete. Marked {$recovered} stale outbound message(s) as unknown."
        );

        return self::SUCCESS;
    }
}

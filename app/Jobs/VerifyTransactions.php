<?php

namespace App\Jobs;

use App\Models\Transaction;
use App\Models\Wallet;
use App\Models\DeadLetterQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class VerifyTransactions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;
    public $timeout = 300;


    /**
     * Create a new job instance.
     */

    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // check unknown transactions older than 5 mins
        $txns = Transaction::where('status', 'unknown')
            ->where('updated_at', '<', now()->subMinutes(5))
            ->limit(50)
            ->get();

        foreach ($txns as $txn) {
            try {
                $status = $this->checkStatus($txn);

                if ($status === 'success') {
                    $txn->update(['status' => 'success']);
                    if ($txn->type === 'withdrawal') {
                        Wallet::find($txn->sender_wallet_id)?->decrement('locked_balance', $txn->amount);
                    }
                } elseif ($status === 'failed') {
                    $txn->update(['status' => 'failed']);
                    if (in_array($txn->type, ['withdrawal', 'bill_payment'])) {
                        $w = Wallet::find($txn->sender_wallet_id);
                        $w?->increment('balance', $txn->amount);
                        if ($txn->type === 'withdrawal') {
                            $w?->decrement('locked_balance', $txn->amount);
                        }
                    }
                }
            } catch (\Exception $e) {
                logger()->warning("verify failed: {$txn->id}", ['err' => $e->getMessage()]);
            }
        }

        // move stuck ones to dlq
        $stuck = Transaction::where('status', 'unknown')
            ->where('updated_at', '<', now()->subHour())
            ->limit(20)
            ->get();

        foreach ($stuck as $txn) {
            $txn->update(['status' => 'requires_review']);
            DeadLetterQueue::create([
                'type' => $txn->type,
                'payload' => ['transaction_id' => $txn->id],
                'error' => 'Stuck in unknown status',
                'attempts' => 0,
            ]);
        }
    }

    protected function checkStatus(Transaction $txn): string
    {
        // call provider api here
        sleep(1);
        
        $rand = rand(1, 100);
        if ($rand <= 70) return 'success';
        if ($rand <= 85) return 'failed';
        return 'pending';
    }
}

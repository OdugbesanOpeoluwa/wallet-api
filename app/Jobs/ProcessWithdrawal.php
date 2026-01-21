<?php

namespace App\Jobs;

use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\CircuitBreaker;

class ProcessWithdrawal implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [30, 60, 120];
    public $uniqueFor = 300;
    public $timeout = 30;


    /**
     * Create a new job instance.
     */
    public function __construct(public string $transactionId)
    {
        //
        $this->onConnection('rabbitmq');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        //

        $txn = Transaction::find($this->transactionId);
        if (!$txn || $txn->status !== 'pending') {
            return;
        }
        $circuit = new CircuitBreaker('bank_provider');
        try {
            $success = $circuit->call(fn() => $this->processWithdrawal($txn));
            if ($success) {
                $txn->update(['status' => 'success']);
                $wallet = Wallet::find($txn->sender_wallet_id);
                $wallet->decrement('locked_balance', $txn->amount);
            } else {
                $this->handleFailure($txn);
            }
        } catch (\Exception $e) {
            if ($this->attempts() >= $this->tries) {
                $this->handleFailure($txn);
            }
            throw $e;
        }
    }

    protected function processWithdrawal(Transaction $txn): bool
    {
        // mock bank transfer
        sleep(2);
        return rand(1, 10) <= 9;
    }

    protected function handleFailure(Transaction $txn): void
    {
        $txn->update(['status' => 'failed']);
        // refund: move locked back to available
        $wallet = Wallet::find($txn->sender_wallet_id);
        $wallet->increment('balance', $txn->amount);
        $wallet->decrement('locked_balance', $txn->amount);
    }

    public function uniqueId(): string
    {
        return $this->transactionId;
    }
}

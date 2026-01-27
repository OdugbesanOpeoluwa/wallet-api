<?php

namespace App\Jobs;

use App\Models\Transaction;
use App\Models\Wallet;
use App\Models\DeadLetterQueue;
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

    public $tries = 5;
    public $backoff = [30, 60, 120, 300, 600];
    public $uniqueFor = 600;
    public $timeout = 60;


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
        
        if (!$txn || !in_array($txn->status, ['pending', 'unknown'])) {
            return;
        }

        $circuit = new CircuitBreaker('bank_provider');

        try {
            $result = $circuit->call(fn() => $this->process($txn));
            
            if ($result['success']) {
                $txn->update([
                    'status' => 'success',
                    'metadata' => array_merge($txn->metadata ?? [], [
                        'provider_ref' => $result['reference'] ?? null,
                    ]),
                ]);
                $wallet = Wallet::find($txn->sender_wallet_id);
                $wallet->decrement('locked_balance', $txn->amount);
            } else {
                // so this is a logic where the provider said it failed so we refund the user
                $this->refund($txn, $result['message'] ?? 'Declined');
            }
        } catch (\Exception $e) {
            logger()->error("withdrawal failed: {$txn->id}", ['err' => $e->getMessage()]);
            
            // mark as unknown - don't refund yet
            $txn->update([
                'status' => 'unknown',
                'metadata' => array_merge($txn->metadata ?? [], [
                    'last_error' => $e->getMessage(),
                    'attempts' => $this->attempts(),
                ]),
            ]);

            if ($this->attempts() >= $this->tries) {
                $this->toDLQ($txn, $e->getMessage());
                return;
            }

            throw $e;
        }
    }

    protected function process(Transaction $txn): array
    {
        // call bank api here
        sleep(2);
        
        $rand = rand(1, 100);
        if ($rand <= 85) {
            return ['success' => true, 'reference' => 'PRV_' . uniqid()];
        } elseif ($rand <= 95) {
            return ['success' => false, 'message' => 'Invalid account'];
        }
        
        throw new \Exception('Bank timeout');
    }

    protected function refund(Transaction $txn, string $reason): void
    {
        $txn->update([
            'status' => 'failed',
            'metadata' => array_merge($txn->metadata ?? [], ['failure_reason' => $reason]),
        ]);

        $wallet = Wallet::find($txn->sender_wallet_id);
        $wallet->increment('balance', $txn->amount);
        $wallet->decrement('locked_balance', $txn->amount);
    }

    protected function toDLQ(Transaction $txn, string $error): void
    {
        $txn->update(['status' => 'requires_review']);

        DeadLetterQueue::create([
            'type' => 'withdrawal',
            'payload' => ['transaction_id' => $txn->id, 'amount' => $txn->amount],
            'error' => $error,
            'attempts' => $this->attempts(),
        ]);
    }

    public function uniqueId(): string
    {
        return $this->transactionId;
    }
}

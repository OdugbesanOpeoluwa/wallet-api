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

class ProcessBillPayment implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    
    /**
     * Create a new job instance.
     */

    public $tries = 5;
    public $backoff = [30, 60, 120, 300, 600];
    public $uniqueFor = 600;
    public $timeout = 60;

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

        $circuit = new CircuitBreaker('bill_provider');

        try {
            $result = $circuit->call(fn() => $this->process($txn));
            
            if ($result['success']) {
                $txn->update([
                    'status' => 'success',
                    'metadata' => array_merge($txn->metadata ?? [], [
                        'provider_ref' => $result['reference'] ?? null,
                        'token' => $result['token'] ?? null,
                    ]),
                ]);
            } else {
                $this->refund($txn, $result['message'] ?? 'Declined');
            }
        } catch (\Exception $e) {
            logger()->error("bill payment failed: {$txn->id}", ['err' => $e->getMessage()]);
            
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
        //mock for high success rate of about 90%
        sleep(2);
        
        $rand = rand(1, 100);
        if ($rand <= 90) {
            return [
                'success' => true,
                'reference' => 'BILL_' . uniqid(),
                'token' => 'TKN_' . strtoupper(uniqid()),
            ];
        } elseif ($rand <= 98) {
            return ['success' => false, 'message' => 'Invalid meter'];
        }
        
        throw new \Exception('Provider timeout');
    }

    protected function refund(Transaction $txn, string $reason): void
    {
        $txn->update([
            'status' => 'failed',
            'metadata' => array_merge($txn->metadata ?? [], ['failure_reason' => $reason]),
        ]);

        $wallet = Wallet::find($txn->sender_wallet_id);
        $wallet->increment('balance', $txn->amount);
    }

    protected function toDLQ(Transaction $txn, string $error): void
    {
        $txn->update(['status' => 'requires_review']);

        DeadLetterQueue::create([
            'type' => 'bill_payment',
            'payload' => ['transaction_id' => $txn->id],
            'error' => $error,
            'attempts' => $this->attempts(),
        ]);
    }

    public function uniqueId(): string
    {
        return $this->transactionId;
    }
}

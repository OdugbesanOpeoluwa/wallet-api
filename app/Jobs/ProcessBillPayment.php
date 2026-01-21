<?php

namespace App\Jobs;

use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\CircuitBreaker;

class ProcessBillPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [30, 60, 120];
    public $uniqueFor = 300;
    
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
        $circuit = new CircuitBreaker('bill_provider');
        try {
            $success = $circuit->call(fn() => $this->processBill($txn));
            $txn->update([
                'status' => $success ? 'success' : 'failed',
            ]);
        } catch (\Exception $e) {
            if ($this->attempts() >= $this->tries) {
                $txn->update(['status' => 'failed']);
            }
            throw $e;
        }
    }


    protected function processBill(Transaction $txn): bool
    {
        //mock high success rate of about 90%
        sleep(2);
        return rand(1, 10) <= 9;
    }

    public function uniqueId(): string
    {
        return $this->transactionId;
    }
}

<?php

namespace App\Jobs;

use App\Models\WebhookLog;
use App\Models\Wallet;
use App\Models\Transaction;
use App\Models\LedgerEntry;
use App\Models\DeadLetterQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProcessWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;
    public $backoff = [30, 60, 120, 300];
    public $timeout = 60;


    /**
     * Create a new job instance.
     */

    public function __construct(public string $logId)
    {
        $this->onQueue('webhooks');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $log = WebhookLog::find($this->logId);
        
        if (!$log || $log->processed) return;

        try {
            match ($log->provider) {
                'crypto' => $this->handleCrypto($log),
                'bank' => $this->handleBank($log),
                default => null,
            };

            $log->update(['processed' => true, 'processed_at' => now()]);

        } catch (\Exception $e) {
            logger()->error("webhook failed: {$log->id}", ['err' => $e->getMessage()]);

            if ($this->attempts() >= $this->tries) {
                DeadLetterQueue::create([
                    'type' => 'webhook',
                    'payload' => ['log_id' => $log->id],
                    'error' => $e->getMessage(),
                    'attempts' => $this->attempts(),
                ]);
                return;
            }

            throw $e;
        }
    }

    protected function handleCrypto(WebhookLog $log): void
    {
        $payload = $log->payload;
        
        if (($payload['status'] ?? '') !== 'confirmed') return;

        DB::transaction(function () use ($payload, $log) {
            $wallet = Wallet::where('id', $payload['wallet_id'])->lockForUpdate()->first();
            if (!$wallet) throw new \Exception('Wallet not found');

            $txn = Transaction::create([
                'id' => Str::uuid(),
                'reference' => 'space_trade_' . Str::random(12),
                'type' => 'deposit',
                'status' => 'success',
                'amount' => $payload['amount'],
                'currency' => $payload['currency'],
                'recipient_wallet_id' => $wallet->id,
                'metadata' => ['txn_hash' => $payload['txn_hash'] ?? null, 'webhook_id' => $log->id],
            ]);

            LedgerEntry::create([
                'wallet_id' => null,
                'account_type' => 'SYSTEM_FLOAT',
                'debit' => $payload['amount'],
                'credit' => 0,
                'transaction_id' => $txn->id,
            ]);

            LedgerEntry::create([
                'wallet_id' => $wallet->id,
                'account_type' => 'USER_WALLET',
                'debit' => 0,
                'credit' => $payload['amount'],
                'transaction_id' => $txn->id,
            ]);

            $wallet->increment('balance', $payload['amount']);
        });
    }

    protected function handleBank(WebhookLog $log): void
    {
        $payload = $log->payload;
        $ref = $payload['reference'] ?? null;
        
        if (!$ref) return;

        $txn = Transaction::where('reference', $ref)->first();
        if (!$txn) return;

        $status = $payload['status'] ?? '';

        if ($status === 'success') {
            $txn->update(['status' => 'success']);
            if ($txn->type === 'withdrawal') {
                Wallet::find($txn->sender_wallet_id)?->decrement('locked_balance', $txn->amount);
            }
        } elseif ($status === 'failed') {
            $txn->update(['status' => 'failed']);
            if ($txn->type === 'withdrawal') {
                $w = Wallet::find($txn->sender_wallet_id);
                $w?->increment('balance', $txn->amount);
                $w?->decrement('locked_balance', $txn->amount);
            }
        }
    }
}

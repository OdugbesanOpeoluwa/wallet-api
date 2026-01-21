<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class TransferService
{
    protected LedgerService $ledger;

    public function __construct(LedgerService $ledger)
    {
        $this->ledger = $ledger;
    }

    public function transfer(Wallet $sender, Wallet $recipient, float $amount, ?string $idempotencyKey = null): Transaction
    { 
        // distributed lock - so this is to prevent parallel transfers from same wallet
        $lockKey = "transfer_lock:{$sender->id}";
        $lock = Cache::lock($lockKey, 10);

        if (!$lock->get()) {
            throw new \Exception('Transfer in progress, try again');
        }

        try {
            return DB::transaction(function () use ($sender, $recipient, $amount, $idempotencyKey) {
                
                if ($idempotencyKey) {
                    $existing = Transaction::where('idempotency_key', $idempotencyKey)->first();
                    if ($existing) {
                        return $existing;
                    }
                }

                $ids = collect([$sender->id, $recipient->id])->sort()->values();
                
                $first = Wallet::where('id', $ids[0])->lockForUpdate()->first();
                $second = Wallet::where('id', $ids[1])->lockForUpdate()->first();

                $sender = $first->id === $sender->id ? $first : $second;
                $recipient = $first->id === $recipient->id ? $first : $second;

                // revalidate balance just before commit
                if ($sender->balance < $amount) {
                    throw new \Exception('Insufficient balance');
                }

                $txn = $this->ledger->createTransaction([
                    'type' => 'transfer',
                    'status' => 'success',
                    'amount' => $amount,
                    'currency' => $sender->currency,
                    'sender_wallet_id' => $sender->id,
                    'recipient_wallet_id' => $recipient->id,
                    'idempotency_key' => $idempotencyKey,
                ]);

                $this->ledger->recordDebit($sender, $txn, $amount);
                $this->ledger->recordCredit($recipient, $txn, $amount);

                $sender->decrement('balance', $amount);
                $recipient->increment('balance', $amount);

                return $txn;
            });
        } finally {
            $lock->release();
        }
    }
}
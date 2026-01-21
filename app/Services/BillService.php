<?php

namespace App\Services;


use App\Models\Transaction;
use App\Models\Wallet;
use App\Jobs\ProcessBillPayment;
use Illuminate\Support\Facades\DB;

class BillService
{
    protected LedgerService $ledger;
    public function __construct(LedgerService $ledger)
    {
        $this->ledger = $ledger;
    }

    public function initiateBillPayment(Wallet $wallet, float $amount, array $billData, ?string $idempotencyKey = null): Transaction
    {
        return DB::transaction(function () use ($wallet, $amount, $billData, $idempotencyKey) {
            
            if ($idempotencyKey) {
                $existing = Transaction::where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    return $existing;
                }
            }

            $wallet = Wallet::where('id', $wallet->id)->lockForUpdate()->first();

            if ($wallet->balance < $amount) {
                throw new \Exception('Insufficient balance');
            }

            $txn = $this->ledger->createTransaction([
                'type' => 'bill_payment',
                'status' => 'pending',
                'amount' => $amount,
                'currency' => $wallet->currency,
                'sender_wallet_id' => $wallet->id,
                'metadata' => $billData,
                'idempotency_key' => $idempotencyKey,
            ]);
            
            $this->ledger->recordDebit($wallet, $txn, $amount);            
            $wallet->decrement('balance', $amount);

            ProcessBillPayment::dispatch($txn->id);

            return $txn;
        });
    }
}

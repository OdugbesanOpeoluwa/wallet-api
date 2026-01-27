<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Entry;
use App\Models\Journal;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\LedgerEntry;

class LedgerService
{
    public function createTransaction(array $data): Transaction
    {
        return Transaction::create([
            'id' => Str::uuid(),
            'reference' => 'space_trade_' . Str::random(12),
            'type' => $data['type'],
            'status' => $data['status'] ?? 'pending',
            'amount' => $data['amount'],
            'currency' => $data['currency'],
            'sender_wallet_id' => $data['sender_wallet_id'] ?? null,
            'recipient_wallet_id' => $data['recipient_wallet_id'] ?? null,
            'metadata' => $data['metadata'] ?? null,
            'idempotency_key' => $data['idempotency_key'] ?? null,
        ]);
    }

    public function createJournal(array $data): Journal
    {
        return Journal::create([
            'reference' => $data['reference'] ?? 'space_trade_' . Str::random(12),
            'type' => $data['type'],
            'amount' => $data['amount'],
            'currency' => $data['currency'],
            'status' => $data['status'] ?? 'pending',
            'metadata' => $data['metadata'] ?? null,
            'idempotency_key' => $data['idempotency_key'] ?? null,
        ]);
    }

    public function recordEntries(Journal $journal, array $legs): void
    {
        $total = 0;

        foreach ($legs as $leg) {
            $amount = $leg['type'] === 'credit' ? abs($leg['amount']) : -abs($leg['amount']);

            Entry::create([
                'journal_id' => $journal->id,
                'account_id' => $leg['account_id'],
                'amount' => $amount,
                'type' => strtoupper($leg['type']),
            ]);

            $total += $amount;
        }

        if (abs($total) > 0.0001) {
            throw new \Exception("entries don't balance: {$total}");
        }
    }

    // legacy methods for compatibility
    public function recordDebit(Wallet $wallet, Transaction $txn, float $amount): void
    {
        LedgerEntry::create([
            'wallet_id' => $wallet->id,
            'account_type' => 'USER_WALLET',
            'debit' => $amount,
            'credit' => 0,
            'transaction_id' => $txn->id,
        ]);
    }

    public function recordCredit(Wallet $wallet, Transaction $txn, float $amount): void
    {
        LedgerEntry::create([
            'wallet_id' => $wallet->id,
            'account_type' => 'USER_WALLET',
            'debit' => 0,
            'credit' => $amount,
            'transaction_id' => $txn->id,
        ]);
    }

    public function recordSystemEntry(string $accountType, Transaction $txn, float $debit, float $credit): void
    {
        LedgerEntry::create([
            'wallet_id' => null,
            'account_type' => $accountType,
            'debit' => $debit,
            'credit' => $credit,
            'transaction_id' => $txn->id,
        ]);
    }
}
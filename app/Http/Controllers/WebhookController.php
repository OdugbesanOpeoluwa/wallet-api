<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Wallet;
use App\Models\Transaction;
use App\Models\LedgerEntry;
use App\Models\WebhookLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WebhookController extends Controller
{
    //
    public function crypto(Request $request)
    {
        $payload = $request->all();
        $signature = $request->header('X-Signature');
        $eventId = $payload['event_id'] ?? null;
        // verify signature (mock)
        if (!$this->verifySignature($payload, $signature)) {
            return $this->error('Invalid signature', 401);
        }
        // check idempotency
        if ($eventId) {
            $existing = WebhookLog::where('event_id', $eventId)->first();
            if ($existing && $existing->processed) {
                return $this->success(['message' => 'Already processed']);
            }
        }
        // store webhook
        $log = WebhookLog::create([
            'provider' => 'crypto',
            'payload' => $payload,
            'signature' => $signature,
            'event_id' => $eventId,
            'processed' => false,
        ]);
        // process if confirmed
        if (($payload['status'] ?? '') === 'confirmed') {
            try {
                $this->creditWallet($payload, $log);
            } catch (\Exception $e) {
                return $this->error($e->getMessage(), 400);
            }
        }
        return $this->success(['message' => 'Received']);
    }

    protected function verifySignature(array $payload, ?string $signature): bool
    {
        if (!$signature) {
            return false;
        }
        $secret = config('services.crypto.webhook_secret');
        
        if (!$secret) {
            return false;
        }
        $computed = hash_hmac('sha256', json_encode($payload), $secret);
        
        return hash_equals($computed, $signature);
    }

    protected function creditWallet(array $payload, WebhookLog $log): void
    {

        DB::transaction(function () use ($payload, $log) {
            $wallet = Wallet::where('id', $payload['wallet_id'])->lockForUpdate()->first();
            if (!$wallet) {
                throw new \Exception('Wallet not found');
            }
            $txn = Transaction::create([
                'id' => Str::uuid(),
                'reference' => 'space_trade_' . Str::random(12),
                'type' => 'deposit',
                'status' => 'success',
                'amount' => $payload['amount'],
                'currency' => $payload['currency'],
                'recipient_wallet_id' => $wallet->id,
                'metadata' => [
                    'crypto_txn_hash' => $payload['txn_hash'] ?? null,
                    'webhook_log_id' => $log->id,
                ],
            ]);
            // double-entry: system float debit, user credit
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
            $log->update(['processed' => true]);
        });
    }  
}

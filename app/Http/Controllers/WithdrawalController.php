<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Wallet;
use App\Models\Transaction;
use App\Models\LedgerEntry;
use App\Http\Requests\WithdrawalRequest;
use App\Jobs\ProcessWithdrawal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\RiskService;
use App\Services\AuditService;

class WithdrawalController extends Controller
{
    //
    public function store(WithdrawalRequest $request)
    {
        $wallet = Wallet::where('id', $request->wallet_id)
            ->where('user_id', $request->user()->id)
            ->first();
        if (!$wallet) {
            return $this->error('Wallet not found', 404);
        }
        if ($wallet->currency !== 'NGN') {
            return $this->error('Only NGN withdrawals supported', 400);
        }
        $risk = app(RiskService::class)->checkWithdrawal(
            $request->user(),
            $request->amount,
            $wallet->id,
            ['account_number' => $request->account_number]
        );
        if ($risk['blocked']) {
            return $this->error($risk['reason'], 403);
        }
        $idempotencyKey = $request->header('X-Idempotency-Key');
        try {
            $txn = DB::transaction(function () use ($wallet, $request, $idempotencyKey) {
                
                if ($idempotencyKey) {
                    $existing = Transaction::where('idempotency_key', $idempotencyKey)->first();
                    if ($existing) {
                        return $existing;
                    }
                }
                $wallet = Wallet::where('id', $wallet->id)->lockForUpdate()->first();
                if ($wallet->balance < $request->amount) {
                    throw new \Exception('Insufficient balance');
                }
                $txn = Transaction::create([
                    'id' => Str::uuid(),
                    'reference' => 'space_trade_' . Str::random(12),
                    'type' => 'withdrawal',
                    'status' => 'pending',
                    'amount' => $request->amount,
                    'currency' => $wallet->currency,
                    'sender_wallet_id' => $wallet->id,
                    'metadata' => [
                        'bank_code' => $request->bank_code,
                        'account_number' => $request->account_number,
                        'risk' => $risk['risk'] ?? 'low',
                        'ip' => $request->ip(),
                    ],
                    'idempotency_key' => $idempotencyKey,
                ]);
                LedgerEntry::create([
                    'wallet_id' => $wallet->id,
                    'account_type' => 'USER_WALLET',
                    'debit' => $request->amount,
                    'credit' => 0,
                    'transaction_id' => $txn->id,
                ]);
                $wallet->decrement('balance', $request->amount);
                $wallet->increment('locked_balance', $request->amount);
                ProcessWithdrawal::dispatch($txn->id);
                return $txn;
            });
            AuditService::log('withdrawal', 'transaction', $txn->id, [
                'amount' => $request->amount,
                'wallet_id' => $wallet->id,
                'bank_code' => $request->bank_code,
                'account_number' => $request->account_number,
                'risk' => $risk['risk'] ?? 'low',
                'ip' => $request->ip(),
            ]);
            return $this->success($txn, 'Withdrawal successful', 201);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }
}

<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class RiskService
{
    public function checkTransfer(User $user, float $amount, string $walletId): array
    {
        $cfg = config('risk.transfer');

        if ($amount > $cfg['per_transaction_limit']) {
            return ['blocked' => true, 'reason' => 'Amount exceeds limit'];
        }

        $daily = $this->dailyVolume($walletId, 'transfer');
        if (($daily + $amount) > $cfg['daily_limit']) {
            return ['blocked' => true, 'reason' => 'Daily limit exceeded'];
        }

        $count = $this->recentCount($walletId, 'transfer', $cfg['velocity']['window_minutes']);
        if ($count >= $cfg['velocity']['max_count']) {
            return ['blocked' => true, 'reason' => 'Too many transfers'];
        }

        return ['blocked' => false, 'risk' => 'low'];
    }

    public function checkWithdrawal(User $user, float $amount, string $walletId, array $meta): array
    {
        $cfg = config('risk.withdrawal');

        if ($amount > $cfg['per_transaction_limit']) {
            return ['blocked' => true, 'reason' => 'Amount exceeds limit'];
        }

        $daily = $this->dailyVolume($walletId, 'withdrawal');
        if (($daily + $amount) > $cfg['daily_limit']) {
            return ['blocked' => true, 'reason' => 'Daily limit exceeded'];
        }

        $count = $this->recentCount($walletId, 'withdrawal', $cfg['velocity']['window_minutes']);
        if ($count >= $cfg['velocity']['max_count']) {
            return ['blocked' => true, 'reason' => 'Too many withdrawals'];
        }

        $flags = [];
        
        if ($this->isNewBeneficiary($user->id, $meta['account_number'] ?? '')) {
            if ($amount > 50000) {
                $flags[] = 'new_beneficiary';
            }
        }

        $risk = count($flags) > 0 ? 'medium' : 'low';

        return ['blocked' => false, 'risk' => $risk, 'flags' => $flags];
    }

    private function dailyVolume(string $walletId, string $type): float
    {
        $key = "vol:{$walletId}:{$type}:" . date('Ymd');
        
        return Cache::remember($key, 300, function () use ($walletId, $type) {
            return (float) Transaction::where('sender_wallet_id', $walletId)
                ->where('type', $type)
                ->where('status', '!=', 'failed')
                ->whereDate('created_at', today())
                ->sum('amount');
        });
    }

    private function recentCount(string $walletId, string $type, int $mins): int
    {
        return Transaction::where('sender_wallet_id', $walletId)
            ->where('type', $type)
            ->where('created_at', '>=', now()->subMinutes($mins))
            ->count();
    }

    private function isNewBeneficiary(string $userId, string $account): bool
    {
        if (!$account) return true;

        return !Transaction::whereHas('senderWallet', fn($q) => $q->where('user_id', $userId))
            ->where('type', 'withdrawal')
            ->where('status', 'success')
            ->whereJsonContains('metadata->account_number', $account)
            ->exists();
    }
}
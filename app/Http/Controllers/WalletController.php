<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Wallet;
use Illuminate\Support\Str;

class WalletController extends Controller
{
    //
    public function store(Request $request)
    {
        $request->validate([
            'currency' => 'required|string|in:NGN,USDT,BTC,ETH',
        ]);

        $existing = Wallet::where('user_id', $request->user()->id)
            ->where('currency', $request->currency)
            ->first();

        if ($existing) {
            return $this->error('Wallet already exists', 400);
        }

        $wallet = Wallet::create([
            'user_id' => $request->user()->id,
            'currency' => $request->currency,
            'balance' => 0,
            'locked_balance' => 0,
        ]);

        $wallet = Wallet::create([
            'user_id' => $request->user()->id,
            'currency' => $request->currency,
            'balance' => 0,
            'locked_balance' => 0,
        ]);
        
        return $this->success($wallet, 'Wallet created', 201);
    }

    public function index(Request $request)
    {

        $wallets = Wallet::where('user_id', $request->user()->id)->get();
        return $this->success($wallets, 'Wallets fetched');
    }

    public function transactions(Request $request, string $id)
    {

        $wallet = Wallet::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$wallet) {
            return $this->error('Wallet not found', 404);
        }

        $transactions = $wallet->ledgerEntries()
            ->with('transaction')
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        return $this->paginated($transactions, 'Transactions fetched');

    }
}
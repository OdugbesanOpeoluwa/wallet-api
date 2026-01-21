<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Wallet;

class CheckWalletOwnership
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        $walletId = $request->route('id') ?? $request->wallet_id ?? $request->sender_wallet_id;
        
        if ($walletId) {
            $wallet = Wallet::find($walletId);
            
            if (!$wallet || $wallet->user_id !== $request->user()->id) {
                return response()->json(['error' => 'Forbidden'], 403);
            }
        }
        return $next($request);
    }
}

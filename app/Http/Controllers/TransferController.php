<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Http\Requests\TransferRequest;
use App\Services\TransferService;
use App\Services\RiskService;
use App\Services\AuditService;  

class TransferController extends Controller
{
    //
    public function __construct(protected TransferService $transferService)
    {
        //
    }
    public function store(TransferRequest $request)
    {
        $sender = Wallet::where('id', $request->sender_wallet_id)
            ->where('user_id', $request->user()->id)
            ->first();
        if (!$sender) {
            return $this->error('Wallet not found', 404);
        }
        $recipient = Wallet::find($request->recipient_wallet_id);
        if ($sender->currency !== $recipient->currency) {
            return $this->error('Currency mismatch', 400);
        }
        $risk = app(RiskService::class)->checkTransfer(
            $request->user(),
            $request->amount,
            $sender->id
        );

        if ($risk['blocked']) {
            return $this->error($risk['reason'], 403);
        }

        try {
            $txn = $this->transferService->transfer(
                $sender,
                $recipient,
                $request->amount,
                $request->header('X-Idempotency-Key')
            );
            AuditService::log('transfer', 'transaction', $txn->id, [
                'amount' => $request->amount,
                'recipient' => $request->recipient_wallet_id,
            ]);

            return $this->success($txn, 'Transfer successful', 201);

        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }
}

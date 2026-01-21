<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Wallet;
use App\Http\Requests\BillPaymentRequest;
use App\Services\BillService;

class BillController extends Controller
{
    //
    public function __construct(protected BillService $billService)
    {
        //
    }
    public function store(BillPaymentRequest $request)
    {
        $wallet = Wallet::where('id', $request->wallet_id)
            ->where('user_id', $request->user()->id)
            ->first();
        if (!$wallet) {
            return $this->error('Wallet not found', 404);
        }
        if ($wallet->currency !== 'NGN') {
            return $this->error('Bills must be paid in NGN', 400);
        }
        try {
            $txn = $this->billService->initiateBillPayment(
                $wallet,
                $request->amount,
                [
                    'bill_type' => $request->bill_type,
                    'bill_account' => $request->bill_account,
                    'provider' => $request->provider,
                ],
                $request->header('X-Idempotency-Key')
            );

            AuditService::log('bill_payment', 'bill', $txn->id, [
                'wallet_id' => $wallet->id,
                'amount' => $request->amount,
                'bill_type' => $request->bill_type,
                'bill_account' => $request->bill_account,
                'provider' => $request->provider,
            ]);
            
            return $this->success($txn, 'Bill payment successful', 201);    

        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }
}

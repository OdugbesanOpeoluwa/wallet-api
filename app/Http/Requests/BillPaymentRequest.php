<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BillPaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        //
        return [
            'wallet_id' => 'required|uuid|exists:wallets,id',
            'amount' => 'required|numeric|min:1',
            'bill_type' => 'required|string|in:electricity,airtime,data,cable',
            'bill_account' => 'required|string|max:50',
            'provider' => 'required|string|max:50',
        ];
    }
}

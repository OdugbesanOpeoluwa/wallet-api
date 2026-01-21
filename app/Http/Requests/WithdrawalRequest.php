<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WithdrawalRequest extends FormRequest
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
            'bank_code' => 'required|string|size:3',
            'account_number' => 'required|string|size:10',
        ];
    }
}

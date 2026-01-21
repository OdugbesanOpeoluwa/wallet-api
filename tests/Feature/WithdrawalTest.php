<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Wallet;

class WithdrawalTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic feature test example.
     */
    public function test_withdrawal()
    {

        $user = User::factory()->create();
        $wallet = Wallet::create([
            'user_id' => $user->id,
            'currency' => 'NGN',
            'balance' => 50000,
            'locked_balance' => 0,
        ]);

        $response = $this->actingAs($user)->postJson('/api/withdrawals', [
            'wallet_id' => $wallet->id,
            'amount' => 10000,
            'bank_code' => '044',
            'account_number' => '9079408325',
        ]);

        $response->assertStatus(201);
        
        $wallet->refresh();
        $this->assertEquals(40000, $wallet->balance);
        $this->assertEquals(10000, $wallet->locked_balance);
    }
    public function test_withdrawal_insufficient_balance()
    {
        $user = User::factory()->create();

        $wallet = Wallet::create([
            'user_id' => $user->id,
            'currency' => 'NGN',
            'balance' => 1050,
            'locked_balance' => 0,
        ]);

        $response = $this->actingAs($user)->postJson('/api/withdrawals', [
            'wallet_id' => $wallet->id,
            'amount' => 50000,
            'bank_code' => '044',
            'account_number' => '8060402272',
        ]);

        $response->assertStatus(400);
    }
}

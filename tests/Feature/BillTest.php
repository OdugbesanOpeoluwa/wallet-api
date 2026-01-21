<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Wallet;

class BillTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic feature test example.
     */
    public function test_pay_bill()
    {
        $user = User::factory()->create();
        $wallet = Wallet::create([
            'user_id' => $user->id,
            'currency' => 'NGN',
            'balance' => 10000,
            'locked_balance' => 0,
        ]);
        $response = $this->actingAs($user)->postJson('/api/bills/pay', [
            'wallet_id' => $wallet->id,
            'amount' => 5000,
            'bill_type' => 'electricity',
            'bill_account' => '1234567890',
            'provider' => 'IKEDC',
        ]);
        $response->assertStatus(201);
        $this->assertEquals(5000, $wallet->fresh()->balance);
    }
    public function test_pay_bill_insufficient_balance()
    {
        $user = User::factory()->create();
        $wallet = Wallet::create([
            'user_id' => $user->id,
            'currency' => 'NGN',
            'balance' => 100,
            'locked_balance' => 0,
        ]);
        $response = $this->actingAs($user)->postJson('/api/bills/pay', [
            'wallet_id' => $wallet->id,
            'amount' => 5000,
            'bill_type' => 'electricity',
            'bill_account' => '1234567890',
            'provider' => 'IKEDC',
        ]);
        $response->assertStatus(400);
    }
}

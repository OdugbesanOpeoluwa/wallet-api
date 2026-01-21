<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Wallet;

class WebhookTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic feature test example.
     */

    public function test_crypto_webhook()
    {

        $user = User::factory()->create();
        $wallet = Wallet::create([
            'user_id' => $user->id,
            'currency' => 'BTC',
            'balance' => 0,
            'locked_balance' => 0,
        ]);

        $payload = [
            'event_id' => 'evt_123',
            'wallet_id' => $wallet->id,
            'amount' => 0.001,
            'currency' => 'BTC',
            'status' => 'confirmed',
            'txn_hash' => '0xabc123',
        ];

        $secret = config('services.crypto.webhook_secret');
        $signature = hash_hmac('sha256', json_encode($payload), $secret);
        $response = $this->postJson('/api/webhooks/crypto', $payload, [
            'X-Signature' => $signature,
        ]);

        $response->assertStatus(200);
        $this->assertEquals(0.001, $wallet->fresh()->balance);
    }

    public function test_webhook_invalid_signature()
    {

        $response = $this->postJson('/api/webhooks/crypto', ['test' => 1], [
            'X-Signature' => 'invalid',
        ]);
        
        $response->assertStatus(401);
    }
}
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Wallet;


class TransferTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic feature test example.
     */
    public function test_transfer_success()
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $senderWallet = Wallet::create([
            'user_id' => $sender->id,
            'currency' => 'NGN',
            'balance' => 10000,
            'locked_balance' => 0,
        ]);

        $recipientWallet = Wallet::create([
            'user_id' => $recipient->id,
            'currency' => 'NGN',
            'balance' => 0,
            'locked_balance' => 0,
        ]);

        $response = $this->actingAs($sender)->postJson('/api/transfers', [
            'sender_wallet_id' => $senderWallet->id,
            'recipient_wallet_id' => $recipientWallet->id,
            'amount' => 1000,
        ]);

        $response->assertStatus(201);
        $this->assertEquals(9000, $senderWallet->fresh()->balance);
        $this->assertEquals(1000, $recipientWallet->fresh()->balance);
    }

    public function test_transfer_insufficient_balance()
    {

        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        $senderWallet = Wallet::create([
            'user_id' => $sender->id,
            'currency' => 'NGN',
            'balance' => 100,
            'locked_balance' => 0,
        ]);

        $recipientWallet = Wallet::create([
            'user_id' => $recipient->id,
            'currency' => 'NGN',
            'balance' => 0,
            'locked_balance' => 0,
        ]);

        $response = $this->actingAs($sender)->postJson('/api/transfers', [
            'sender_wallet_id' => $senderWallet->id,
            'recipient_wallet_id' => $recipientWallet->id,
            'amount' => 1000,
        ]);

        $response->assertStatus(400);
    }

    public function test_transfer_currency_mismatch()
    {
        
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $senderWallet = Wallet::create([
            'user_id' => $sender->id,
            'currency' => 'NGN',
            'balance' => 10000,
            'locked_balance' => 0,
        ]);

        $recipientWallet = Wallet::create([
            'user_id' => $recipient->id,
            'currency' => 'BTC',
            'balance' => 0,
            'locked_balance' => 0,
        ]);

        $response = $this->actingAs($sender)->postJson('/api/transfers', [
            'sender_wallet_id' => $senderWallet->id,
            'recipient_wallet_id' => $recipientWallet->id,
            'amount' => 1000,
        ]);

        $response->assertStatus(400);
    }

    public function test_cannot_transfer_from_other_wallet()
    {

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $wallet1 = Wallet::create([
            'user_id' => $user1->id,
            'currency' => 'NGN',
            'balance' => 10000,
            'locked_balance' => 0,
        ]);

        $wallet2 = Wallet::create([
            'user_id' => $user2->id,
            'currency' => 'NGN',
            'balance' => 0,
            'locked_balance' => 0,
        ]);

        $response = $this->actingAs($user2)->postJson('/api/transfers', [
            'sender_wallet_id' => $wallet1->id,
            'recipient_wallet_id' => $wallet2->id,
            'amount' => 1000,
        ]);
        
        $response->assertStatus(404);
    }
}
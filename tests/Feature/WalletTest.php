<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Wallet;

class WalletTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic feature test example.
     */
 public function test_create_wallet()
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->postJson('/api/wallets', [
            'currency' => 'NGN',
        ]);
        $response->assertStatus(201);
        $this->assertDatabaseHas('wallets', [
            'user_id' => $user->id,
            'currency' => 'NGN',
        ]);
    }
    public function test_create_duplicate_wallet()
    {
        $user = User::factory()->create();
        Wallet::create([
            'user_id' => $user->id,
            'currency' => 'NGN',
            'balance' => 0,
            'locked_balance' => 0,
        ]);
        $response = $this->actingAs($user)->postJson('/api/wallets', [
            'currency' => 'NGN',
        ]);
        $response->assertStatus(400);
    }
    public function test_list_wallets()
    {
        $user = User::factory()->create();
        Wallet::create([
            'user_id' => $user->id,
            'currency' => 'NGN',
            'balance' => 1000,
            'locked_balance' => 0,
        ]);
        $response = $this->actingAs($user)->getJson('/api/wallets');
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }
    public function test_cannot_access_other_user_wallet()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        $wallet = Wallet::create([
            'user_id' => $user1->id,
            'currency' => 'NGN',
            'balance' => 1000,
            'locked_balance' => 0,
        ]);
        $response = $this->actingAs($user2)->getJson("/api/wallets/{$wallet->id}/transactions");
        $response->assertStatus(404);
    }
}

<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;    

class AuthTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic feature test example.
     */

    public function test_register()
    {
        $response = $this->postJson('/api/register', [
            'name' => 'opeoluwa',
            'email' => 'opeoluwa@gmail.com',
            'password' => 'Vdkk2#@wjnw',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['status', 'data' => ['user', 'token']]);
    }

    public function test_register_duplicate_email()
    {
        User::factory()->create(['email' => 'opeoluwa@gmail.com']);
        $response = $this->postJson('/api/register', [
            'name' => 'Test',
            'email' => 'opeoluwa@gmail.com',
            'password' => 'Vdkk2#@wjnw',
        ]);

        $response->assertStatus(422);
    }

    public function test_login()
    {
        $user = User::factory()->create(['password' => bcrypt('Vdkk2#@wjnw')]);
        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'Vdkk2#@wjnw',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['status', 'data' => ['user', 'token']]);
    }

    public function test_login_wrong_password()
    {
        $user = User::factory()->create();
        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrong',
        ]);

        $response->assertStatus(401);
    }

    public function test_logout()
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->postJson('/api/logout');

        $response->assertStatus(200);
    }
}

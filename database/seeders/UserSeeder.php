<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $user1 = User::create([
            'name' => 'Opeoluwa',
            'email' => 'odugbesanopeoluwa@gmail.com',
            'password' => Hash::make('Gbesani@#$#$#43l003'),
        ]);

        Wallet::create([
            'user_id' => $user1->id,
            'currency' => 'NGN',
            'balance' => 100000,
            'locked_balance' => 0,
        ]);
    }
}
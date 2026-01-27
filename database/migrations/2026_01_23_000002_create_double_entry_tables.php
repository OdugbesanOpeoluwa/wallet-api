<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 50); // USER_WALLET, SYSTEM_FLOAT, COMPANY_REVENUE, EXTERNAL_PROVIDER
            $table->uuid('wallet_id')->nullable(); // it will be liked to user wallet if the type is USER_WALLET
            $table->string('currency', 10);
            $table->decimal('balance', 20, 8)->default(0);
            $table->string('name')->nullable(); 
            $table->timestamps();

            $table->index(['type', 'currency'], 'idx_accounts_type_currency');
            $table->unique(['wallet_id', 'currency'], 'uniq_accounts_wallet_currency');
        });

        // Journals - one per financial transaction
        Schema::create('journals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('reference', 100)->unique();
            $table->string('type', 50); // transfer, deposit, withdrawal, bill_payment, fee
            $table->decimal('amount', 20, 8); // Principal amount
            $table->string('currency', 10);
            $table->string('status', 20); // pending, success, failed, unknown, requires_review
            $table->json('metadata')->nullable();
            $table->string('idempotency_key', 255)->nullable()->unique();
            $table->timestamps();

            $table->index(['status', 'created_at'], 'idx_journals_status');
            $table->index(['type', 'status', 'created_at'], 'idx_journals_type_status');
        });

        // Entries - so basically the sum of the  debit and credit legs must sum to zero per journal 
        Schema::create('entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('journal_id');
            $table->uuid('account_id');
            $table->decimal('amount', 20, 8); // Positive = Credit, Negative = Debit
            $table->string('type', 10); // The type will be either DEBIT or CREDIT 
            $table->timestamps();

            $table->foreign('journal_id')->references('id')->on('journals')->onDelete('cascade');
            $table->foreign('account_id')->references('id')->on('accounts');
            $table->index(['account_id', 'created_at'], 'idx_entries_account');
            $table->index(['journal_id'], 'idx_entries_journal');
        });

        // Seed system accounts
        $this->seedSystemAccounts();
    }


    protected function seedSystemAccounts(): void
    {
        $currencies = ['NGN', 'USD', 'BTC', 'ETH', 'USDT'];
        $now = now();

        foreach ($currencies as $currency) {
            // System float - The total money will be managed by the platform
            DB::table('accounts')->insert([
                'id' => Str::uuid(),
                'type' => 'SYSTEM_FLOAT',
                'wallet_id' => null,
                'currency' => $currency,
                'balance' => 0,
                'name' => "System Float ({$currency})",
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Company revenue - The platform's earnings
            DB::table('accounts')->insert([
                'id' => Str::uuid(),
                'type' => 'COMPANY_REVENUE',
                'wallet_id' => null,
                'currency' => $currency,
                'balance' => 0,
                'name' => "Company Revenue ({$currency})",
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // External provider accounts (bank/crypto) - Tracks money leaving/entering
            DB::table('accounts')->insert([
                'id' => Str::uuid(),
                'type' => 'EXTERNAL_PROVIDER',
                'wallet_id' => null,
                'currency' => $currency,
                'balance' => 0,
                'name' => "External Provider ({$currency})",
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entries');
        Schema::dropIfExists('journals');
        Schema::dropIfExists('accounts');
    }
};

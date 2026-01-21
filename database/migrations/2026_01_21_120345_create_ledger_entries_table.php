<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('wallet_id')->nullable();
            $table->string('account_type'); // USER_WALLET, SYSTEM_FLOAT, COMPANY
            $table->decimal('debit', 20, 8)->default(0);
            $table->decimal('credit', 20, 8)->default(0);
            $table->uuid('transaction_id');

            $table->foreign('wallet_id')->references('id')->on('wallets');
            $table->foreign('transaction_id')->references('id')->on('transactions');
            $table->index('account_type');
            $table->index('wallet_id');
            $table->index('transaction_id');
            $table->index('created_at');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};

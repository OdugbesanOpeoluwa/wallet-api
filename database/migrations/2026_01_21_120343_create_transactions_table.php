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
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('reference')->unique();
            $table->string('type'); //transfer, deposit, withdrawal, bill_payment
            $table->string('status'); //pending, completed, failed
            $table->decimal('amount', 20,8);
            $table->string('currency');
            $table->uuid('sender_wallet_id')->nullable();
            $table->uuid('recipient_wallet_id')->nullable();
            $table->json('metadata')->nullable();
            $table->string('idempotency_key')->nullable()->unique();
            $table->timestamps();

            $table->foreign('sender_wallet_id')->references('id')->on('wallets');
            $table->foreign('recipient_wallet_id')->references('id')->on('wallets');
            $table->index('sender_wallet_id');
            $table->index('recipient_wallet_id');
            $table->index('reference');
            $table->index('idempotency_key');
            $table->index('type');
            $table->index('status');
            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};

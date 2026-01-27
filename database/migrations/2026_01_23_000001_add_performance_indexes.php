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
        // Transaction indexes for user/status queries
        Schema::table('transactions', function (Blueprint $table) {
            $table->index(['sender_wallet_id', 'status', 'created_at'], 'idx_txn_sender_status');
            $table->index(['recipient_wallet_id', 'status', 'created_at'], 'idx_txn_recipient_status');
            $table->index(['status', 'type', 'created_at'], 'idx_txn_status_type');
        });

        // Outbox indexes
        Schema::table('outbox', function (Blueprint $table) {
            $table->unsignedTinyInteger('retry_count')->default(0)->after('processed');
            $table->index(['processed', 'retry_count', 'created_at'], 'idx_outbox_pending');
        });

        // Webhook log indexes for provider/status queries
        Schema::table('webhook_logs', function (Blueprint $table) {
            $table->timestamp('processed_at')->nullable()->after('processed');
            $table->index(['provider', 'processed', 'created_at'], 'idx_webhook_pending');
        });

        // Ledger entry indexes for wallet history
        Schema::table('ledger_entries', function (Blueprint $table) {
            $table->index(['wallet_id', 'created_at'], 'idx_ledger_wallet_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('idx_txn_sender_status');
            $table->dropIndex('idx_txn_recipient_status');
            $table->dropIndex('idx_txn_status_type');
        });

        Schema::table('outbox', function (Blueprint $table) {
            $table->dropColumn('retry_count');
            $table->dropIndex('idx_outbox_pending');
        });

        Schema::table('webhook_logs', function (Blueprint $table) {
            $table->dropColumn('processed_at');
            $table->dropIndex('idx_webhook_pending');
        });

        Schema::table('ledger_entries', function (Blueprint $table) {
            $table->dropIndex('idx_ledger_wallet_time');
        });
    }
};

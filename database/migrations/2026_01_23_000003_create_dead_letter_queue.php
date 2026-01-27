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
        Schema::create('dead_letter_queue', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 50); // withdrawal, bill_payment, webhook_processing
            $table->json('payload'); 
            $table->text('error'); // Exception message
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('pipeline_stage')->nullable(); // Where it failed
            $table->boolean('resolved')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolved_by')->nullable(); // Admin user who resolved
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            $table->index(['type', 'resolved', 'created_at'], 'idx_dlq_pending');
            $table->index(['resolved', 'created_at'], 'idx_dlq_status');
        });

        // Add status column for uncertain transactions
        Schema::table('transactions', function (Blueprint $table) {
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dead_letter_queue');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Audit trail for admin wallet operations
     */
    public function up(): void
    {
        Schema::create('admin_wallet_actions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('admin_id')->index()->comment('Admin who performed action');
            $table->foreignUuid('user_id')->nullable()->index()->comment('Target user (null for bulk)');
            $table->enum('user_type', ['customer', 'driver', 'all'])->index();
            
            // Transaction Details
            $table->enum('transaction_type', ['credit', 'debit', 'bulk_credit', 'bulk_debit']);
            $table->decimal('amount', 16, 2);
            $table->integer('affected_users_count')->default(1)->comment('For bulk operations');
            
            // Audit Info
            $table->text('note')->nullable()->comment('Admin note/reason');
            $table->string('reference', 100)->nullable()->comment('Reference number');
            $table->decimal('balance_before', 16, 2)->nullable();
            $table->decimal('balance_after', 16, 2)->nullable();
            
            // IP and Device Tracking
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            
            $table->timestamp('created_at');
            
            // Indexes for reporting
            $table->index('created_at');
            $table->index(['admin_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_wallet_actions');
    }
};


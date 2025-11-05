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
        Schema::create('admin_wallet_payment_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_id')->unique();
            $table->string('razorpay_order_id')->nullable()->unique();
            $table->string('razorpay_payment_id')->nullable();
            $table->string('razorpay_signature')->nullable();
            
            $table->uuid('admin_id');
            $table->uuid('user_id');
            $table->enum('user_type', ['customer', 'driver']);
            
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('INR');
            
            $table->enum('payment_method', ['upi', 'netbanking', 'card', 'wallet'])->default('upi');
            $table->string('payment_method_used')->nullable(); // Actual method used after payment
            
            $table->enum('status', ['pending', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->text('notes')->nullable();
            $table->text('failure_reason')->nullable();
            
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            
            $table->index('admin_id');
            $table->index('user_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_wallet_payment_orders');
    }
};


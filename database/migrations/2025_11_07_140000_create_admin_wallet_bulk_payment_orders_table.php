<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_wallet_bulk_payment_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_id')->unique();
            $table->string('razorpay_order_id')->nullable()->unique();
            $table->string('razorpay_payment_id')->nullable();
            $table->string('razorpay_signature')->nullable();

            $table->uuid('admin_id')->index();
            $table->enum('user_type', ['customer', 'driver', 'all'])->index();

            $table->decimal('per_user_amount', 12, 2);
            $table->integer('target_users_count');
            $table->decimal('total_amount', 14, 2);
            $table->string('currency', 3)->default('INR');

            $table->enum('payment_method', ['upi', 'netbanking', 'card', 'wallet'])->default('upi');
            $table->string('payment_method_used')->nullable();

            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->text('failure_reason')->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_wallet_bulk_payment_orders');
    }
};



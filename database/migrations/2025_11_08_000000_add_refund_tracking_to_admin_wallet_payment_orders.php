<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_wallet_payment_orders', function (Blueprint $table) {
            $table->decimal('refunded_amount', 10, 2)->default(0)->after('amount');
            $table->timestamp('last_refunded_at')->nullable()->after('paid_at');
        });

        Schema::create('admin_wallet_payment_refunds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_order_id');
            $table->uuid('admin_id');
            $table->uuid('user_id');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('INR');
            $table->string('razorpay_refund_id')->nullable();
            $table->string('razorpay_payment_id')->nullable();
            $table->string('razorpay_order_id')->nullable();
            $table->enum('status', ['initiated', 'succeeded', 'failed'])->default('initiated');
            $table->text('failure_reason')->nullable();
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('payment_order_id');
            $table->index('user_id');
            $table->index('razorpay_payment_id');
            $table->index('razorpay_refund_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_wallet_payment_refunds');

        Schema::table('admin_wallet_payment_orders', function (Blueprint $table) {
            $table->dropColumn(['refunded_amount', 'last_refunded_at']);
        });
    }
};



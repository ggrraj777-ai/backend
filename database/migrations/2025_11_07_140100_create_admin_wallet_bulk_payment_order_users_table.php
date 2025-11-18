<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_wallet_bulk_payment_order_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bulk_order_id');
            $table->uuid('user_id');
            $table->timestamps();

            $table->foreign('bulk_order_id')
                ->references('id')
                ->on('admin_wallet_bulk_payment_orders')
                ->onDelete('cascade');

            $table->index(['bulk_order_id', 'user_id'], 'bulk_order_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_wallet_bulk_payment_order_users');
    }
};



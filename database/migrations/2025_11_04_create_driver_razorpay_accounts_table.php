<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Stores Razorpay linked account info for drivers (for auto-split)
     */
    public function up(): void
    {
        Schema::create('driver_razorpay_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('driver_id')->unique();
            
            // Razorpay Account Details
            $table->string('razorpay_account_id')->nullable()->unique()->comment('Razorpay linked account ID');
            $table->string('razorpay_contact_id')->nullable()->comment('Razorpay contact ID');
            
            // Bank Account Details
            $table->string('account_holder_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('ifsc_code')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('branch_name')->nullable();
            $table->enum('account_type', ['savings', 'current'])->default('savings');
            
            // UPI Details (Alternative)
            $table->string('upi_id')->nullable()->comment('UPI ID for instant settlements');
            
            // Verification Status
            $table->enum('verification_status', [
                'pending',
                'verified',
                'failed',
                'suspended'
            ])->default('pending')->index();
            
            $table->text('verification_notes')->nullable();
            $table->timestamp('verified_at')->nullable();
            
            // Settlement Configuration
            $table->decimal('settlement_percentage', 8, 2)->default(0)->comment('Driver share percentage');
            $table->boolean('auto_settlement_enabled')->default(true);
            $table->enum('settlement_schedule', ['instant', 'daily', 'weekly'])->default('instant');
            
            // Tracking
            $table->decimal('total_settled_amount', 16, 2)->default(0);
            $table->integer('total_settlements')->default(0);
            $table->timestamp('last_settlement_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign key
            $table->foreign('driver_id')->references('id')->on('users')->onDelete('cascade');
        });

        // Table for tracking individual settlements
        Schema::create('razorpay_settlements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('driver_id')->index();
            $table->foreignUuid('trip_id')->nullable()->index();
            
            // Razorpay Details
            $table->string('razorpay_payment_id')->nullable();
            $table->string('razorpay_transfer_id')->nullable()->unique();
            $table->string('razorpay_order_id')->nullable();
            
            // Amount Details
            $table->decimal('trip_fare', 16, 2)->default(0)->comment('Total trip fare');
            $table->decimal('platform_share', 16, 2)->default(0)->comment('Company share');
            $table->decimal('driver_share', 16, 2)->default(0)->comment('Driver share');
            $table->decimal('platform_fee', 16, 2)->default(0);
            $table->decimal('insurance_fee', 16, 2)->default(0);
            $table->decimal('gst_amount', 16, 2)->default(0);
            
            // Settlement Status
            $table->enum('status', [
                'pending',
                'processing',
                'settled',
                'failed',
                'reversed'
            ])->default('pending')->index();
            
            $table->text('failure_reason')->nullable();
            $table->timestamp('settled_at')->nullable();
            
            // Metadata
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('driver_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('trip_id')->references('id')->on('trip_requests')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('razorpay_settlements');
        Schema::dropIfExists('driver_razorpay_accounts');
    }
};


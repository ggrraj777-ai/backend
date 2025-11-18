<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Enhanced driver document management with Firebase Storage
     */
    public function up(): void
    {
        Schema::create('driver_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('driver_id')->index();
            
            // Document Type
            $table->enum('document_type', [
                'driving_license',
                'rc_book',
                'aadhar_card',
                'photo',
                'other'
            ])->index();
            
            // Document Numbers/IDs
            $table->string('document_number')->nullable()->comment('License/RC/Aadhar number');
            
            // Document Images (Firebase Storage URLs)
            $table->text('front_image_url')->nullable()->comment('Front side image');
            $table->text('back_image_url')->nullable()->comment('Back side image');
            $table->text('firebase_front_path')->nullable()->comment('Firebase storage path');
            $table->text('firebase_back_path')->nullable()->comment('Firebase storage path');
            
            // Verification Status
            $table->enum('verification_status', [
                'pending',
                'approved',
                'rejected',
                'expired'
            ])->default('pending')->index();
            
            $table->text('rejection_reason')->nullable();
            $table->foreignUuid('verified_by')->nullable()->comment('Admin user ID');
            $table->timestamp('verified_at')->nullable();
            
            // Expiry (for license, RC)
            $table->date('expiry_date')->nullable();
            $table->boolean('is_expired')->default(false);
            
            // Metadata
            $table->json('metadata')->nullable()->comment('Additional document info');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign keys
            $table->foreign('driver_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('verified_by')->references('id')->on('users')->onDelete('set null');
        });

        // Add document verification columns to users table
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'document_verification_status')) {
                $table->enum('document_verification_status', [
                    'pending',
                    'partial',
                    'approved',
                    'rejected'
                ])->default('pending')->after('is_active');
            }
            if (!Schema::hasColumn('users', 'documents_verified_at')) {
                $table->timestamp('documents_verified_at')->nullable()->after('document_verification_status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_documents');
        
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['document_verification_status', 'documents_verified_at']);
        });
    }
};


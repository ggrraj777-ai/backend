<?php
/**
 * Quick script to create admin user
 * Run this via: php create-admin-user.php
 * Or via Render.com Shell: php create-admin-user.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Ramsey\Uuid\Uuid;

try {
    // Check database connection
    DB::connection()->getPdo();
    echo "✓ Database connection successful\n";
    
    // Check if users table exists
    if (!DB::getSchemaBuilder()->hasTable('users')) {
        echo "❌ ERROR: 'users' table does not exist!\n";
        echo "Please run migrations first:\n";
        echo "  php artisan migrate --force\n";
        exit(1);
    }
    
    echo "✓ Users table exists\n";
    
    // Check if admin user already exists
    $adminExists = DB::table('users')->where('email', 'admin@admin.com')->exists();
    
    if ($adminExists) {
        echo "⚠️  Admin user already exists! Updating to ensure correct settings...\n";
        $user = DB::table('users')->where('email', 'admin@admin.com')->first();
        echo "Current User ID: {$user->id}\n";
        echo "Current User Type: {$user->user_type}\n";
        echo "Current Is Active: " . ($user->is_active ? 'Yes' : 'No') . "\n";
        
        // Always reset password and ensure correct settings for Render.com (non-interactive)
        DB::table('users')
            ->where('email', 'admin@admin.com')
            ->update([
                'password' => Hash::make('12345678'),
                'user_type' => 'super-admin',
                'is_active' => true,
                'updated_at' => now()
            ]);
        echo "✓ Password reset and user settings updated\n";
    } else {
        // Create admin user
        echo "Creating admin user...\n";
        
        $userId = Uuid::uuid4()->toString();
        DB::table('users')->insert([
            'id' => $userId,
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'admin@admin.com',
            'password' => Hash::make('12345678'),
            'user_type' => 'super-admin',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        echo "✓ Admin user created successfully!\n";
        echo "\nLogin Credentials:\n";
        echo "  Email: admin@admin.com\n";
        echo "  Password: 12345678\n";
    }
    
    // Verify password
    $user = DB::table('users')->where('email', 'admin@admin.com')->first();
    if ($user && Hash::check('12345678', $user->password)) {
        echo "✓ Password verification successful\n";
    } else {
        echo "⚠️  WARNING: Password verification failed!\n";
        echo "Resetting password...\n";
        DB::table('users')
            ->where('email', 'admin@admin.com')
            ->update([
                'password' => Hash::make('12345678'),
                'is_active' => true,
                'user_type' => 'super-admin'
            ]);
        echo "✓ Password reset and user activated\n";
    }
    
    // Final verification
    $finalUser = DB::table('users')->where('email', 'admin@admin.com')->first();
    echo "\n=== Final User Status ===\n";
    echo "ID: {$finalUser->id}\n";
    echo "Email: {$finalUser->email}\n";
    echo "User Type: {$finalUser->user_type}\n";
    echo "Is Active: " . ($finalUser->is_active ? 'Yes (1)' : 'No (0)') . "\n";
    echo "Password Set: " . (!empty($finalUser->password) ? 'Yes' : 'No') . "\n";
    
    // Test password one more time
    if (Hash::check('12345678', $finalUser->password)) {
        echo "✓ Final password check: SUCCESS\n";
    } else {
        echo "❌ Final password check: FAILED\n";
    }
    
} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "\nTroubleshooting:\n";
    echo "1. Check database environment variables (DB_HOST, DB_DATABASE, etc.)\n";
    echo "2. Verify database is accessible\n";
    echo "3. Make sure migrations have been run: php artisan migrate --force\n";
    exit(1);
}


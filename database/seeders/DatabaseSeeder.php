<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create System User
        $systemUser = User::factory()->create([
            'name' => 'System User',
            'email' => 'system@newsapp.com',
            'password' => Hash::make('system_password_123'),
            'email_verified_at' => now(),
        ]);

        // Create Admin User
        $adminUser = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@newsapp.com',
            'password' => Hash::make('admin_password_123'),
            'email_verified_at' => now(),
        ]);

        // Create tokens
        $systemToken = $systemUser->createToken('system-api-token')->plainTextToken;
        $adminToken = $adminUser->createToken('admin-api-token')->plainTextToken;

        $this->command->info('=== USER TOKENS GENERATED ===');
        $this->command->info('System User Token: ' . $systemToken);
        $this->command->info('Admin User Token: ' . $adminToken);
        $this->command->info('=============================');
        $this->command->info('Add to .env: VITE_API_TOKEN=' . $systemToken);

        // Other users...
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
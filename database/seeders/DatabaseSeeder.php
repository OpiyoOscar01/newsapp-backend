<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create or update System User
        $systemUser = User::updateOrCreate(
            ['email' => 'system@newsapp.com'],
            [
                'name' => 'System User',
                'password' => Hash::make('system_password_123'),
                'email_verified_at' => now(),
            ]
        );

        // Create or update Admin User
        $adminUser = User::updateOrCreate(
            ['email' => 'admin@newsapp.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('admin_password_123'),
                'email_verified_at' => now(),
            ]
        );

        // Generate tokens
        $systemToken = $systemUser->createToken('system-api-token')->plainTextToken;
        $adminToken = $adminUser->createToken('admin-api-token')->plainTextToken;

        $this->command->info('=== USER TOKENS GENERATED ===');
        $this->command->info('System User Token: ' . $systemToken);
        $this->command->info('Admin User Token: ' . $adminToken);
        $this->command->info('=============================');
        $this->command->info('Add to .env: VITE_API_TOKEN=' . $systemToken);

        // Create or update Test User (with password!)
        User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('test_password_123'),
                'email_verified_at' => now(),
            ]
        );

        // === API CALLS TO /fetch ENDPOINT (5 times) ===
        for ($i = 1; $i <= 5; $i++) {
            $this->command->info("=== API Call #{$i} ===");

            $response = Http::withToken($systemToken)
                ->post(config('app.url') . '/api/v1/mediastack/fetch');

            if ($response->successful()) {
                $this->command->info('API Response: ' . json_encode($response->json()));
            } else {
                $this->command->error('API call failed: ' . $response->status());
                $this->command->error('Response body: ' . $response->body());
            }
        }
    }
}

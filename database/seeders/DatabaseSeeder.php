<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ============================================
        // STEP 1: CREATE ROLES WITH PROPER GUARD
        // ============================================
        $this->command->info('Creating roles...');
        
        // Get the default guard from config (should be 'sanctum' now)
        $guardName = config('auth.defaults.guard', 'web');
        $this->command->info("Using guard: {$guardName}");
        
        // Create roles with explicit guard
        $adminRole = Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => $guardName]
        );
        $systemRole = Role::firstOrCreate(
            ['name' => 'system', 'guard_name' => $guardName]
        );
        $userRole = Role::firstOrCreate(
            ['name' => 'user', 'guard_name' => $guardName]
        );
        
        $this->command->info('✓ Admin role created (ID: ' . $adminRole->id . ')');
        $this->command->info('✓ System role created (ID: ' . $systemRole->id . ')');
        $this->command->info('✓ User role created (ID: ' . $userRole->id . ')');
        
        // ============================================
        // STEP 2: CREATE USERS
        // ============================================
        $this->command->info("\nCreating users...");
        
        // Create System User
        $systemUser = User::updateOrCreate(
            ['email' => 'system@definepress.com'],
            [
                'first_name' => 'System',
                'last_name' => 'User',
                'name' => 'System User',
                'password' => Hash::make('system_password_123'),
                'email_verified_at' => now(),
            ]
        );
        $systemUser->syncRoles(['system']);
        $this->command->info('✓ System user created');
        
        // Create Admin User
        $adminUser = User::updateOrCreate(
            ['email' => 'admin@definepress.com'],
            [
                'first_name' => 'Admin',
                'last_name' => 'User',
                'name' => 'Admin User',
                'password' => Hash::make('admin_password_123'),
                'email_verified_at' => now(),
            ]
        );
        $adminUser->syncRoles(['admin']);
        $this->command->info('✓ Admin user created');
        
        // Create Test User
        $testUser = User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'first_name' => 'Test',
                'last_name' => 'User',
                'name' => 'Test User',
                'password' => Hash::make('test_password_123'),
                'email_verified_at' => now(),
            ]
        );
        $testUser->syncRoles(['user']);
        $this->command->info('✓ Test user created');
        
        // ============================================
        // STEP 3: GENERATE API TOKENS
        // ============================================
        $this->command->info("\nGenerating API tokens...");
        
        $systemToken = $systemUser->createToken('system-api-token')->plainTextToken;
        $adminToken = $adminUser->createToken('admin-api-token')->plainTextToken;
        $testToken = $testUser->createToken('test-api-token')->plainTextToken;
        
        $this->command->info("\n========== USER TOKENS ==========");
        $this->command->info("System User Token:\n{$systemToken}");
        $this->command->info("\nAdmin User Token:\n{$adminToken}");
        $this->command->info("\nTest User Token:\n{$testToken}");
        $this->command->info("=================================\n");
        
        // ============================================
        // STEP 4: FETCH ARTICLES PER CATEGORY
        // ============================================
        $this->command->info("\nFetching articles per category (target: 50 per category)...\n");
        
        $categories = ['general', 'business', 'entertainment', 'health', 'science', 'sports', 'technology'];
        $targetPerCategory = 50;
        $maxPagesPerCategory = 10;
        
        foreach ($categories as $category) {
            $currentCount = Article::where('category', $category)->count();
            $needed = max(0, $targetPerCategory - $currentCount);
            
            if ($needed <= 0) {
                $this->command->info("✓ {$category}: already has {$currentCount} articles, skipping");
                continue;
            }
            
            $this->command->info("→ {$category}: needs {$needed} more (has {$currentCount})");
            
            $pagesFetched = 0;
            $totalFetched = 0;
            
            while ($totalFetched < $needed && $pagesFetched < $maxPagesPerCategory) {
                $offset = $pagesFetched * 100;
                $pagesFetched++;
                
                try {
                    $response = Http::withToken($systemToken)
                        ->timeout(30)
                        ->post(config('app.url') . '/api/v1/mediastack/fetch', [
                            'categories' => $category,
                            'limit' => 100,
                            'offset' => $offset,
                            'force_refresh' => true,
                        ]);
                    
                    if ($response->successful()) {
                        $result = $response->json()['data'] ?? [];
                        $articlesInBatch = $result['articles_processed'] ?? 0;
                        $totalFetched += $articlesInBatch;
                        
                        $this->command->info("  Page {$pagesFetched} (offset {$offset}): +{$articlesInBatch} articles");
                    } else {
                        $this->command->warn("  Page {$pagesFetched}: API error — " . $response->body());
                    }
                } catch (\Exception $e) {
                    $this->command->error("  Page {$pagesFetched}: Exception — " . $e->getMessage());
                }
            }
            
            $finalCount = Article::where('category', $category)->count();
            $this->command->info("  → {$category} now has {$finalCount} articles\n");
        }
        
        // ============================================
        // STEP 5: SUMMARY
        // ============================================
        $this->command->info("\n========== FINAL ARTICLE COUNTS ==========");
        
        foreach ($categories as $category) {
            $count = Article::where('category', $category)->count();
            $status = $count >= $targetPerCategory ? '✓' : '✗';
            $this->command->info("{$status} {$category}: {$count} articles");
        }
        
        $this->command->info("===========================================\n");
        $this->command->info("✅ Database seeding completed successfully!");
    }
}
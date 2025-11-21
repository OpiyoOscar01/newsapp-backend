<?php

use App\Http\Controllers\Api\{
    AuthController,
    CategoryController,
    SourceController,
    ArticleController,
    ApiFetchLogController,
    ArticleInteractionController,
    ArticleKeywordController,
    MediastackSettingController,
    FetchScheduleController,
    NewsController,
    AnalyticsController,
    MediaStackController
};
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public API routes (no authentication required)
Route::prefix('v1')->middleware(['throttle:api'])->group(function () {
    
    // 🔐 AUTHENTICATION ROUTES (Public)
    Route::prefix('auth')->controller(AuthController::class)->group(function () {
        Route::post('register', 'register');          // POST /api/v1/auth/register
        Route::post('login', 'login');                // POST /api/v1/auth/login
    });
    
    // News API for Frontend
    Route::prefix('news')->controller(NewsController::class)->group(function () {
        Route::get('latest', 'latest');
        Route::get('trending', 'trending');
        Route::get('featured', 'featured');
        Route::get('by-category/{category}', 'byCategory');
        Route::get('by-source/{source}', 'bySource');
        Route::get('search', 'search');
        Route::get('categorized', 'categorizedNews');
    });
    
    // Public article routes
    Route::prefix('articles')->controller(ArticleController::class)->group(function () {
        Route::get('/', 'index');
        Route::get('{article}', 'show');
        Route::post('{article}/view', 'recordView');
        Route::get('{article}/related', 'related');
        Route::get('{article}/analytics', 'analytics');
    });
    
    // Public category routes
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('categories/{category}', [CategoryController::class, 'show']);
    
    // Public source routes
    Route::get('sources', [SourceController::class, 'index']);
    Route::get('sources/{source}', [SourceController::class, 'show']);

    //Testing Connection to MediaStack API
    Route::post('mediastack/test-connection', [MediaStackController::class, 'testConnection']);

});

// Protected API routes (authentication required)
Route::prefix('v1')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    
    // 🔐 AUTHENTICATION ROUTES (Protected)
    Route::prefix('auth')->controller(AuthController::class)->group(function () {
        Route::post('logout', 'logout');              // POST /api/v1/auth/logout
        Route::get('profile', 'profile');             // GET /api/v1/auth/profile
        Route::put('profile', 'updateProfile');       // PUT /api/v1/auth/profile
        Route::post('change-password', 'changePassword'); // POST /api/v1/auth/change-password
    });
    
    // 👤 User-specific routes
    Route::get('user', function (Request $request) {
        return response()->json([
            'success' => true,
            'data' => $request->user()
        ]);
    });
    
    // Admin Article Management
    Route::prefix('admin/articles')->controller(ArticleController::class)->group(function () {
        Route::post('/', 'store');
        Route::put('{article}', 'update');
        Route::delete('{article}', 'destroy');
        Route::patch('{article}/feature', 'feature');
        Route::patch('{article}/unfeature', 'unfeature');
        Route::patch('{article}/activate', 'activate');
        Route::patch('{article}/deactivate', 'deactivate');
        Route::post('sync-mediastack', 'syncFromMediaStack');
    });
    
    // MediaStack Integration
    Route::prefix('mediastack')->controller(MediaStackController::class)->group(function () {
        Route::post('fetch', 'fetchNews');
        Route::post('fetch-latest', 'fetchLatest');
        Route::post('fetch-category/{category}', 'fetchByCategory');
        Route::get('status', 'apiStatus');
        Route::get('usage-stats', 'usageStats');
    });
    
    // Admin Resource Routes
    Route::apiResource('admin/categories', CategoryController::class);
    Route::apiResource('admin/sources', SourceController::class);
    Route::apiResource('admin/fetch-logs', ApiFetchLogController::class);
    Route::apiResource('admin/interactions', ArticleInteractionController::class);
    Route::apiResource('admin/keywords', ArticleKeywordController::class);
    Route::apiResource('admin/settings', MediastackSettingController::class);
    Route::apiResource('admin/schedules', FetchScheduleController::class);
    
    // Analytics Routes
    Route::prefix('analytics')->controller(AnalyticsController::class)->group(function () {
        Route::get('dashboard', 'dashboard');
        Route::get('articles/popular', 'popularArticles');
        Route::get('categories/performance', 'categoryPerformance');
        Route::get('sources/reliability', 'sourceReliability');
        Route::get('interactions/summary', 'interactionsSummary');
    });
});

// Webhook routes (for external integrations)
Route::prefix('webhooks')->middleware(['throttle:webhooks'])->group(function () {
    Route::post('mediastack', [MediaStackController::class, 'webhook']);
});
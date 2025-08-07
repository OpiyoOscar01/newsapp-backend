<?php

use App\Http\Controllers\Api\{
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

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// News API v1 routes
Route::prefix('v1')->middleware(['throttle:news'])->group(function () {
    
    // Core Resource Routes
    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('sources', SourceController::class);
    Route::apiResource('articles', ArticleController::class);
    Route::apiResource('fetch-logs', ApiFetchLogController::class);
    Route::apiResource('interactions', ArticleInteractionController::class);
    Route::apiResource('keywords', ArticleKeywordController::class);
    Route::apiResource('settings', MediastackSettingController::class);
    Route::apiResource('schedules', FetchScheduleController::class);
    
    // News-specific Custom Routes
    Route::prefix('news')->controller(NewsController::class)->group(function () {
        Route::get('latest', 'latest');
        Route::get('trending', 'trending');
        Route::get('featured', 'featured');
        Route::get('by-category/{category}', 'byCategory');
        Route::get('by-source/{source}', 'bySource');
        Route::get('search', 'search');
    });
    
    // Article-specific Custom Routes
    Route::prefix('articles')->controller(ArticleController::class)->group(function () {
        Route::patch('{article}/feature', 'feature');
        Route::patch('{article}/unfeature', 'unfeature');
        Route::patch('{article}/activate', 'activate');
        Route::patch('{article}/deactivate', 'deactivate');
        Route::post('{article}/view', 'recordView');
        Route::get('{article}/related', 'related');
    });
    
    // Category-specific Custom Routes
    Route::prefix('categories')->controller(CategoryController::class)->group(function () {
        Route::patch('{category}/activate', 'activate');
        Route::patch('{category}/deactivate', 'deactivate');
        Route::get('{category}/stats', 'stats');
    });
    
    // Source-specific Custom Routes
    Route::prefix('sources')->controller(SourceController::class)->group(function () {
        Route::patch('{source}/activate', 'activate');
        Route::patch('{source}/deactivate', 'deactivate');
        Route::post('{source}/sync', 'syncFromMediaStack');
        Route::get('{source}/performance', 'performance');
    });
    
    // Analytics Routes
    Route::prefix('analytics')->controller(AnalyticsController::class)->group(function () {
        Route::get('dashboard', 'dashboard');
        Route::get('articles/popular', 'popularArticles');
        Route::get('categories/performance', 'categoryPerformance');
        Route::get('sources/reliability', 'sourceReliability');
        Route::get('interactions/summary', 'interactionsSummary');
    });
    
    // MediaStack Integration Routes
    Route::prefix('mediastack')->controller(MediaStackController::class)->group(function () {
        Route::post('fetch', 'fetchNews');
        Route::get('status', 'apiStatus');
        Route::post('test-connection', 'testConnection');
        Route::get('usage-stats', 'usageStats');
    });
});

// Authentication required routes
Route::middleware('auth:sanctum')->group(function () {
    // User-specific routes go here
});
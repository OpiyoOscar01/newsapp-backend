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
    MediaStackController,
    NewsletterController
};
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// ============================================================================
// PUBLIC API ROUTES (No Authentication Required)
// ============================================================================
Route::prefix('v1')->group(function () {

    // ------------------------------------------------------------------------
    // Authentication Routes (Public)
    // ------------------------------------------------------------------------
    Route::prefix('auth')->controller(AuthController::class)->group(function () {
        Route::post('register', 'register');
        Route::post('login', 'login');
    });

    // ------------------------------------------------------------------------
    // News Routes (Public)
    // ------------------------------------------------------------------------
    Route::prefix('news')->controller(NewsController::class)->group(function () {
        Route::get('latest', 'latest');
        Route::get('trending', 'trending');
        Route::get('featured', 'featured');
        Route::get('by-category/{category}', 'byCategory');
        Route::get('by-source/{source}', 'bySource');
        Route::get('search', 'search');
        Route::get('categorized', 'categorizedNews');
    });

    // ------------------------------------------------------------------------
    // Category Routes (Public)
    // ------------------------------------------------------------------------
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('categories/{category}', [CategoryController::class, 'show']);

    // ------------------------------------------------------------------------
    // Source Routes (Public)
    // ------------------------------------------------------------------------
    Route::get('sources', [SourceController::class, 'index']);
    Route::get('sources/{source}', [SourceController::class, 'show']);

    // ------------------------------------------------------------------------
    // Article Routes (Public) - ORDER MATTERS! Put specific routes FIRST
    // ------------------------------------------------------------------------
    Route::prefix('articles')->group(function () {
        Route::get('slug/{slug}', [ArticleController::class, 'showBySlug']);

        Route::post('{articleId}/view', [ArticleInteractionController::class, 'recordView'])
            ->where('articleId', '[0-9]+');
        Route::post('{articleId}/like/toggle', [ArticleInteractionController::class, 'toggleLike'])
            ->where('articleId', '[0-9]+');
        Route::post('{articleId}/share', [ArticleInteractionController::class, 'recordShare'])
            ->where('articleId', '[0-9]+');
        Route::get('{articleId}/comments', [ArticleInteractionController::class, 'getComments'])
            ->where('articleId', '[0-9]+');
        Route::get('{articleId}/interactions/counts', [ArticleInteractionController::class, 'getInteractionCounts'])
            ->where('articleId', '[0-9]+');
        Route::get('{articleId}/related', [ArticleController::class, 'related'])
            ->where('articleId', '[0-9]+');

        Route::middleware(['auth:sanctum'])->group(function () {
            Route::post('{articleId}/comments', [ArticleInteractionController::class, 'addComment'])
                ->where('articleId', '[0-9]+');
            Route::post('{articleId}/bookmark/toggle', [ArticleInteractionController::class, 'toggleBookmark'])
                ->where('articleId', '[0-9]+');
            Route::get('{articleId}/analytics', [ArticleController::class, 'analytics'])
                ->where('articleId', '[0-9]+');
            Route::put('comments/{comment}', [ArticleInteractionController::class, 'updateComment']);
            Route::delete('comments/{comment}', [ArticleInteractionController::class, 'deleteComment']);
        });

        Route::get('/', [ArticleController::class, 'index']);
        Route::get('{id}', [ArticleController::class, 'show'])
            ->where('id', '[0-9]+');
    });

    // ------------------------------------------------------------------------
    // Newsletter Routes (Public)
    // ------------------------------------------------------------------------
    Route::prefix('newsletter')->controller(NewsletterController::class)->group(function () {
        Route::post('subscribe', 'subscribe');
        Route::post('unsubscribe', 'unsubscribe');
        Route::post('preferences', 'getPreferences');
        Route::put('preferences', 'updatePreferences');
    });

    // ------------------------------------------------------------------------
    // MediaStack Routes (Public)
    // ------------------------------------------------------------------------
    Route::post('mediastack/test-connection', [MediaStackController::class, 'testConnection']);
});

// ============================================================================
// PUBLIC API ROUTES WITH THROTTLE
// ============================================================================
Route::prefix('v1')->middleware(['throttle:api'])->group(function () {

    // ------------------------------------------------------------------------
    // Visitor Analytics Routes (Public with throttle)
    // ------------------------------------------------------------------------
    Route::prefix('analytics/visitors')->controller(AnalyticsController::class)->group(function () {
        Route::post('track', 'trackVisitor');
        Route::get('stats', 'getVisitorStats');
        Route::get('realtime', 'getRealtimeVisitors');
        Route::get('recent', 'getRecentVisitorEvents');
        Route::get('export', 'exportVisitorData');
    });
});

// ============================================================================
// PROTECTED API ROUTES (Authentication Required)
// ============================================================================
Route::prefix('v1')->middleware(['auth:sanctum'])->group(function () {

    // ------------------------------------------------------------------------
    // Authentication Routes (Protected)
    // ------------------------------------------------------------------------
    Route::prefix('auth')->controller(AuthController::class)->group(function () {
        Route::post('logout', 'logout');
        Route::get('profile', 'profile');
        Route::put('profile', 'updateProfile');
        Route::post('change-password', 'changePassword');
    });

    // ------------------------------------------------------------------------
    // User Routes (Protected)
    // ------------------------------------------------------------------------
    Route::get('user', function (Request $request) {
        return response()->json([
            'success' => true,
            'data' => $request->user()
        ]);
    });

    // ------------------------------------------------------------------------
    // User Interaction Routes (Protected)
    // ------------------------------------------------------------------------
    Route::prefix('user')->controller(ArticleInteractionController::class)->group(function () {
        Route::get('likes', 'getUserLikes');
        Route::get('bookmarks', 'getUserBookmarks');
    });

    // ------------------------------------------------------------------------
    // Admin Article Management Routes (Protected)
    // ------------------------------------------------------------------------
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

    // ------------------------------------------------------------------------
    // Admin Category Management Routes (Protected)
    // ------------------------------------------------------------------------
    Route::apiResource('admin/categories', CategoryController::class);

    // ------------------------------------------------------------------------
    // Admin Source Management Routes (Protected)
    // ------------------------------------------------------------------------
    Route::apiResource('admin/sources', SourceController::class);

    // ------------------------------------------------------------------------
    // Admin Fetch Log Routes (Protected)
    // ------------------------------------------------------------------------
    Route::apiResource('admin/fetch-logs', ApiFetchLogController::class);

    // ------------------------------------------------------------------------
    // Admin Interaction Routes (Protected)
    // ------------------------------------------------------------------------
    Route::apiResource('admin/interactions', ArticleInteractionController::class);

    // ------------------------------------------------------------------------
    // Admin Keyword Routes (Protected)
    // ------------------------------------------------------------------------
    Route::apiResource('admin/keywords', ArticleKeywordController::class);

    // ------------------------------------------------------------------------
    // Admin MediaStack Settings Routes (Protected)
    // ------------------------------------------------------------------------
    Route::apiResource('admin/settings', MediastackSettingController::class);

    // ------------------------------------------------------------------------
    // Admin Fetch Schedule Routes (Protected)
    // ------------------------------------------------------------------------
    Route::apiResource('admin/schedules', FetchScheduleController::class);

    // ------------------------------------------------------------------------
    // MediaStack Integration Routes (Protected)
    // ------------------------------------------------------------------------
    Route::prefix('mediastack')->controller(MediaStackController::class)->group(function () {
        Route::post('/fetch', 'fetchNews');
        Route::post('fetch-latest', 'fetchLatest');
        Route::post('fetch-category/{category}', 'fetchByCategory');
        Route::get('status', 'apiStatus');
        Route::get('usage-stats', 'usageStats');
    });

    // ------------------------------------------------------------------------
    // Analytics Routes (Protected)
    // ------------------------------------------------------------------------
    Route::prefix('analytics')->controller(AnalyticsController::class)->group(function () {
        Route::get('dashboard', 'dashboard');
        Route::get('articles/popular', 'popularArticles');
        Route::get('categories/performance', 'categoryPerformance');
        Route::get('sources/reliability', 'sourceReliability');
        Route::get('interactions/summary', 'interactionsSummary');
    });

    // ------------------------------------------------------------------------
    // Admin Analytics Routes (Protected)
    // ------------------------------------------------------------------------
    Route::prefix('admin/analytics')->controller(AnalyticsController::class)->group(function () {
        Route::get('dashboard', 'dashboard');
        Route::get('articles/popular', 'popularArticles');
        Route::get('categories/performance', 'categoryPerformance');
        Route::get('sources/reliability', 'sourceReliability');
        Route::get('interactions/summary', 'interactionsSummary');

        // Visitor analytics admin helpers
        Route::get('visitors/export-full', 'exportVisitorData');
        Route::get('visitors/recent', 'getRecentVisitorEvents');
        Route::delete('visitors/cleanup', 'cleanupVisitorData');
    });
});

// ============================================================================
// WEBHOOK ROUTES (External Integrations)
// ============================================================================
Route::prefix('webhooks')->middleware(['throttle:webhooks'])->group(function () {
    Route::post('mediastack', [MediaStackController::class, 'webhook']);
});

// ============================================================================
// AUTH DEBUG/CHECK ENDPOINTS (For testing authentication)
// ============================================================================
Route::prefix('v1')->middleware(['auth:sanctum'])->group(function () {

    Route::get('/auth/check', function (Request $request) {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'message' => 'Authentication check completed',
            'data' => [
                'authenticated' => Auth::check(),
                'user_id' => Auth::id(),
                'user_email' => $user?->email,
                'user_name' => $user?->first_name ? $user?->first_name . ' ' . $user?->last_name : $user?->name,
                'user' => $user,
                'token_details' => [
                    'has_token' => !empty($request->bearerToken()),
                    'token_preview' => $request->bearerToken() ? substr($request->bearerToken(), 0, 30) . '...' : null,
                ],
                'headers' => [
                    'authorization' => $request->header('Authorization') ? 'Bearer [HIDDEN]' : null,
                    'has_authorization' => !empty($request->header('Authorization')),
                ],
            ]
        ]);
    });

    Route::get('/auth/status', function (Request $request) {
        return response()->json([
            'authenticated' => Auth::check(),
            'user_id' => Auth::id(),
        ]);
    });

    Route::get('/auth/debug-token', function (Request $request) {
        return response()->json([
            'bearer_token' => $request->bearerToken(),
            'has_bearer_token' => !empty($request->bearerToken()),
            'auth_header' => $request->header('Authorization'),
            'all_headers' => $request->headers->all(),
            'user_agent' => $request->userAgent(),
            'ip' => $request->ip(),
        ]);
    });
});

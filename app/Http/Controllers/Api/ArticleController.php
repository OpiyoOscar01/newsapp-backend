<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateArticleRequest;
use App\Http\Requests\UpdateArticleRequest;
use App\Http\Resources\ArticleResource;
use App\Http\Resources\ArticleCollection;
use App\Http\Traits\ApiResponseTrait;
use App\Services\ArticleService;
use App\Services\MediaStackService;
use App\Models\Article;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class ArticleController extends Controller
{
    use AuthorizesRequests, ApiResponseTrait;

    public function __construct(
        private ArticleService $articleService,
        private MediaStackService $mediaStackService
    ) {
        // Apply authorization middleware selectively
        // $this->middleware('can:view,article')->only(['show']);
        // $this->middleware('can:update,article')->only(['update', 'feature', 'unfeature', 'activate', 'deactivate']);
        // $this->middleware('can:delete,article')->only(['destroy']);
    }

    /**
     * Display a listing of articles with enhanced filtering
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category' => 'nullable|string|exists:categories,slug',
            'source' => 'nullable|string|exists:sources,slug',
            'country' => 'nullable|string|size:2',
            'language' => 'nullable|string|size:2',
            'search' => 'nullable|string|min:2|max:100',
            'featured' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'sort_by' => 'nullable|in:published_at,view_count,created_at',
            'sort_order' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        try {
            $filters = array_filter($validated, fn($value) => !is_null($value));
            $perPage = $validated['per_page'] ?? 15;

            $articles = $this->articleService->getPaginated($perPage, $filters);

            return $this->successResponse(
                new ArticleCollection($articles),
                'Articles retrieved successfully',
                200,
                [
                    'total' => $articles->total(),
                    'per_page' => $articles->perPage(),
                    'current_page' => $articles->currentPage(),
                    'last_page' => $articles->lastPage(),
                ]
            );

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve articles',
                500,
                $e->getMessage()
            );
        }
    }

    /**
     * Store a newly created article
     */
    public function store(CreateArticleRequest $request): JsonResponse
    {
        try {
            $article = $this->articleService->create($request->validated());

            return $this->successResponse(
                new ArticleResource($article->load(['sourceModel', 'categoryModel'])),
                'Article created successfully',
                201
            );

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to create article',
                500,
                $e->getMessage()
            );
        }
    }

    /**
     * Display the specified article with full details
     */
  // app/Http/Controllers/Api/ArticleController.php

public function show($id): JsonResponse
{
    try {
        // Find the article by ID
        $article = Article::with([
            'sourceModel', 
            'categoryModel', 
            'articleKeywords',
            'interactions' => function ($query) {
                $query->latest()->limit(10);
            }
        ])->find($id);

        if (!$article) {
            return $this->errorResponse(
                'Article not found',
                404
            );
        }
        
        return $this->successResponse(
            new ArticleResource($article),
            'Article retrieved successfully'
        );

    } catch (\Exception $e) {
        return $this->errorResponse(
            'Article not found',
            404,
            $e->getMessage()
        );
    }
}

    /**
     * Update the specified article
     */
    public function update(UpdateArticleRequest $request, Article $article): JsonResponse
    {
        try {
            $updatedArticle = $this->articleService->update($article, $request->validated());

            return $this->successResponse(
                new ArticleResource($updatedArticle->load(['sourceModel', 'categoryModel'])),
                'Article updated successfully'
            );

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to update article',
                500,
                $e->getMessage()
            );
        }
    }

    /**
     * Remove the specified article
     */
    public function destroy(Article $article): JsonResponse
    {
        try {
            $this->articleService->delete($article);

            return $this->successResponse(
                null,
                'Article deleted successfully'
            );

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to delete article',
                500,
                $e->getMessage()
            );
        }
    }

    /**
     * Feature the specified article
     */
    public function feature(Article $article): JsonResponse
    {
        try {            
            $updatedArticle = $this->articleService->feature($article);

            return $this->successResponse(
                new ArticleResource($updatedArticle),
                'Article featured successfully'
            );

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to feature article',
                500,
                $e->getMessage()
            );
        }
    }

    /**
     * Unfeature the specified article
     */
    public function unfeature(Article $article): JsonResponse
    {
        try {            
            $updatedArticle = $this->articleService->unfeature($article);

            return $this->successResponse(
                new ArticleResource($updatedArticle),
                'Article unfeatured successfully'
            );

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to unfeature article',
                500,
                $e->getMessage()
            );
        }
    }

    /**
     * Activate the specified article
     */
    public function activate(Article $article): JsonResponse
    {
        try {            
            $updatedArticle = $this->articleService->activate($article);

            return $this->successResponse(
                new ArticleResource($updatedArticle),
                'Article activated successfully'
            );

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to activate article',
                500,
                $e->getMessage()
            );
        }
    }

    /**
     * Deactivate the specified article
     */
    public function deactivate(Article $article): JsonResponse
    {
        try {            
            $updatedArticle = $this->articleService->deactivate($article);

            return $this->successResponse(
                new ArticleResource($updatedArticle),
                'Article deactivated successfully'
            );

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to deactivate article',
                500,
                $e->getMessage()
            );
        }
    }

    /**
     * Record article view with analytics
     */
    public function recordView(Request $request, Article $article): JsonResponse
    {
        $validated = $request->validate([
            'referrer' => 'nullable|url',
            'user_agent' => 'nullable|string',
            'session_id' => 'nullable|string',
            'read_time' => 'nullable|integer|min:0',
        ]);

        try {
            $this->articleService->recordView($article, $validated);

            return $this->successResponse(
                [
                    'view_count' => $article->fresh()->view_count,
                    'message' => 'View recorded successfully'
                ],
                'View recorded successfully'
            );

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to record view',
                500,
                $e->getMessage()
            );
        }
    }

    /**
     * Get related articles using advanced algorithms
     */
    public function related(Article $article): JsonResponse
    {
        try {
            $relatedArticles = $this->articleService->getRelated($article, 6);

            return $this->successResponse(
                ArticleResource::collection($relatedArticles),
                'Related articles retrieved successfully'
            );

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve related articles',
                500,
                $e->getMessage()
            );
        }
    }

    /**
     * Sync fresh content from MediaStack
     */
    public function syncFromMediaStack(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'categories' => 'nullable|string',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        try {
            $result = $this->mediaStackService->fetchNews($validated);

            return $this->successResponse(
                $result,
                'Articles synced from MediaStack successfully'
            );

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to sync articles from MediaStack',
                500,
                $e->getMessage()
            );
        }
    }

    /**
     * Get article analytics
     */
    public function analytics(Article $article): JsonResponse
    {
        try {
            $analytics = [
                'views' => [
                    'total' => $article->view_count,
                    'today' => $article->interactions()
                        ->where('interaction_type', 'view')
                        ->whereDate('created_at', today())
                        ->count(),
                    'this_week' => $article->interactions()
                        ->where('interaction_type', 'view')
                        ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
                        ->count(),
                ],
                'engagement' => [
                    'shares' => $article->interactions()
                        ->where('interaction_type', 'share')
                        ->count(),
                    'likes' => $article->interactions()
                        ->where('interaction_type', 'like')
                        ->count(),
                    'comments' => $article->interactions()
                        ->where('interaction_type', 'comment')
                        ->count(),
                ],
                'performance' => [
                    'reading_time' => $article->reading_time,
                    'is_trending' => $article->interactions()
                        ->where('created_at', '>=', now()->subDay())
                        ->count() > 100,
                ],
            ];

            return $this->successResponse(
                $analytics,
                'Article analytics retrieved successfully'
            );

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve article analytics',
                500,
                $e->getMessage()
            );
        }
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Services\ArticleService;
use App\Http\Resources\ArticleResource;
use App\Http\Resources\ArticleCollection;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class NewsController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private ArticleService $articleService
    ) {}

    /**
     * Get latest news for frontend
     */
    public function latest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:50',
            'category' => 'nullable|string',
            'exclude' => 'nullable|array',
            'exclude.*' => 'integer|exists:articles,id',
        ]);

        try {
            $limit = $validated['limit'] ?? 20;
            $filters = [];
            
            if (isset($validated['category'])) {
                $filters['category'] = $validated['category'];
            }

            $articles = $this->articleService->getLatest($limit, $filters);

            // Exclude specified articles
            if (isset($validated['exclude'])) {
                $articles = $articles->whereNotIn('id', $validated['exclude']);
            }

            return $this->successResponse(
                ArticleResource::collection($articles),
                'Latest news retrieved successfully'
            );

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve latest news',
                500,
                $e->getMessage()
            );
        }
    }

    /**
     * Get trending news
     */
    public function trending(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:50',
            'timeframe' => 'nullable|in:1,3,7,30', // days
        ]);

        try {
            $limit = $validated['limit'] ?? 20;
            $timeframe = $validated['timeframe'] ?? 7;
            
            $articles = $this->articleService->getTrending($limit, $timeframe);

            return $this->successResponse(
                ArticleResource::collection($articles),
                'Trending news retrieved successfully'
            );

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve trending news',
                500,
                $e->getMessage()
            );
        }
    }

    /**
     * Get featured news
     */
    public function featured(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:20',
            'category' => 'nullable|string',
        ]);

        try {
            $limit = $validated['limit'] ?? 10;
            $articles = $this->articleService->getFeatured($limit);

            if (isset($validated['category'])) {
                $articles = $articles->where('category', $validated['category']);
            }

            return $this->successResponse(
                ArticleResource::collection($articles),
                'Featured news retrieved successfully'
            );

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve featured news',
                500,
                $e->getMessage()
            );
        }
    }

    /**
     * Get news by category
     */
    public function byCategory(Request $request, string $category): JsonResponse
    {
        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:50',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        try {
            if (isset($validated['per_page'])) {
                // Paginated response
                $perPage = $validated['per_page'];
                $articles = $this->articleService->getPaginated($perPage, ['category' => $category]);
                
                return $this->successResponse(
                    new ArticleCollection($articles),
                    "News for category '{$category}' retrieved successfully"
                );
            } else {
                // Simple collection response
                $limit = $validated['limit'] ?? 20;
                $articles = $this->articleService->getByCategory($category, $limit);
                
                return $this->successResponse(
                    ArticleResource::collection($articles),
                    "News for category '{$category}' retrieved successfully"
                );
            }

        } catch (\Exception $e) {
            return $this->errorResponse(
                "Failed to retrieve news for category '{$category}'",
                500,
                $e->getMessage()
            );
        }
    }

    /**
     * Get news by source
     */
    public function bySource(Request $request, string $source): JsonResponse
    {
        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:50',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        try {
            if (isset($validated['per_page'])) {
                // Paginated response
                $perPage = $validated['per_page'];
                $articles = $this->articleService->getPaginated($perPage, ['source' => $source]);
                
                return $this->successResponse(
                    new ArticleCollection($articles),
                    "News from source '{$source}' retrieved successfully"
                );
            } else {
                // Simple collection response
                $limit = $validated['limit'] ?? 20;
                $articles = $this->articleService->getBySource($source, $limit);
                
                return $this->successResponse(
                    ArticleResource::collection($articles),
                    "News from source '{$source}' retrieved successfully"
                );
            }

        } catch (\Exception $e) {
            return $this->errorResponse(
                "Failed to retrieve news from source '{$source}'",
                500,
                $e->getMessage()
            );
        }
    }

    /**
     * Search news
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => 'required|string|min:2|max:100',
            'category' => 'nullable|string',
            'source' => 'nullable|string',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'per_page' => 'nullable|integer|min:1|max:50',
            'sort_by' => 'nullable|in:relevance,date,popularity',
        ]);

        try {
            $filters = array_merge(
                ['search' => $validated['q']],
                array_filter($validated, fn($key) => in_array($key, ['category', 'source', 'date_from', 'date_to']), ARRAY_FILTER_USE_KEY)
            );

            $perPage = $validated['per_page'] ?? 20;
            $articles = $this->articleService->getPaginated($perPage, $filters);

            return $this->successResponse(
                new ArticleCollection($articles),
                "Search results for '{$validated['q']}' retrieved successfully",
                200
            );

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Search failed',
                500,
                $e->getMessage()
            );
        }
    }

    /**
     * Get categorized news for homepage
     */
    public function categorizedNews(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'categories' => 'nullable|array',
            'categories.*' => 'string',
            'limit_per_category' => 'nullable|integer|min:1|max:10',
        ]);

        try {
            $categories = $validated['categories'] ?? ['general', 'business', 'technology', 'sports', 'health'];
            $limitPerCategory = $validated['limit_per_category'] ?? 4;
            
            $categorizedNews = [];
            
            foreach ($categories as $category) {
                $articles = $this->articleService->getByCategory($category, $limitPerCategory);
                $categorizedNews[] = [
                    'name' => ucfirst($category),
                    'slug' => $category,
                    'articles' => ArticleResource::collection($articles),
                ];
            }

            return $this->successResponse(
                $categorizedNews,
                'Categorized news retrieved successfully'
            );

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve categorized news',
                500,
                $e->getMessage()
            );
        }
    }
}

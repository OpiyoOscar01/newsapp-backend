<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateArticleRequest;
use App\Http\Requests\UpdateArticleRequest;
use App\Http\Resources\ArticleResource;
use App\Http\Resources\ArticleCollection;
use App\Http\Traits\ApiResponseTrait;
use App\Services\ArticleService;
use App\Models\Article;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class ArticleController extends Controller
{
    use AuthorizesRequests;
    use ApiResponseTrait;

    /**
     * Create a new controller instance.
     */
    public function __construct(
        private ArticleService $articleService
    ) {
        $this->authorizeResource(Article::class, 'article');
    }

    /**
     * Display a listing of articles.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'category', 'source', 'country', 'language', 
                'search', 'featured', 'is_active'
            ]);
            $perPage = $request->get('per_page', 15);

            $articles = $this->articleService->getPaginated($perPage, $filters);

            return $this->successResponse(
                new ArticleCollection($articles),
                'Articles retrieved successfully'
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
     * Store a newly created article.
     */
    public function store(CreateArticleRequest $request): JsonResponse
    {
        try {
            $article = $this->articleService->create($request->validated());

            return $this->successResponse(
                new ArticleResource($article),
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
     * Display the specified article.
     */
    public function show(Article $article): JsonResponse
    {
        try {
            // Load relationships for detailed view
            $article->load(['categoryModel', 'sourceModel', 'articleKeywords']);
            
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
     * Update the specified article.
     */
    public function update(UpdateArticleRequest $request, Article $article): JsonResponse
    {
        try {
            $updatedArticle = $this->articleService->update($article, $request->validated());

            return $this->successResponse(
                new ArticleResource($updatedArticle),
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
     * Remove the specified article.
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
     * Feature the specified article.
     */
    public function feature(Article $article): JsonResponse
    {
        try {
            $this->authorize('update', $article);
            
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
     * Unfeature the specified article.
     */
    public function unfeature(Article $article): JsonResponse
    {
        try {
            $this->authorize('update', $article);
            
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
     * Activate the specified article.
     */
    public function activate(Article $article): JsonResponse
    {
        try {
            $this->authorize('update', $article);
            
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
     * Deactivate the specified article.
     */
    public function deactivate(Article $article): JsonResponse
    {
        try {
            $this->authorize('update', $article);
            
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
     * Record article view.
     */
    public function recordView(Request $request, Article $article): JsonResponse
    {
        try {
            $this->articleService->recordView($article, $request->all());

            return $this->successResponse(
                ['view_count' => $article->fresh()->view_count],
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
     * Get related articles.
     */
    public function related(Article $article): JsonResponse
    {
        try {
            $relatedArticles = $this->articleService->getRelated($article);

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
}
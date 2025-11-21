<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MediaStackService;
use App\Http\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class MediaStackController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private MediaStackService $mediaStackService
    ) {}

    /**
     * Fetch news from MediaStack API
     */
    public function fetchNews(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'categories' => 'nullable|string',
            'sources' => 'nullable|string',
            'countries' => 'nullable|string',
            'languages' => 'nullable|string',
            'keywords' => 'nullable|string',
            'date' => 'nullable|date',
            'sort' => ['nullable', Rule::in(['published_desc', 'published_asc', 'popularity'])],
            'limit' => 'nullable|integer|min:1|max:100',
            'offset' => 'nullable|integer|min:0',
        ]);

        try {
            $result = $this->mediaStackService->fetchNews($validated);

            return $this->successResponse($result, 'News fetched successfully');

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to fetch news from MediaStack',
                500,
                $e->getMessage()
            );
        }
    }

    /**
     * Get API status and health check
     */
    public function apiStatus(): JsonResponse
    {
        try {
            $status = $this->mediaStackService->testConnection();
            
            return $this->successResponse($status, 'API status retrieved');

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to check API status',
                500,
                $e->getMessage()
            );
        }
    }

    /**
     * Test API connection
     */
    public function testConnection(): JsonResponse
    {
        try {
            $result = $this->mediaStackService->testConnection();

            if ($result['success']) {
                return $this->successResponse($result, 'Connection test successful');
            }

            return $this->errorResponse(
                'Connection test failed',
                400,
                $result['message']
            );

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Connection test error',
                500,
                $e->getMessage()
            );
        }
    }

    /**
     * Get API usage statistics
     */
    public function usageStats(): JsonResponse
    {
        try {
            $stats = $this->mediaStackService->getUsageStats();

            return $this->successResponse($stats, 'Usage statistics retrieved');

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve usage statistics',
                500,
                $e->getMessage()
            );
        }
    }

    /**
     * Fetch latest news
     */
    public function fetchLatest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:100',
            'categories' => 'nullable|string',
        ]);

        try {
            $params = array_merge($validated, [
                'sort' => 'published_desc',
            ]);

            $result = $this->mediaStackService->fetchNews($params);

            return $this->successResponse($result, 'Latest news fetched successfully');

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to fetch latest news',
                500,
                $e->getMessage()
            );
        }
    }

    /**
     * Fetch news by category
     */
    public function fetchByCategory(Request $request, string $category): JsonResponse
    {
        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:100',
            'sources' => 'nullable|string',
        ]);

        try {
            $params = array_merge($validated, [
                'categories' => $category,
                'sort' => 'published_desc',
            ]);

            $result = $this->mediaStackService->fetchNews($params);

            return $this->successResponse($result, "News for category '{$category}' fetched successfully");

        } catch (\Exception $e) {
            return $this->errorResponse(
                "Failed to fetch news for category '{$category}'",
                500,
                $e->getMessage()
            );
        }
    }
}

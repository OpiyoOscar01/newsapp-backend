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
            'force_refresh' => 'nullable|boolean', // Add option to bypass date filtering
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
     * Fetch paginated news with automatic offset handling
     */
    public function fetchPaginated(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1|max:100',
            'categories' => 'nullable|string',
            'sources' => 'nullable|string',
            'countries' => 'nullable|string',
            'languages' => 'nullable|string',
            'date_from' => 'nullable|date', // Add date range support
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        // Calculate offset from page
        $limit = $validated['limit'] ?? 100;
        $page = $validated['page'] ?? 1;
        $offset = ($page - 1) * $limit;

        $params = array_merge($validated, [
            'limit' => $limit,
            'offset' => $offset,
        ]);

        try {
            $result = $this->mediaStackService->fetchNews($params);

            // Add pagination metadata
            $result['pagination'] = array_merge($result['pagination'] ?? [], [
                'current_page' => $page,
                'per_page' => $limit,
                'next_page' => $page + 1,
                'prev_page' => $page > 1 ? $page - 1 : null,
                'total_pages' => isset($result['pagination']['total']) 
                    ? ceil($result['pagination']['total'] / $limit) 
                    : null,
            ]);

            return $this->successResponse($result, 'Paginated news fetched successfully');

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to fetch paginated news',
                500,
                $e->getMessage()
            );
        }
    }

    /**
     * Fetch latest news with proper pagination
     */
    public function fetchLatest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
            'categories' => 'nullable|string',
            'sources' => 'nullable|string',
            'countries' => 'nullable|string',
        ]);

        $limit = $validated['limit'] ?? 100;
        $page = $validated['page'] ?? 1;
        $offset = ($page - 1) * $limit;

        $params = array_merge($validated, [
            'sort' => 'published_desc',
            'limit' => $limit,
            'offset' => $offset,
        ]);

        try {
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
     * Fetch news by category with pagination
     */
    public function fetchByCategory(Request $request, string $category): JsonResponse
    {
        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
            'sources' => 'nullable|string',
            'countries' => 'nullable|string',
        ]);

        $limit = $validated['limit'] ?? 100;
        $page = $validated['page'] ?? 1;
        $offset = ($page - 1) * $limit;

        $params = array_merge($validated, [
            'categories' => $category,
            'sort' => 'published_desc',
            'limit' => $limit,
            'offset' => $offset,
        ]);

        try {
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

    /**
     * Fetch news by date range (for backfilling)
     */
    public function fetchByDateRange(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'categories' => 'nullable|string',
            'sources' => 'nullable|string',
            'countries' => 'nullable|string',
            'limit' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        $limit = $validated['limit'] ?? 100;
        $page = $validated['page'] ?? 1;
        $offset = ($page - 1) * $limit;

        $params = array_merge($validated, [
            'limit' => $limit,
            'offset' => $offset,
            'date' => $validated['from_date'],
            'date_to' => $validated['to_date'],
            'date_search' => 'from',
        ]);

        try {
            $result = $this->mediaStackService->fetchNews($params);

            return $this->successResponse($result, 'News by date range fetched successfully');

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to fetch news by date range',
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
     * Get database statistics
     */
    public function dbStats(): JsonResponse
    {
        try {
            $stats = $this->mediaStackService->getDatabaseStats();

            return $this->successResponse($stats, 'Database statistics retrieved');

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve database statistics',
                500,
                $e->getMessage()
            );
        }
    }

    /**
     * Reset fetch tracker (for testing)
     */
    public function resetTracker(): JsonResponse
    {
        try {
            $this->mediaStackService->resetFetchTracker();

            return $this->successResponse(null, 'Fetch tracker reset successfully');

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to reset fetch tracker',
                500,
                $e->getMessage()
            );
        }
    }
}
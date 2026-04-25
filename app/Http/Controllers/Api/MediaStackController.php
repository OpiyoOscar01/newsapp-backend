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

    // -------------------------------------------------------------------------
    // Core fetch endpoints
    // -------------------------------------------------------------------------

    /**
     * General-purpose fetch.
     *
     * Offset is managed automatically by the service via a Cache bookmark.
     * Callers may pass `force_refresh=true` to ignore the bookmark and start
     * from a specific `offset` (or 0 if omitted).
     */
    public function fetchNews(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'categories'    => 'nullable|string',
            'sources'       => 'nullable|string',
            'countries'     => 'nullable|string',
            'languages'     => 'nullable|string',
            'keywords'      => 'nullable|string',
            'date'          => 'nullable|date',
            'sort'          => ['nullable', Rule::in(['published_desc', 'published_asc', 'popularity'])],
            'limit'         => 'nullable|integer|min:1|max:100',
            'offset'        => 'nullable|integer|min:0',
            'force_refresh' => 'nullable|boolean',
        ]);

        try {
            $result = $this->mediaStackService->fetchNews($validated);

            return $this->successResponse($result, 'News fetched successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to fetch news from MediaStack', 500, $e->getMessage());
        }
    }

    /**
     * Paginated fetch where the *caller* controls the page number.
     *
     * Unlike the automatic bookmark approach in fetchNews(), here the caller
     * explicitly provides a page, so the service computes the offset directly
     * (force_refresh is implied — the bookmark is bypassed).
     */
    public function fetchPaginated(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page'        => 'nullable|integer|min:1',
            'limit'       => 'nullable|integer|min:1|max:100',
            'categories'  => 'nullable|string',
            'sources'     => 'nullable|string',
            'countries'   => 'nullable|string',
            'languages'   => 'nullable|string',
            'date_from'   => 'nullable|date',
            'date_to'     => 'nullable|date|after_or_equal:date_from',
        ]);

        $limit  = $validated['limit'] ?? 100;
        $page   = $validated['page']  ?? 1;
        $offset = ($page - 1) * $limit;

        try {
            // Pass explicit offset so the service bypasses the cache bookmark
            $result = $this->mediaStackService->fetchNews(array_merge($validated, [
                'limit'         => $limit,
                'offset'        => $offset,
                'force_refresh' => true,
            ]));

            $total       = $result['pagination']['total'] ?? null;
            $totalPages  = $total ? (int) ceil($total / $limit) : null;

            $result['pagination'] = array_merge($result['pagination'] ?? [], [
                'current_page' => $page,
                'per_page'     => $limit,
                'next_page'    => $page + 1,
                'prev_page'    => $page > 1 ? $page - 1 : null,
                'total_pages'  => $totalPages,
            ]);

            return $this->successResponse($result, 'Paginated news fetched successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to fetch paginated news', 500, $e->getMessage());
        }
    }

    /**
     * Fetch all remaining pages for a filter set in one request.
     *
     * Useful for scheduled backfill commands. Capped at $maxPages to avoid
     * runaway execution — increase via the `max_pages` param as needed.
     */
    public function fetchAllPages(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'categories'  => 'nullable|string',
            'sources'     => 'nullable|string',
            'countries'   => 'nullable|string',
            'languages'   => 'nullable|string',
            'limit'       => 'nullable|integer|min:1|max:100',
            'max_pages'   => 'nullable|integer|min:1|max:50',
        ]);

        $maxPages = $validated['max_pages'] ?? 10;
        unset($validated['max_pages']);

        try {
            $result = $this->mediaStackService->fetchAllPages($validated, $maxPages);

            return $this->successResponse($result, 'All pages fetched successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to fetch all pages', 500, $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // Convenience endpoints
    // -------------------------------------------------------------------------

    /**
     * Fetch latest news sorted by publication date descending.
     * Offset advances automatically across calls (bookmark-based).
     */
    public function fetchLatest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit'      => 'nullable|integer|min:1|max:100',
            'categories' => 'nullable|string',
            'sources'    => 'nullable|string',
            'countries'  => 'nullable|string',
        ]);

        try {
            $result = $this->mediaStackService->fetchNews(array_merge($validated, [
                'sort' => 'published_desc',
            ]));

            return $this->successResponse($result, 'Latest news fetched successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to fetch latest news', 500, $e->getMessage());
        }
    }

    /**
     * Fetch news for a specific category.
     * Offset advances automatically per category (each category has its own bookmark).
     */
    public function fetchByCategory(Request $request, string $category): JsonResponse
    {
        $validated = $request->validate([
            'limit'     => 'nullable|integer|min:1|max:100',
            'sources'   => 'nullable|string',
            'countries' => 'nullable|string',
        ]);

        try {
            $result = $this->mediaStackService->fetchNews(array_merge($validated, [
                'categories' => $category,
                'sort'       => 'published_desc',
            ]));

            return $this->successResponse($result, "News for category '{$category}' fetched successfully");

        } catch (\Exception $e) {
            return $this->errorResponse("Failed to fetch news for category '{$category}'", 500, $e->getMessage());
        }
    }

    /**
     * Backfill news within an explicit date range.
     * Always uses explicit offsets — does not touch the cache bookmark.
     */
    public function fetchByDateRange(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_date'  => 'required|date',
            'to_date'    => 'required|date|after_or_equal:from_date',
            'categories' => 'nullable|string',
            'sources'    => 'nullable|string',
            'countries'  => 'nullable|string',
            'limit'      => 'nullable|integer|min:1|max:100',
            'page'       => 'nullable|integer|min:1',
        ]);

        $limit  = $validated['limit'] ?? 100;
        $page   = $validated['page']  ?? 1;
        $offset = ($page - 1) * $limit;

        try {
            $result = $this->mediaStackService->fetchNews([
                'categories'    => $validated['categories'] ?? null,
                'sources'       => $validated['sources']    ?? null,
                'countries'     => $validated['countries']  ?? null,
                'date_from'     => $validated['from_date'],
                'date_to'       => $validated['to_date'],
                'limit'         => $limit,
                'offset'        => $offset,
                'force_refresh' => true, // explicit offset — ignore bookmark
            ]);

            return $this->successResponse($result, 'News by date range fetched successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to fetch news by date range', 500, $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // Health & diagnostics
    // -------------------------------------------------------------------------

    public function apiStatus(): JsonResponse
    {
        try {
            $status = $this->mediaStackService->testConnection();

            return $this->successResponse($status, 'API status retrieved');

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to check API status', 500, $e->getMessage());
        }
    }

    public function testConnection(): JsonResponse
    {
        try {
            $result = $this->mediaStackService->testConnection();

            if ($result['success']) {
                return $this->successResponse($result, 'Connection test successful');
            }

            return $this->errorResponse('Connection test failed', 400, $result['message']);

        } catch (\Exception $e) {
            return $this->errorResponse('Connection test error', 500, $e->getMessage());
        }
    }

    public function usageStats(): JsonResponse
    {
        try {
            return $this->successResponse(
                $this->mediaStackService->getUsageStats(),
                'Usage statistics retrieved'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve usage statistics', 500, $e->getMessage());
        }
    }

    public function dbStats(): JsonResponse
    {
        try {
            return $this->successResponse(
                $this->mediaStackService->getDatabaseStats(),
                'Database statistics retrieved'
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve database statistics', 500, $e->getMessage());
        }
    }

    /**
     * Reset the offset bookmark for the default (or provided) filter set.
     * Call this when you want the next scheduled fetch to restart from offset 0.
     */
    public function resetTracker(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'categories' => 'nullable|string',
            'countries'  => 'nullable|string',
            'languages'  => 'nullable|string',
            'sources'    => 'nullable|string',
        ]);

        try {
            // Pass filters so only the matching bookmark is cleared.
            // Pass nothing to clear ALL bookmarks.
            $this->mediaStackService->resetFetchTracker($validated);

            return $this->successResponse(null, 'Fetch tracker reset successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to reset fetch tracker', 500, $e->getMessage());
        }
    }
}
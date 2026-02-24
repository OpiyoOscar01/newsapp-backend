<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VisitorLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class AnalyticsController extends Controller
{

    /**
     * Track visitor data
     */
    public function trackVisitor(Request $request)
    {
        $validated = $request->validate([
            'sessionId' => 'required|string',
            'uniqueVisitorId' => 'nullable|string',
            'page' => 'required|string',
            'pageType' => 'required|in:landing,category,article,other',
            'referrer' => 'nullable|string',
            'referrerType' => 'required|in:direct,search,social,external,internal',
            'userAgent' => 'nullable|string',
            'screenResolution' => 'nullable|string',
            'deviceType' => 'required|in:mobile,tablet,desktop',
            'location' => 'nullable|array',
            'location.country' => 'nullable|string',
            'location.city' => 'nullable|string',
            'location.timezone' => 'nullable|string',
            'categorySlug' => 'nullable|string',
            'articleId' => 'nullable|string',
            'additionalData' => 'nullable|array'
        ]);

        // Extract location data
        $location = $validated['location'] ?? [];
        
        // Create visitor log
        $visitorLog = VisitorLog::create([
            'session_id' => $validated['sessionId'],
            'unique_visitor_id' => $validated['uniqueVisitorId'] ?? null,
            'page' => $validated['page'],
            'page_type' => $validated['pageType'],
            'referrer' => $validated['referrer'] ?? null,
            'referrer_type' => $validated['referrerType'],
            'user_agent' => $validated['userAgent'] ?? null,
            'screen_resolution' => $validated['screenResolution'] ?? null,
            'device_type' => $validated['deviceType'],
            'country' => $location['country'] ?? null,
            'city' => $location['city'] ?? null,
            'timezone' => $location['timezone'] ?? 'UTC',
            'category_slug' => $validated['categorySlug'] ?? null,
            'article_id' => $validated['articleId'] ?? null,
            'ip_address' => $request->ip(),
            'additional_data' => $validated['additionalData'] ?? null,
        ]);

        // Clear relevant cache
        $this->clearAnalyticsCache();

        return response()->json([
            'success' => true,
            'message' => 'Visitor data tracked successfully',
            'data' => $visitorLog
        ], 201);
    }

    /**
     * Get visitor statistics
     */
    public function getVisitorStats(Request $request)
    {
        $days = $request->input('days', 7);
        $cacheKey = "visitor_stats_{$days}_days";
        
        // Return cached data if available
        if (Cache::has($cacheKey)) {
            return response()->json([
                'success' => true,
                'data' => Cache::get($cacheKey)
            ]);
        }

        // Filter data by date range
        $recentData = VisitorLog::dateRange($days)->get();

        // Calculate statistics
        $stats = [
            'totalVisits' => $recentData->count(),
            'uniqueVisitors' => $this->getUniqueVisitors($days),
            'pageViews' => $this->getPageViews($days),
            'referrerStats' => $this->getReferrerStats($days),
            'deviceStats' => $this->getDeviceStats($days),
            'topPages' => $this->getTopPages($days),
            'topCategories' => $this->getTopCategories($days),
            'topArticles' => $this->getTopArticles($days),
            'visitsByHour' => $this->getVisitsByHour($days),
            'visitsByDay' => $this->getVisitsByDay($days),
        ];

        // Cache for 5 minutes
        Cache::put($cacheKey, $stats, now()->addMinutes(5));

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Get real-time visitor count
     */
    public function getRealtimeVisitors()
    {
        $todayStart = now()->startOfDay();
        
        $visitors = VisitorLog::where('created_at', '>=', $todayStart)
            ->select(DB::raw('COUNT(*) as total, COUNT(DISTINCT session_id) as unique_visitors'))
            ->first();

        $activeNow = VisitorLog::where('created_at', '>=', now()->subMinutes(5))
            ->distinct('session_id')
            ->count('session_id');

        return response()->json([
            'success' => true,
            'data' => [
                'total_today' => $visitors->total ?? 0,
                'unique_today' => $visitors->unique_visitors ?? 0,
                'active_now' => $activeNow
            ]
        ]);
    }

    /**
     * Export visitor data
     */
    public function exportVisitorData(Request $request)
    {
        $days = $request->input('days', 30);
        
        $data = VisitorLog::dateRange($days)
            ->orderBy('created_at', 'desc')
            ->get();

        $stats = Cache::get("visitor_stats_{$days}_days") ?? $this->getVisitorStats($request)->getData()->data;

        return response()->json([
            'success' => true,
            'data' => [
                'raw_data' => $data,
                'stats' => $stats,
                'exported_at' => now()->toISOString(),
                'time_range' => $days . ' days',
                'total_records' => $data->count()
            ]
        ]);
    }

    /**
     * Helper methods for statistics calculation
     */
    private function getUniqueVisitors($days): int
    {
        return VisitorLog::dateRange($days)
            ->distinct('session_id')
            ->count('session_id');
    }

    private function getPageViews($days): array
    {
        return [
            'landing' => VisitorLog::dateRange($days)->pageType('landing')->count(),
            'category' => VisitorLog::dateRange($days)->pageType('category')->count(),
            'article' => VisitorLog::dateRange($days)->pageType('article')->count(),
            'other' => VisitorLog::dateRange($days)->pageType('other')->count(),
        ];
    }

    private function getReferrerStats($days): array
    {
        return [
            'direct' => VisitorLog::dateRange($days)->referrerType('direct')->count(),
            'search' => VisitorLog::dateRange($days)->referrerType('search')->count(),
            'social' => VisitorLog::dateRange($days)->referrerType('social')->count(),
            'external' => VisitorLog::dateRange($days)->referrerType('external')->count(),
            'internal' => VisitorLog::dateRange($days)->referrerType('internal')->count(),
        ];
    }

    private function getDeviceStats($days): array
    {
        return [
            'mobile' => VisitorLog::dateRange($days)->deviceType('mobile')->count(),
            'tablet' => VisitorLog::dateRange($days)->deviceType('tablet')->count(),
            'desktop' => VisitorLog::dateRange($days)->deviceType('desktop')->count(),
        ];
    }

    private function getTopPages($days, $limit = 10): array
    {
        return VisitorLog::dateRange($days)
            ->select('page', DB::raw('COUNT(*) as views'))
            ->groupBy('page')
            ->orderByDesc('views')
            ->limit($limit)
            ->get()
            ->map(fn($item) => ['page' => $item->page, 'views' => $item->views])
            ->toArray();
    }

    private function getTopCategories($days, $limit = 10): array
    {
        return VisitorLog::dateRange($days)
            ->whereNotNull('category_slug')
            ->select('category_slug as category', DB::raw('COUNT(*) as views'))
            ->groupBy('category_slug')
            ->orderByDesc('views')
            ->limit($limit)
            ->get()
            ->map(fn($item) => ['category' => $item->category, 'views' => $item->views])
            ->toArray();
    }

    private function getTopArticles($days, $limit = 10): array
    {
        return VisitorLog::dateRange($days)
            ->whereNotNull('article_id')
            ->select('article_id', DB::raw('COUNT(*) as views'))
            ->groupBy('article_id')
            ->orderByDesc('views')
            ->limit($limit)
            ->get()
            ->map(fn($item) => ['articleId' => $item->article_id, 'views' => $item->views])
            ->toArray();
    }

    private function getVisitsByHour($days): array
    {
        $hours = VisitorLog::dateRange($days)
            ->select(DB::raw('HOUR(created_at) as hour'), DB::raw('COUNT(*) as count'))
            ->groupBy(DB::raw('HOUR(created_at)'))
            ->orderBy('hour')
            ->get()
            ->pluck('count', 'hour')
            ->toArray();

        $result = [];
        for ($i = 0; $i < 24; $i++) {
            $result[$i] = $hours[$i] ?? 0;
        }

        return $result;
    }

    private function getVisitsByDay($days): array
    {
        return VisitorLog::dateRange($days)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get()
            ->pluck('count', 'date')
            ->toArray();
    }

    private function clearAnalyticsCache(): void
    {
        for ($i = 1; $i <= 365; $i++) {
            Cache::forget("visitor_stats_{$i}_days");
        }
    }
}
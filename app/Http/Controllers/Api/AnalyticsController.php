<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VisitorLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function trackVisitor(Request $request): JsonResponse
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
            'additionalData' => 'nullable|array',
        ]);

        $location = $validated['location'] ?? [];

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

        $this->clearAnalyticsCache();

        return response()->json([
            'success' => true,
            'message' => 'Visitor data tracked successfully.',
            'data' => $visitorLog,
        ], 201);
    }

    public function getVisitorStats(Request $request): JsonResponse
    {
        $days = max(1, min((int) $request->input('days', 7), 365));
        $cacheKey = "visitor_stats_{$days}_days";

        $stats = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($days) {
            return [
                'totalVisits' => VisitorLog::dateRange($days)->count(),
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
        });

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    public function getRealtimeVisitors(): JsonResponse
    {
        $todayStart = now()->startOfDay();

        $visitors = VisitorLog::query()
            ->where('created_at', '>=', $todayStart)
            ->selectRaw('COUNT(*) as total_today')
            ->selectRaw("COUNT(DISTINCT COALESCE(NULLIF(unique_visitor_id, ''), session_id)) as unique_today")
            ->first();

        $activeNow = VisitorLog::query()
            ->where('created_at', '>=', now()->subMinutes(5))
            ->selectRaw("COUNT(DISTINCT COALESCE(NULLIF(unique_visitor_id, ''), session_id)) as active_now")
            ->value('active_now');

        return response()->json([
            'success' => true,
            'data' => [
                'total_today' => (int) ($visitors->total_today ?? 0),
                'unique_today' => (int) ($visitors->unique_today ?? 0),
                'active_now' => (int) ($activeNow ?? 0),
            ],
        ]);
    }

    public function getRecentVisitorEvents(Request $request): JsonResponse
    {
        $limit = max(1, min((int) $request->input('limit', 20), 100));

        $events = VisitorLog::query()
            ->latest('created_at')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $events,
        ]);
    }

    public function exportVisitorData(Request $request): JsonResponse
    {
        $days = max(1, min((int) $request->input('days', 30), 365));

        $data = VisitorLog::dateRange($days)
            ->orderByDesc('created_at')
            ->get();

        $stats = [
            'totalVisits' => VisitorLog::dateRange($days)->count(),
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

        return response()->json([
            'success' => true,
            'data' => [
                'raw_data' => $data,
                'stats' => $stats,
                'exported_at' => now()->toISOString(),
                'time_range' => "{$days} days",
                'total_records' => $data->count(),
            ],
        ]);
    }

    public function cleanupVisitorData(Request $request): JsonResponse
    {
        $days = max(1, min((int) $request->input('days', 30), 3650));
        $cutoff = now()->subDays($days);

        $deletedCount = VisitorLog::query()
            ->where('created_at', '<', $cutoff)
            ->delete();

        $this->clearAnalyticsCache();

        return response()->json([
            'success' => true,
            'message' => "Deleted {$deletedCount} visitor analytics records older than {$days} days.",
            'data' => [
                'deleted_count' => $deletedCount,
                'days' => $days,
            ],
        ]);
    }

    private function getUniqueVisitors(int $days): int
    {
        return (int) VisitorLog::dateRange($days)
            ->selectRaw("COUNT(DISTINCT COALESCE(NULLIF(unique_visitor_id, ''), session_id)) as aggregate")
            ->value('aggregate');
    }

    private function getPageViews(int $days): array
    {
        $rows = VisitorLog::dateRange($days)
            ->select('page_type', DB::raw('COUNT(*) as total'))
            ->groupBy('page_type')
            ->pluck('total', 'page_type');

        return [
            'landing' => (int) ($rows['landing'] ?? 0),
            'category' => (int) ($rows['category'] ?? 0),
            'article' => (int) ($rows['article'] ?? 0),
            'other' => (int) ($rows['other'] ?? 0),
        ];
    }

    private function getReferrerStats(int $days): array
    {
        $rows = VisitorLog::dateRange($days)
            ->select('referrer_type', DB::raw('COUNT(*) as total'))
            ->groupBy('referrer_type')
            ->pluck('total', 'referrer_type');

        return [
            'direct' => (int) ($rows['direct'] ?? 0),
            'search' => (int) ($rows['search'] ?? 0),
            'social' => (int) ($rows['social'] ?? 0),
            'external' => (int) ($rows['external'] ?? 0),
            'internal' => (int) ($rows['internal'] ?? 0),
        ];
    }

    private function getDeviceStats(int $days): array
    {
        $rows = VisitorLog::dateRange($days)
            ->select('device_type', DB::raw('COUNT(*) as total'))
            ->groupBy('device_type')
            ->pluck('total', 'device_type');

        return [
            'mobile' => (int) ($rows['mobile'] ?? 0),
            'tablet' => (int) ($rows['tablet'] ?? 0),
            'desktop' => (int) ($rows['desktop'] ?? 0),
        ];
    }

    private function getTopPages(int $days, int $limit = 10): array
    {
        return VisitorLog::dateRange($days)
            ->select('page', DB::raw('COUNT(*) as views'))
            ->groupBy('page')
            ->orderByDesc('views')
            ->limit($limit)
            ->get()
            ->map(fn ($item) => [
                'page' => $item->page,
                'views' => (int) $item->views,
            ])
            ->values()
            ->toArray();
    }

    private function getTopCategories(int $days, int $limit = 10): array
    {
        return VisitorLog::dateRange($days)
            ->whereNotNull('category_slug')
            ->where('category_slug', '!=', '')
            ->select('category_slug as category', DB::raw('COUNT(*) as views'))
            ->groupBy('category_slug')
            ->orderByDesc('views')
            ->limit($limit)
            ->get()
            ->map(fn ($item) => [
                'category' => $item->category,
                'views' => (int) $item->views,
            ])
            ->values()
            ->toArray();
    }

    private function getTopArticles(int $days, int $limit = 10): array
    {
        return VisitorLog::dateRange($days)
            ->whereNotNull('article_id')
            ->where('article_id', '!=', '')
            ->select('article_id', DB::raw('COUNT(*) as views'))
            ->groupBy('article_id')
            ->orderByDesc('views')
            ->limit($limit)
            ->get()
            ->map(fn ($item) => [
                'articleId' => (string) $item->article_id,
                'views' => (int) $item->views,
            ])
            ->values()
            ->toArray();
    }

    private function getVisitsByHour(int $days): array
    {
        $rows = VisitorLog::dateRange($days)
            ->selectRaw('HOUR(created_at) as hour')
            ->selectRaw('COUNT(*) as total')
            ->groupBy(DB::raw('HOUR(created_at)'))
            ->orderBy('hour')
            ->pluck('total', 'hour')
            ->toArray();

        $result = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $result[$hour] = (int) ($rows[$hour] ?? 0);
        }

        return $result;
    }

    private function getVisitsByDay(int $days): array
    {
        $rows = VisitorLog::dateRange($days)
            ->selectRaw('DATE(created_at) as date')
            ->selectRaw('COUNT(*) as total')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->pluck('total', 'date')
            ->toArray();

        $result = [];
        foreach ($rows as $date => $count) {
            $result[$date] = (int) $count;
        }

        return $result;
    }

    private function clearAnalyticsCache(): void
    {
        for ($days = 1; $days <= 365; $days++) {
            Cache::forget("visitor_stats_{$days}_days");
        }
    }
}

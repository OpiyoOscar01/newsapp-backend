<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Source;
use App\Models\Category;
use App\Models\ApiFetchLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Exception;

class MediaStackService
{
    private string $apiKey;
    private string $apiUrl;
    private array $defaultParams;
    private int $timeout;
    private array $retryConfig;

    /**
     * Cache key prefix for storing fetch progress (offset bookmarks).
     * Key format: mediastack_offset_{hash_of_filters}
     */
    private const OFFSET_CACHE_PREFIX = 'mediastack_offset_';

    /**
     * How long to remember an offset bookmark (seconds).
     * 24 hours — long enough to survive cron gaps, short enough to reset stale state.
     */
    private const OFFSET_TTL = 86400;

    public function __construct()
    {
        $this->apiKey        = config('mediastack.api_key');
        $this->apiUrl        = config('mediastack.api_url');
        $this->defaultParams = config('mediastack.default_params', [
            'limit'      => 100,
            'languages'  => 'en',
            'countries'  => 'us,gb,ca,au',
            'categories' => 'general,business,entertainment,health,science,sports,technology',
            'sort'       => 'published_desc',
        ]);
        $this->timeout     = config('mediastack.timeout', 60);
        $this->retryConfig = config('mediastack.retry', ['times' => 3, 'sleep' => 1]);
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Fetch news from MediaStack and persist new articles.
     *
     * Offset is managed automatically via a Cache bookmark keyed on the
     * canonical filter set, so repeated calls always advance forward rather
     * than re-fetching the same window.
     *
     * Pass `force_refresh => true` to ignore the bookmark and reset to 0.
     * Pass `offset => N`         to start at an explicit position (also resets bookmark).
     */
    public function fetchNews(array $params = []): array
    {
        $startTime    = microtime(true);
        $forceRefresh = (bool) ($params['force_refresh'] ?? false);

        // Remove internal-only keys before building the API query
        unset($params['force_refresh']);

        Log::info('MediaStack: initiating fetch', ['params' => $params]);

        $fetchLog = $this->createFetchLog($params);

        try {
            // ── 1. Resolve limit ────────────────────────────────────────────
            $limit = (int) ($params['limit'] ?? $this->defaultParams['limit'] ?? 100);

            // ── 2. Resolve offset ───────────────────────────────────────────
            // Priority: explicit param > cache bookmark > 0
            $cacheKey = $this->offsetCacheKey($params);

            if ($forceRefresh || isset($params['offset'])) {
                $offset = (int) ($params['offset'] ?? 0);
                Cache::put($cacheKey, $offset, self::OFFSET_TTL);
            } else {
                $offset = (int) (Cache::get($cacheKey, 0));
            }

            $params['offset'] = $offset;
            $params['limit']  = $limit;

            // ── 3. Build query params ───────────────────────────────────────
            $queryParams = array_merge($this->defaultParams, $params, [
                'access_key' => $this->apiKey,
            ]);

            // Strip nulls / empty strings
            $queryParams = array_filter($queryParams, fn($v) => !is_null($v) && $v !== '');

            // Normalise date parameters to what MediaStack expects
            $queryParams = $this->formatDateParameters($queryParams);

            // Remove keys that are not MediaStack API parameters
            $apiOnlyParams = $this->stripInternalKeys($queryParams);

            Log::info('MediaStack: sending request', [
                'offset' => $offset,
                'limit'  => $limit,
                'params' => $apiOnlyParams,
            ]);

            // ── 4. Call API ─────────────────────────────────────────────────
            $response = $this->makeApiRequest($apiOnlyParams);

            if (!$response->successful()) {
                throw new Exception("API request failed with status: {$response->status()}");
            }

            $data = $response->json();

            if (isset($data['error'])) {
                throw new Exception("MediaStack API Error: {$data['error']['message']}");
            }

            $rawArticles  = $data['data'] ?? [];
            $apiTotal     = (int) ($data['pagination']['total'] ?? 0);
            $receivedCount = count($rawArticles);

            // ── 5. Persist articles ─────────────────────────────────────────
            [$processedCount, $skippedCount] = $this->processArticles($rawArticles);

            // ── 6. Advance bookmark ─────────────────────────────────────────
            //
            // Only advance if MediaStack actually returned a full page.
            // If it returned fewer than $limit items we have likely reached
            // the end of available results — reset the bookmark to 0 so the
            // next scheduled run starts fresh (new articles will have arrived).
            //
            $nextOffset = $offset + $receivedCount;

            if ($receivedCount < $limit || $nextOffset >= $apiTotal) {
                // End of result set — reset for next run
                Cache::put($cacheKey, 0, self::OFFSET_TTL);
                $reachedEnd = true;
            } else {
                Cache::put($cacheKey, $nextOffset, self::OFFSET_TTL);
                $reachedEnd = false;
            }

            // ── 7. Log & return ─────────────────────────────────────────────
            $executionTime = microtime(true) - $startTime;

            $this->updateFetchLog($fetchLog, [
                'status'            => 'success',
                'articles_fetched'  => $receivedCount,
                'articles_processed'=> $processedCount,
                'duplicates_skipped'=> $skippedCount,
                'api_response'      => $data,
                'execution_time'    => $executionTime,
            ]);

            Log::info('MediaStack: fetch complete', [
                'offset_used'   => $offset,
                'next_offset'   => $reachedEnd ? 0 : $nextOffset,
                'received'      => $receivedCount,
                'processed'     => $processedCount,
                'skipped'       => $skippedCount,
                'api_total'     => $apiTotal,
                'reached_end'   => $reachedEnd,
                'execution_time'=> round($executionTime, 2),
            ]);

            return [
                'success'            => true,
                'articles_fetched'   => $receivedCount,
                'articles_processed' => $processedCount,
                'duplicates_skipped' => $skippedCount,
                'offset_used'        => $offset,
                'next_offset'        => $reachedEnd ? 0 : $nextOffset,
                'reached_end'        => $reachedEnd,
                'pagination'         => $data['pagination'] ?? null,
                'fetch_params'       => $params,
            ];

        } catch (Exception $e) {
            Log::error('MediaStack: fetch failed', [
                'error'  => $e->getMessage(),
                'params' => $params,
                'trace'  => $e->getTraceAsString(),
            ]);

            $this->updateFetchLog($fetchLog, [
                'status'         => 'failed',
                'error_message'  => $e->getMessage(),
                'execution_time' => microtime(true) - $startTime,
            ]);

            throw $e;
        }
    }

    /**
     * Fetch all pages for a given filter set in a single call.
     * Useful for backfill commands — not for scheduled fetches.
     */
    public function fetchAllPages(array $params = [], int $maxPages = 10): array
    {
        $aggregated = [
            'articles_fetched'   => 0,
            'articles_processed' => 0,
            'duplicates_skipped' => 0,
            'pages_fetched'      => 0,
        ];

        for ($page = 1; $page <= $maxPages; $page++) {
            $result = $this->fetchNews(array_merge($params, [
                'offset'        => ($page - 1) * ($params['limit'] ?? 100),
                'force_refresh' => true, // explicit offset overrides bookmark
            ]));

            $aggregated['articles_fetched']   += $result['articles_fetched'];
            $aggregated['articles_processed'] += $result['articles_processed'];
            $aggregated['duplicates_skipped'] += $result['duplicates_skipped'];
            $aggregated['pages_fetched']++;

            if ($result['reached_end']) {
                break;
            }
        }

        return $aggregated;
    }

    /**
     * Convenience wrapper used by MediaStackController::fetchPaginated().
     * Delegates entirely to fetchNews(); offset is auto-managed.
     */
    public function fetchNewsWithPagination(int $page = 1, int $limit = 100, array $filters = []): array
    {
        // When the caller provides an explicit page, honour it by computing
        // the offset directly (bypasses the cache bookmark).
        $offset = ($page - 1) * $limit;

        $result          = $this->fetchNews(array_merge($filters, [
            'limit'         => $limit,
            'offset'        => $offset,
            'force_refresh' => true,
        ]));
        $result['page']     = $page;
        $result['has_more'] = !$result['reached_end'];

        return $result;
    }

    // -------------------------------------------------------------------------
    // Offset bookmark helpers
    // -------------------------------------------------------------------------

    /**
     * Build a stable cache key from the filter set so that different
     * category/country/language combinations each get their own bookmark.
     */
    private function offsetCacheKey(array $params): string
    {
        // Only stable filter keys matter — not limit/offset/force_refresh
        $filterKeys = ['categories', 'countries', 'languages', 'sources', 'keywords', 'sort', 'date'];
        $fingerprint = [];

        foreach ($filterKeys as $key) {
            if (isset($params[$key])) {
                $fingerprint[$key] = $params[$key];
            }
        }

        ksort($fingerprint);

        return self::OFFSET_CACHE_PREFIX . md5(json_encode($fingerprint));
    }

    /**
     * Reset the offset bookmark for a given filter set (or all bookmarks).
     */
    public function resetFetchTracker(array $params = []): void
    {
        if (empty($params)) {
            // Flush all offset bookmarks — crude but safe for "reset everything"
            Cache::flush();
            Log::info('MediaStack: all offset bookmarks cleared');
        } else {
            $cacheKey = $this->offsetCacheKey($params);
            Cache::forget($cacheKey);
            Log::info('MediaStack: offset bookmark cleared', ['cache_key' => $cacheKey]);
        }
    }

    // -------------------------------------------------------------------------
    // Article processing
    // -------------------------------------------------------------------------

    /**
     * Bulk-insert new articles, skipping any whose URL already exists.
     *
     * Returns [processedCount, skippedCount].
     */
    private function processArticles(array $articles): array
    {
        if (empty($articles)) {
            return [0, 0];
        }

        // ── Batch-load existing URLs in one query ───────────────────────────
        $incomingUrls = array_values(array_filter(array_column($articles, 'url')));

        $existingUrls = Article::whereIn('url', $incomingUrls)
            ->pluck('url')
            ->flip() // url => index, O(1) lookup
            ->toArray();

        // ── Prepare rows for bulk insert ────────────────────────────────────
        $toInsert = [];
        $skipped  = 0;
        $now      = now()->toDateTimeString();

        foreach ($articles as $articleData) {
            $url = $articleData['url'] ?? null;

            if (!$url) {
                Log::warning('MediaStack: article missing URL, skipping', ['data' => $articleData]);
                $skipped++;
                continue;
            }

            if (isset($existingUrls[$url])) {
                $skipped++;
                continue;
            }

            // Mark as seen within this batch to handle dupes inside the same response
            $existingUrls[$url] = true;

            $toInsert[] = ['data' => $articleData];
        }

        if (empty($toInsert)) {
            return [0, $skipped];
        }

        // ── Generate unique slugs without per-row DB hits ───────────────────
        $slugs = $this->generateUniqueSlugs(array_column($toInsert, 'data'));

        // ── Build final rows ────────────────────────────────────────────────
        $rows = [];

        foreach ($toInsert as $i => $item) {
            $d = $item['data'];

            $rows[] = [
                'title'             => $d['title']       ?? 'Untitled',
                'description'       => $d['description'] ?? null,
                'content'           => $d['description'] ?? null,
                'author'            => $d['author']      ?? null,
                'url'               => $d['url'],
                'source'            => $d['source']      ?? 'Unknown',
                'image_url'         => $d['image']       ?? null,
                'category'          => $d['category']    ?? 'general',
                'language'          => $d['language']    ?? null,
                'country'           => $d['country']     ?? null,
                'published_at'      => isset($d['published_at'])
                                         ? Carbon::parse($d['published_at'])->toDateTimeString()
                                         : $now,
                'slug'              => $slugs[$i],
                'meta_description'  => Str::limit(strip_tags($d['description'] ?? ''), 160),
                'is_active'         => true,
                'is_featured'       => false,
                'view_count'        => 0,
                'processing_status' => 'pending',
                'created_at'        => $now,
                'updated_at'        => $now,
            ];
        }

        // ── Bulk insert, ignoring any last-second URL duplicates ────────────
        // insertOrIgnore skips rows that violate the unique index on `url`
        // without throwing an exception — safe for concurrent fetches.
        $inserted = 0;

        // Insert in chunks to avoid hitting DB placeholder limits
        foreach (array_chunk($rows, 50) as $chunk) {
            $inserted += DB::table('articles')->insertOrIgnore($chunk);
        }

        $skipped += (count($rows) - $inserted);

        Log::info('MediaStack: article processing complete', [
            'received'  => count($articles),
            'inserted'  => $inserted,
            'skipped'   => $skipped,
        ]);

        return [$inserted, $skipped];
    }

    /**
     * Generate unique slugs for a batch of articles in two DB queries.
     */
    private function generateUniqueSlugs(array $articles): array
    {
        $baseSlug = fn(string $title) => Str::slug($title) ?: 'article';

        $baseSlugs = array_map(fn($d) => $baseSlug($d['title'] ?? 'untitled'), $articles);

        // Find all existing slugs that could conflict
        $existing = Article::where(function ($q) use ($baseSlugs) {
            foreach ($baseSlugs as $slug) {
                $q->orWhere('slug', 'like', $slug . '%');
            }
        })->pluck('slug')->flip()->toArray();

        $slugs   = [];
        $used    = []; // tracks what we've assigned within this batch

        foreach ($baseSlugs as $base) {
            $candidate = $base;
            $counter   = 1;

            while (isset($existing[$candidate]) || isset($used[$candidate])) {
                $candidate = $base . '-' . $counter;
                $counter++;
            }

            $slugs[] = $candidate;
            $used[$candidate] = true;
        }

        return $slugs;
    }

    // -------------------------------------------------------------------------
    // Source / Category helpers
    // -------------------------------------------------------------------------

    /**
     * Resolve multiple source names in two queries (one select, one insert).
     *
     * @param  string[] $names
     * @return array<string, Source>  keyed by source name
     */
    private function bulkGetOrCreateSources(array $names): array
    {
        if (empty($names)) {
            return [];
        }

        $mediastackIds = array_map(
            fn($n) => strtolower(str_replace(' ', '_', $n)),
            $names
        );

        // Fetch existing
        $existing = Source::whereIn('mediastack_id', $mediastackIds)
            ->get()
            ->keyBy('name')
            ->toArray();

        $map = [];

        foreach ($names as $name) {
            if (isset($existing[$name])) {
                $map[$name] = (object) $existing[$name];
                continue;
            }

            // Create individually only for truly new sources (rare)
            $map[$name] = Source::firstOrCreate(
                ['mediastack_id' => strtolower(str_replace(' ', '_', $name))],
                [
                    'name'        => $name,
                    'slug'        => Str::slug($name),
                    'description' => "News source: {$name}",
                    'is_active'   => true,
                    'metadata'    => ['created_from_mediastack' => true],
                ]
            );
        }

        return $map;
    }

    /**
     * Resolve multiple category names in two queries.
     *
     * @param  string[] $names
     * @return array<string, Category>  keyed by category name
     */
    private function bulkGetOrCreateCategories(array $names): array
    {
        if (empty($names)) {
            return [];
        }

        $existing = Category::whereIn('name', $names)
            ->get()
            ->keyBy('name')
            ->toArray();

        $map = [];

        foreach ($names as $name) {
            if (isset($existing[$name])) {
                $map[$name] = (object) $existing[$name];
                continue;
            }

            $map[$name] = Category::firstOrCreate(
                ['name' => $name],
                [
                    'slug'        => Str::slug($name),
                    'description' => "News category: {$name}",
                    'is_active'   => true,
                    'metadata'    => ['created_from_mediastack' => true],
                ]
            );
        }

        return $map;
    }

    // -------------------------------------------------------------------------
    // HTTP
    // -------------------------------------------------------------------------

    private function makeApiRequest(array $params)
    {
        $maxAttempts   = $this->retryConfig['times'];
        $sleepSeconds  = (int) ($this->retryConfig['sleep'] ?? 1);
        $lastException = null;
        $response      = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $response = Http::timeout($this->timeout)
                    ->withOptions([
                        'verify'          => true,
                        'connect_timeout' => 30,
                        'read_timeout'    => 30,
                    ])
                    ->get($this->apiUrl, $params);

                if ($response->successful()) {
                    return $response;
                }

                if ($response->status() === 429) {
                    $retryAfter = (int) $response->header('Retry-After', 5);
                    Log::warning('MediaStack: rate limited', ['retry_after' => $retryAfter, 'attempt' => $attempt]);
                    sleep($retryAfter);
                } elseif ($response->status() >= 500) {
                    Log::warning('MediaStack: server error', ['status' => $response->status(), 'attempt' => $attempt]);
                    sleep($sleepSeconds);
                } else {
                    // 4xx client error — no point retrying
                    break;
                }

            } catch (Exception $e) {
                $lastException = $e;
                Log::warning('MediaStack: request attempt failed', [
                    'attempt' => $attempt,
                    'error'   => $e->getMessage(),
                ]);

                if ($attempt < $maxAttempts) {
                    sleep($sleepSeconds);
                }
            }
        }

        if ($lastException) {
            throw $lastException;
        }

        return $response;
    }

    // -------------------------------------------------------------------------
    // Parameter helpers
    // -------------------------------------------------------------------------

    /**
     * Normalise date-related parameters to what MediaStack expects.
     */
    private function formatDateParameters(array $params): array
    {
        if (isset($params['date']) && str_contains((string) $params['date'], ' ')) {
            $params['date'] = explode(' ', $params['date'])[0];
        }

        // Combine date_from + date_to into MediaStack's comma-separated format
        if (isset($params['date_from']) && isset($params['date_to'])) {
            $params['date'] = $params['date_from'] . ',' . $params['date_to'];
            unset($params['date_from'], $params['date_to']);
        } elseif (isset($params['date']) && isset($params['date_to'])) {
            $params['date'] = $params['date'] . ',' . $params['date_to'];
            unset($params['date_to']);
        }

        // Remove internal helper keys MediaStack doesn't know about
        unset($params['date_search'], $params['date_from']);

        return $params;
    }

    /**
     * Strip any keys that should never reach the MediaStack API.
     */
    private function stripInternalKeys(array $params): array
    {
        $internal = ['force_refresh', 'page'];

        return array_diff_key($params, array_flip($internal));
    }

    // -------------------------------------------------------------------------
    // Fetch log helpers
    // -------------------------------------------------------------------------

    private function createFetchLog(array $params): ApiFetchLog
    {
        return ApiFetchLog::create([
            'api_endpoint'   => $this->apiUrl . '?access_key=' . Str::mask($this->apiKey, '*', 1, -4),
            'request_params' => $params,
            'status'         => 'running',
            'started_at'     => now(),
        ]);
    }

    private function updateFetchLog(ApiFetchLog $log, array $data): void
    {
        $log->update(array_merge($data, ['finished_at' => now()]));
    }

    // -------------------------------------------------------------------------
    // Statistics & diagnostics
    // -------------------------------------------------------------------------

    public function getUsageStats(): array
    {
        $logs = ApiFetchLog::where('created_at', '>=', now()->subDays(30))
            ->selectRaw('
                DATE(created_at)          as date,
                COUNT(*)                  as requests,
                SUM(articles_fetched)     as total_articles,
                SUM(articles_processed)   as processed_articles,
                SUM(duplicates_skipped)   as duplicates_skipped,
                AVG(execution_time)       as avg_execution_time
            ')
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get();

        return [
            'daily_stats' => $logs,
            'summary' => [
                'total_requests'           => $logs->sum('requests'),
                'total_articles_fetched'   => $logs->sum('total_articles'),
                'total_articles_processed' => $logs->sum('processed_articles'),
                'total_duplicates_skipped' => $logs->sum('duplicates_skipped'),
                'avg_execution_time'       => round($logs->avg('avg_execution_time'), 2),
            ],
        ];
    }

    public function getDatabaseStats(): array
    {
        return [
            'total_articles'       => Article::count(),
            'articles_by_category' => Article::selectRaw('category, count(*) as count')
                                        ->groupBy('category')
                                        ->orderBy('count', 'desc')
                                        ->get(),
            'articles_by_source'   => Article::selectRaw('source, count(*) as count')
                                        ->groupBy('source')
                                        ->orderBy('count', 'desc')
                                        ->limit(10)
                                        ->get(),
            'latest_article_date'  => Article::max('published_at'),
            'oldest_article_date'  => Article::min('published_at'),
            'articles_today'       => Article::whereDate('created_at', today())->count(),
            'date_range_coverage'  => [
                'from'       => Article::min('published_at'),
                'to'         => Article::max('published_at'),
                'total_days' => Article::selectRaw('DATEDIFF(MAX(published_at), MIN(published_at)) as days')->value('days'),
            ],
        ];
    }

    public function testConnection(): array
    {
        try {
            $response = Http::timeout(30)
                ->withOptions(['verify' => true, 'connect_timeout' => 15])
                ->get($this->apiUrl, [
                    'access_key' => $this->apiKey,
                    'limit'      => 1,
                ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'status'  => 'connected',
                    'message' => 'API connection successful',
                    'data'    => $response->json(),
                ];
            }

            return [
                'success' => false,
                'status'  => 'failed',
                'message' => "API returned status: {$response->status()}",
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'status'  => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }
}
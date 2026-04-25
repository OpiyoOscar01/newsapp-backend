<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Source;
use App\Models\Category;
use App\Models\ApiFetchLog;
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
    
    // Track fetched dates per request to avoid duplicates
    private static array $fetchedUrls = [];

    public function __construct()
    {
        $this->apiKey = config('mediastack.api_key');
        $this->apiUrl = config('mediastack.api_url');
        $this->defaultParams = config('mediastack.default_params', [
            'limit' => 100,
            'languages' => 'en',
            'countries' => 'us,gb,ca,au',
            'categories' => 'general,business,entertainment,health,science,sports,technology',
            'sort' => 'published_desc',
        ]);
        $this->timeout = config('mediastack.timeout', 60); // Increased timeout
        $this->retryConfig = config('mediastack.retry', ['times' => 3, 'sleep' => 100]);
    }

    /**
     * Fetch news from MediaStack API with deduplication logic
     */
    public function fetchNews(array $params = []): array
    {
        $startTime = microtime(true);
        $forceRefresh = $params['force_refresh'] ?? false;
        
        Log::info('Initiating news fetch from MediaStack', [
            'params' => $params
        ]);
        
        $fetchLog = $this->createFetchLog($params);

        try {
            // Apply deduplication logic unless force_refresh is true
            if (!$forceRefresh) {
                $params = $this->applyDeduplicationParams($params);
            }

            // Merge with default parameters
            $queryParams = array_merge($this->defaultParams, $params, [
                'access_key' => $this->apiKey,
            ]);

            // Remove null/empty values
            $queryParams = array_filter($queryParams, function($value) {
                return !is_null($value) && $value !== '';
            });

            // Handle date parameters properly for MediaStack API
            $queryParams = $this->formatDateParameters($queryParams);

            Log::info('Making MediaStack API request', [
                'url' => $this->apiUrl,
                'params' => $queryParams
            ]);

            // Make API request with retry logic
            $response = $this->makeApiRequest($queryParams);
            
            if (!$response->successful()) {
                throw new Exception("API request failed with status: {$response->status()}");
            }

            $data = $response->json();
            
            if (isset($data['error'])) {
                throw new Exception("MediaStack API Error: {$data['error']['message']}");
            }

            // Process and store articles with deduplication
            $processedCount = $this->processArticles($data['data'] ?? []);
            
            // Get stats for reporting
            $totalArticles = count($data['data'] ?? []);
            $duplicates = $totalArticles - $processedCount;
            
            // Update fetch log
            $this->updateFetchLog($fetchLog, [
                'status' => 'success',
                'articles_fetched' => $totalArticles,
                'articles_processed' => $processedCount,
                'duplicates_skipped' => $duplicates,
                'api_response' => $data,
                'execution_time' => microtime(true) - $startTime,
            ]);
            
            Log::info('News fetch from MediaStack completed', [
                'fetched' => $totalArticles,
                'processed' => $processedCount,
                'duplicates' => $duplicates,
                'offset' => $params['offset'] ?? 0,
                'execution_time' => microtime(true) - $startTime
            ]);

            return [
                'success' => true,
                'articles_fetched' => $totalArticles,
                'articles_processed' => $processedCount,
                'duplicates_skipped' => $duplicates,
                'pagination' => $data['pagination'] ?? null,
                'fetch_params' => $params,
            ];

        } catch (Exception $e) {
            Log::error('MediaStack API fetch failed', [
                'error' => $e->getMessage(),
                'params' => $params,
                'trace' => $e->getTraceAsString(),
            ]);

            $this->updateFetchLog($fetchLog, [
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'execution_time' => microtime(true) - $startTime,
            ]);

            throw $e;
        }
    }

    /**
     * Format date parameters correctly for MediaStack API
     */
    private function formatDateParameters(array $params): array
    {
        // MediaStack expects date in YYYY-MM-DD format
        if (isset($params['date'])) {
            // If it's a full datetime, extract just the date part
            if (strpos($params['date'], ' ') !== false) {
                $params['date'] = explode(' ', $params['date'])[0];
            }
        }

        // Handle date range
        if (isset($params['date_from']) && isset($params['date_to'])) {
            $params['date'] = $params['date_from'] . ',' . $params['date_to'];
            unset($params['date_from']);
            unset($params['date_to']);
        } elseif (isset($params['date']) && isset($params['date_to'])) {
            $params['date'] = $params['date'] . ',' . $params['date_to'];
            unset($params['date_to']);
        }

        // Remove date_search if present (MediaStack doesn't support it directly)
        unset($params['date_search']);

        return $params;
    }

    /**
     * Apply deduplication parameters to avoid fetching same articles
     */
    private function applyDeduplicationParams(array $params): array
    {
        // If date is explicitly provided, respect it
        if (isset($params['date'])) {
            return $params;
        }

        // Get the latest published date from existing articles
        $latestArticle = Article::whereNotNull('published_at')
            ->orderBy('published_at', 'desc')
            ->first();

        if ($latestArticle) {
            // Get just the date part in YYYY-MM-DD format
            $latestDate = $latestArticle->published_at->format('Y-m-d');
            
            // Set date filter to fetch from this date onward
            $params['date'] = $latestDate;
            
            Log::info('Auto-applying date filter to avoid duplicates', [
                'date_from' => $params['date']
            ]);
        } else {
            Log::info('No existing articles, fetching latest');
        }

        return $params;
    }

    /**
     * Process and store articles with enhanced deduplication
     */
    private function processArticles(array $articles): int
    {
        $processedCount = 0;
        $skippedCount = 0;
        $existingUrls = [];

        // Pre-fetch existing URLs for batch checking (performance optimization)
        $urls = array_column($articles, 'url');
        if (!empty($urls)) {
            $existingUrls = Article::whereIn('url', $urls)
                ->pluck('url')
                ->flip()
                ->toArray();
        }

        foreach ($articles as $articleData) {
            try {
                $url = $articleData['url'] ?? null;
                
                // Skip if URL is missing
                if (!$url) {
                    Log::warning('Article missing URL, skipping', ['article' => $articleData]);
                    continue;
                }

                // Skip if already processed in this batch
                if (isset(self::$fetchedUrls[$url])) {
                    $skippedCount++;
                    continue;
                }

                // Skip if already exists in database
                if (isset($existingUrls[$url])) {
                    self::$fetchedUrls[$url] = true;
                    $skippedCount++;
                    continue;
                }

                Log::debug('Processing article', [
                    'title' => $articleData['title'] ?? 'unknown',
                    'url' => $url
                ]);

                // Get or create source
                $source = $this->getOrCreateSource($articleData);

                // Get or create category
                $categoryName = $articleData['category'] ?? 'general';
                $category = $this->getOrCreateCategory($categoryName);

                // Prepare article data
                $processedData = $this->prepareArticleData($articleData, $source, $category);

                // Create article
                Article::create($processedData);
                
                // Track in memory to avoid duplicates in same batch
                self::$fetchedUrls[$url] = true;
                $processedCount++;

            } catch (\Throwable $e) {
                Log::error('Failed to process article', [
                    'url'   => $articleData['url'] ?? 'unknown',
                    'title' => $articleData['title'] ?? 'unknown',
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                ]);
            }
        }

        Log::info('Article processing summary', [
            'processed' => $processedCount,
            'skipped_db_duplicates' => $skippedCount,
            'total_received' => count($articles)
        ]);

        return $processedCount;
    }

    /**
     * Make API request with retry logic and better SSL handling
     */
    private function makeApiRequest(array $params)
    {
        $attempt = 0;
        $maxAttempts = $this->retryConfig['times'];
        $lastException = null;

        do {
            try {
                $response = Http::timeout($this->timeout)
                    ->withOptions([
                        'verify' => true, // Enable SSL verification
                        'connect_timeout' => 30, // Connection timeout
                        'read_timeout' => 30, // Read timeout
                    ])
                    ->retry(0) // Disable built-in retry, we'll handle it manually
                    ->get($this->apiUrl, $params);

                if ($response->successful()) {
                    return $response;
                }

                // Handle rate limiting
                if ($response->status() === 429) {
                    $retryAfter = $response->header('Retry-After', 5);
                    Log::warning('Rate limited, waiting', ['retry_after' => $retryAfter]);
                    sleep($retryAfter);
                } elseif ($response->status() >= 500) {
                    // Server error, wait before retry
                    Log::warning('Server error, retrying', ['status' => $response->status()]);
                    sleep($this->retryConfig['sleep'] / 1000);
                }

            } catch (Exception $e) {
                $lastException = $e;
                Log::warning('API request attempt failed', [
                    'attempt' => $attempt + 1,
                    'error' => $e->getMessage()
                ]);
                
                // Wait before retry
                if ($attempt < $maxAttempts - 1) {
                    sleep($this->retryConfig['sleep'] / 1000);
                }
            }

            $attempt++;
        } while ($attempt < $maxAttempts);

        // If we have an exception, throw it
        if ($lastException) {
            throw $lastException;
        }

        // Otherwise return the last response
        return $response ?? Http::timeout($this->timeout)->get($this->apiUrl, $params);
    }

    /**
     * Prepare article data for storage
     */
    private function prepareArticleData(array $data, Source $source, Category $category): array
    {
        return [
            'title' => $data['title'] ?? 'Untitled',
            'description' => $data['description'] ?? null,
            'content' => $data['description'] ?? null,
            'url' => $data['url'],
            'image_url' => $data['image'] ?? null,
            'author' => $data['author'] ?? null,
            'source' => $data['source'] ?? 'Unknown',
            'category' => $data['category'] ?? 'general',
            'country' => $data['country'] ?? null,
            'language' => $data['language'] ?? null,
            'published_at' => isset($data['published_at']) ? Carbon::parse($data['published_at']) : now(),
            'slug' => $this->generateSlug($data['title'] ?? 'untitled'),
            'source_id' => $source->id,
            'category_id' => $category->id,
            'is_active' => true,
            'is_featured' => false,
            'view_count' => 0,
            'processing_status' => 'pending',
            'metadata' => [
                'mediastack_data' => $data,
                'processed_at' => now()->toISOString(),
            ],
        ];
    }

    /**
     * Get or create source
     */
    private function getOrCreateSource(array $sourceData): Source
    {
        $sourceName = $sourceData['source'] ?? 'Unknown Source';
        $mediastackId = $sourceData['id'] ?? strtolower(str_replace(' ', '_', $sourceName));

        return Source::firstOrCreate(
            ['mediastack_id' => $mediastackId],
            [
                'name' => $sourceName,
                'slug' => Str::slug($sourceName),
                'description' => "News source: {$sourceName}",
                'is_active' => true,
                'metadata' => ['created_from_mediastack' => true],
            ]
        );
    }

    /**
     * Get or create category
     */
    private function getOrCreateCategory(string $categoryName): Category
    {
        return Category::firstOrCreate(
            ['name' => $categoryName],
            [
                'slug' => Str::slug($categoryName),
                'description' => "News category: {$categoryName}",
                'is_active' => true,
                'metadata' => ['created_from_mediastack' => true],
            ]
        );
    }

    /**
     * Generate unique slug
     */
    private function generateSlug(string $title): string
    {
        $slug = Str::slug($title);
        $originalSlug = $slug;
        $counter = 1;

        while (Article::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Create fetch log entry
     */
    private function createFetchLog(array $params): ApiFetchLog
    {
        return ApiFetchLog::create([
            'api_endpoint' => $this->apiUrl . '?access_key=' . Str::mask($this->apiKey, '*', 1, -4),
            'request_params' => $params,
            'status' => 'running',
            'started_at' => now(),
        ]);
    }

    /**
     * Update fetch log entry
     */
    private function updateFetchLog(ApiFetchLog $log, array $data): void
    {
        $log->update(array_merge($data, [
            'finished_at' => now(),
        ]));
    }

    /**
     * Fetch news with pagination and track progress
     */
    public function fetchNewsWithPagination(int $page = 1, int $limit = 100, array $filters = []): array
    {
        $offset = ($page - 1) * $limit;
        
        $params = array_merge($filters, [
            'limit' => $limit,
            'offset' => $offset,
        ]);
        
        $result = $this->fetchNews($params);
        
        // Add page info to result
        $result['page'] = $page;
        $result['has_more'] = ($result['pagination']['total'] ?? 0) > ($offset + $limit);
        
        return $result;
    }

    /**
     * Get API usage statistics
     */
    public function getUsageStats(): array
    {
        $logs = ApiFetchLog::where('created_at', '>=', now()->subDays(30))
            ->selectRaw('
                DATE(created_at) as date,
                COUNT(*) as requests,
                SUM(articles_fetched) as total_articles,
                SUM(articles_processed) as processed_articles,
                SUM(duplicates_skipped) as duplicates_skipped,
                AVG(execution_time) as avg_execution_time
            ')
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get();

        return [
            'daily_stats' => $logs,
            'summary' => [
                'total_requests' => $logs->sum('requests'),
                'total_articles_fetched' => $logs->sum('total_articles'),
                'total_articles_processed' => $logs->sum('processed_articles'),
                'total_duplicates_skipped' => $logs->sum('duplicates_skipped'),
                'avg_execution_time' => round($logs->avg('avg_execution_time'), 2),
            ],
        ];
    }

    /**
     * Get database statistics
     */
    public function getDatabaseStats(): array
    {
        return [
            'total_articles' => Article::count(),
            'articles_by_category' => Article::selectRaw('category, count(*) as count')
                ->groupBy('category')
                ->orderBy('count', 'desc')
                ->get(),
            'articles_by_source' => Article::selectRaw('source, count(*) as count')
                ->groupBy('source')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get(),
            'latest_article_date' => Article::max('published_at'),
            'oldest_article_date' => Article::min('published_at'),
            'articles_today' => Article::whereDate('created_at', today())->count(),
            'date_range_coverage' => [
                'from' => Article::min('published_at'),
                'to' => Article::max('published_at'),
                'total_days' => Article::selectRaw('DATEDIFF(MAX(published_at), MIN(published_at)) as days')->value('days'),
            ],
        ];
    }

    /**
     * Reset fetch tracker (for testing)
     */
    public function resetFetchTracker(): void
    {
        self::$fetchedUrls = [];
    }

    /**
     * Test API connection
     */
    public function testConnection(): array
    {
        try {
            $response = Http::timeout(30)
                ->withOptions([
                    'verify' => true,
                    'connect_timeout' => 15,
                ])
                ->get($this->apiUrl, [
                    'access_key' => $this->apiKey,
                    'limit' => 1,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'status' => 'connected',
                    'message' => 'API connection successful',
                    'data' => $data,
                ];
            }

            return [
                'success' => false,
                'status' => 'failed',
                'message' => "API returned status: {$response->status()}",
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }
}
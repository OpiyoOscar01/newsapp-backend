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

    public function __construct()
    {
        $this->apiKey = config('mediastack.api_key');
        $this->apiUrl = config('mediastack.api_url');
        $this->defaultParams = config('mediastack.default_params');
        $this->timeout = config('mediastack.timeout');
        $this->retryConfig = config('mediastack.retry');
    }

    /**
     * Fetch news from MediaStack API
     */
    public function fetchNews(array $params = []): array
    {
        $startTime = microtime(true);
        Log::info('Initiating news fetch from MediaStack', [
            'params' => $params
        ]);
        $fetchLog = $this->createFetchLog($params);

        try {
            // Merge with default parameters
            $queryParams = array_merge($this->defaultParams, $params, [
                'access_key' => $this->apiKey,
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

            // Process and store articles
            $processedCount = $this->processArticles($data['data'] ?? []);
            
            // Update fetch log
           $fetchLog = $this->updateFetchLog($fetchLog, [
                'status' => 'success',
                'articles_fetched' => count($data['data'] ?? []),
                'articles_processed' => $processedCount,
                'api_response' => $data,
                'execution_time' => microtime(true) - $startTime,
            ]);
            Log::info('News fetch from MediaStack completed', [
                'fetched' => count($data['data'] ?? []),
                'data' => $data,
                'processed' => $processedCount
            ]);

            return [
                'success' => true,
                'articles_fetched' => count($data['data'] ?? []),
                'articles_processed' => $processedCount,
                'pagination' => $data['pagination'] ?? null,
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
     * Make API request with retry logic
     */
    private function makeApiRequest(array $params)
    {
        $attempt = 0;
        $maxAttempts = $this->retryConfig['times'];

        do {
            try {
                $response = Http::timeout($this->timeout)
                    ->retry($maxAttempts, $this->retryConfig['sleep'])
                    ->get($this->apiUrl, $params);

                if ($response->successful()) {
                    return $response;
                }

                $attempt++;
                if ($attempt < $maxAttempts) {
                    sleep($this->retryConfig['sleep'] / 1000);
                }

            } catch (Exception $e) {
                $attempt++;
                if ($attempt >= $maxAttempts) {
                    throw $e;
                }
                sleep($this->retryConfig['sleep'] / 1000);
            }
        } while ($attempt < $maxAttempts);

        return $response;
    }

    /**
     * Process and store articles
     */
  private function processArticles(array $articles): int
{
    $processedCount = 0;

    foreach ($articles as $articleData) {
        try {
            // Skip if article already exists
            if (Article::where('url', $articleData['url'])->exists()) {
                continue;
            }

            Log::info('Processing article', [
                'title' => $articleData['title'] ?? 'unknown',
                'data'  => $articleData,
            ]);

            // Get or create source
            $source = $this->getOrCreateSource($articleData);
            Log::info('Source created or fetched', $source->toArray());

            // Get or create category
            $category = $this->getOrCreateCategory($articleData['category']);

            // Prepare article data
            $processedData = $this->prepareArticleData($articleData, $source, $category);

            // Create article
            Article::create($processedData);
            $processedCount++;

        } catch (\Throwable $e) {
            // Log full exception details
            Log::error('Failed to process article', [
                'url'        => $articleData['url'] ?? 'unknown',
                'title'      => $articleData['title'] ?? 'unknown',
                'error'      => $e->getMessage(),
                'exception'  => get_class($e),
                'file'       => $e->getFile(),
                'line'       => $e->getLine(),
                'trace'      => $e->getTraceAsString(),
                'data'       => $articleData,
            ]);
        }
    }

    return $processedCount;
}


    /**
     * Prepare article data for storage
     */
    private function prepareArticleData(array $data, Source $source, Category $category): array
    {
        return [
            'title' => $data['title'],
            'description' => $data['description'],
            'content' => $data['description'], // MediaStack doesn't provide full content
            'url' => $data['url'],
            'image_url' => $data['image'],
            'author' => $data['author'],
            'source' => $data['source'],
            'category' => $data['category'],
            'country' => $data['country'],
            'language' => $data['language'],
            'published_at' => Carbon::parse($data['published_at']),
            'slug' => $this->generateSlug($data['title']),
            'source_id' => $source->id,
            'category_id' => $category->id,
            'is_active' => true,
            'is_featured' => false,
            'view_count' => 0,
            'metadata' => [
                'mediastack_data' => $data,
                'processed_at' => now()->toISOString(),
            ],
        ];
    }

    /**
     * Get or create source
     */
/**
 * Get or create a source based on MediaStack ID and name
 */
private function getOrCreateSource(array $sourceData): Source
{
    // Extract values with defaults
    $sourceName = $sourceData['source'] ?? 'Unknown Source';
    
    // Use MediaStack 'id' if available, otherwise generate a fallback ID from the source name
    $mediastackId = $sourceData['id'] ?? strtolower(str_replace(' ', '_', $sourceName));

    // Optional: log a warning if ID was missing
    if (!isset($sourceData['id'])) {
        Log::warning("Source data missing 'id', using fallback ID", [
            'source_name' => $sourceName,
            'fallback_id' => $mediastackId
        ]);
    }

    return Source::firstOrCreate(
        ['mediastack_id' => $mediastackId], // search by MediaStack ID or fallback
        [
            'name'        => $sourceName,
            'slug'        => \Illuminate\Support\Str::slug($sourceName),
            'description' => "News source: {$sourceName}",
            'is_active'   => true,
            'metadata'    => ['created_from_mediastack' => true],
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
                'avg_execution_time' => $logs->avg('avg_execution_time'),
            ],
        ];
    }

    /**
     * Test API connection
     */
    public function testConnection(): array
    {
        try {
            $response = Http::timeout(10)->get($this->apiUrl, [
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

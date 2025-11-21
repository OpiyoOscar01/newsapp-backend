<?php

namespace App\Services;

use App\Models\Article;
use App\Repositories\Contracts\ArticleRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ArticleService
{
    public function __construct(
        private ArticleRepositoryInterface $articleRepository
    ) {}

    /**
     * Get paginated articles with enhanced filtering
     */
    public function getPaginated(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        return $this->articleRepository->paginate($perPage, $filters);
    }

    /**
     * Find article by ID
     */
    public function findById(int $id): ?Article
    {
        return Cache::remember(
            "article.{$id}",
            now()->addMinutes(30),
            fn() => $this->articleRepository->find($id)
        );
    }

    /**
     * Find article by slug
     */
    public function findBySlug(string $slug): ?Article
    {
        return Cache::remember(
            "article.slug.{$slug}",
            now()->addMinutes(30),
            fn() => $this->articleRepository->findBySlug($slug)
        );
    }

    /**
     * Create new article
     */
    public function create(array $data): Article
    {
        return DB::transaction(function () use ($data) {
            // Generate slug if not provided
            if (empty($data['slug'])) {
                $data['slug'] = $this->generateUniqueSlug($data['title']);
            }

            // Create article
            $article = $this->articleRepository->create($data);

            // Clear cache
            $this->clearRelatedCache();

            return $article;
        });
    }

    /**
     * Update article
     */
    public function update(Article $article, array $data): Article
    {
        return DB::transaction(function () use ($article, $data) {
            // Update slug if title changed and slug not provided
            if (isset($data['title']) && !isset($data['slug'])) {
                $data['slug'] = $this->generateUniqueSlug($data['title'], $article->id);
            }

            $this->articleRepository->update($article, $data);

            // Clear cache
            $this->clearArticleCache($article);
            $this->clearRelatedCache();

            return $article->fresh();
        });
    }

    /**
     * Delete article
     */
    public function delete(Article $article): bool
    {
        return DB::transaction(function () use ($article) {
            $result = $this->articleRepository->delete($article);
            
            // Clear cache
            $this->clearArticleCache($article);
            $this->clearRelatedCache();

            return $result;
        });
    }

    /**
     * Get latest articles with caching
     */
    public function getLatest(int $limit = 20, array $filters = []): Collection
    {
        $cacheKey = 'articles.latest.' . md5(serialize($filters)) . ".{$limit}";
        
        return Cache::remember(
            $cacheKey,
            now()->addMinutes(15),
            fn() => $this->articleRepository->getLatest($limit)
        );
    }

    /**
     * Get featured articles
     */
    public function getFeatured(int $limit = 10): Collection
    {
        return Cache::remember(
            "articles.featured.{$limit}",
            now()->addMinutes(30),
            fn() => $this->articleRepository->getFeatured($limit)
        );
    }

    /**
     * Get trending articles
     */
    public function getTrending(int $limit = 20, int $days = 7): Collection
    {
        return Cache::remember(
            "articles.trending.{$limit}.{$days}",
            now()->addMinutes(20),
            fn() => $this->articleRepository->getTrending($limit)
        );
    }

    /**
     * Get articles by category
     */
    public function getByCategory(string $category, ?int $limit): Collection
    {
        $cacheKey = "articles.category.{$category}" . ($limit ? ".{$limit}" : '');
        
        return Cache::remember(
            $cacheKey,
            now()->addMinutes(20),
            fn() => $this->articleRepository->byCategory($category, $limit)
        );
    }

    /**
     * Get articles by source
     */
    public function getBySource(string $source, ?int $limit): Collection
    {
        $cacheKey = "articles.source.{$source}" . ($limit ? ".{$limit}" : '');
        
        return Cache::remember(
            $cacheKey,
            now()->addMinutes(20),
            fn() => $this->articleRepository->bySource($source, $limit)
        );
    }

    /**
     * Search articles
     */
    public function search(string $term): Collection
    {
        return $this->articleRepository->search($term);
    }

    /**
     * Get related articles with improved algorithm
     */
    public function getRelated(Article $article, int $limit = 5): Collection
    {
        return Cache::remember(
            "article.{$article->id}.related.{$limit}",
            now()->addHour(),
            fn() => $this->articleRepository->getRelated($article, $limit)
        );
    }

    /**
     * Feature article
     */
    public function feature(Article $article): Article
    {
        $this->articleRepository->feature($article);
        $this->clearArticleCache($article);
        Cache::forget('articles.featured.10');
        return $article->fresh();
    }

    /**
     * Unfeature article
     */
    public function unfeature(Article $article): Article
    {
        $this->articleRepository->unfeature($article);
        $this->clearArticleCache($article);
        Cache::forget('articles.featured.10');
        return $article->fresh();
    }

    /**
     * Activate article
     */
    public function activate(Article $article): Article
    {
        $this->articleRepository->activate($article);
        $this->clearArticleCache($article);
        $this->clearRelatedCache();
        return $article->fresh();
    }

    /**
     * Deactivate article
     */
    public function deactivate(Article $article): Article
    {
        $this->articleRepository->deactivate($article);
        $this->clearArticleCache($article);
        $this->clearRelatedCache();
        return $article->fresh();
    }

    /**
     * Record article view with enhanced analytics
     */
    public function recordView(Article $article, array $data = []): void
    {
        // Increment view count (with rate limiting per session)
        $sessionKey = "article_view_{$article->id}_" . session()->getId();
        
        if (!Cache::has($sessionKey)) {
            $article->incrementViewCount();
            Cache::put($sessionKey, true, now()->addMinutes(30));
        }

        // Create interaction record
        $article->interactions()->create([
            'interaction_type' => 'view',
            'user_id' => Auth::id(),
            'session_id' => session()->getId(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'referrer' => $data['referrer'] ?? request()->header('referer'),
            'metadata' => array_merge($data, [
                'timestamp' => now()->toISOString(),
                'page_url' => request()->fullUrl(),
            ]),
            'interaction_date' => now(),
        ]);

        // Clear trending cache as views affect trending
        Cache::forget('articles.trending.20.7');
    }

    /**
     * Generate unique slug
     */
    private function generateUniqueSlug(string $title, ?int $excludeId = null): string
    {
        $slug = Str::slug($title);
        $originalSlug = $slug;
        $counter = 1;

        while (true) {
            $query = Article::where('slug', $slug);
            
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }

            if (!$query->exists()) {
                break;
            }

            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Clear article-specific cache
     */
    private function clearArticleCache(Article $article): void
    {
        Cache::forget("article.{$article->id}");
        Cache::forget("article.slug.{$article->slug}");
        Cache::forget("article.{$article->id}.related.5");
    }

    /**
     * Clear related cache entries
     */
    private function clearRelatedCache(): void
    {
        Cache::forget('articles.latest.20');
        Cache::forget('articles.trending.20.7');
        // Add more cache keys as needed
    }
}

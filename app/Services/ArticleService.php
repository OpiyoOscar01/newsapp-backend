<?php

namespace App\Services;

use App\Models\Article;
use App\Repositories\Contracts\ArticleRepositoryInterface;
use App\Services\ImageCacheService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Article Service
 * 
 * Handles business logic for articles
 */
class ArticleService
{
    /**
     * Create a new service instance.
     */
    public function __construct(
        private ArticleRepositoryInterface $articleRepository,
        // private ImageCacheService $imageCacheService
    ) {}

    /**
     * Get paginated articles
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
        return $this->articleRepository->find($id);
    }

    /**
     * Find article by slug
     */
    public function findBySlug(string $slug): ?Article
    {
        return $this->articleRepository->findBySlug($slug);
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

            // Queue image caching if image URL provided
            if (!empty($data['image_url'])) {
                // Dispatch job to cache image
                // CacheArticleImages::dispatch($article);
            }

            // Queue keyword extraction
            // ProcessArticleKeywords::dispatch($article);

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

            return $article->fresh();
        });
    }

    /**
     * Delete article
     */
    public function delete(Article $article): bool
    {
        return DB::transaction(function () use ($article) {
            // Delete cached image if exists
            if ($article->cached_image_path) {
                // $this->imageCacheService->delete($article->cached_image_path);
            }

            return $this->articleRepository->delete($article);
        });
    }

    /**
     * Get latest articles
     */
    public function getLatest(int $limit = 20): Collection
    {
        return $this->articleRepository->getLatest($limit);
    }

    /**
     * Get featured articles
     */
    public function getFeatured(int $limit = 10): Collection
    {
        return $this->articleRepository->getFeatured($limit);
    }

    /**
     * Get trending articles
     */
    public function getTrending(int $limit = 20): Collection
    {
        return $this->articleRepository->getTrending($limit);
    }

    /**
     * Get articles by category
     */
    public function getByCategory(string $category, int $limit = null): Collection
    {
        return $this->articleRepository->byCategory($category, $limit);
    }

    /**
     * Get articles by source
     */
    public function getBySource(string $source, int $limit = null): Collection
    {
        return $this->articleRepository->bySource($source, $limit);
    }

    /**
     * Search articles
     */
    public function search(string $term): Collection
    {
        return $this->articleRepository->search($term);
    }

    /**
     * Get related articles
     */
    public function getRelated(Article $article, int $limit = 5): Collection
    {
        return $this->articleRepository->getRelated($article, $limit);
    }

    /**
     * Feature article
     */
    public function feature(Article $article): Article
    {
        $this->articleRepository->feature($article);
        return $article->fresh();
    }

    /**
     * Unfeature article
     */
    public function unfeature(Article $article): Article
    {
        $this->articleRepository->unfeature($article);
        return $article->fresh();
    }

    /**
     * Activate article
     */
    public function activate(Article $article): Article
    {
        $this->articleRepository->activate($article);
        return $article->fresh();
    }

    /**
     * Deactivate article
     */
    public function deactivate(Article $article): Article
    {
        $this->articleRepository->deactivate($article);
        return $article->fresh();
    }

    /**
     * Record article view
     */
    public function recordView(Article $article, array $data = []): void
    {
        // Increment view count
        $article->incrementViewCount();

        // Create interaction record
        $article->interactions()->create([
            'interaction_type' => 'view',
            'user_id' => Auth::id(),
            'session_id' => session()->getId(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'referrer' => request()->header('referer'),
            'metadata' => $data,
            'interaction_date' => now(),
        ]);
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
}
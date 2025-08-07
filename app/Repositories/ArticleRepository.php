<?php

namespace App\Repositories;

use App\Models\Article;
use App\Repositories\Contracts\ArticleRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ArticleRepository implements ArticleRepositoryInterface
{
    /**
     * Get all articles
     */
    public function all(): Collection
    {
        return Article::orderBy('published_at', 'desc')->get();
    }

    /**
     * Get paginated articles
     */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $query = Article::active()->orderBy('published_at', 'desc');

        // Apply filters
        if (!empty($filters['category'])) {
            $query->byCategory($filters['category']);
        }

        if (!empty($filters['source'])) {
            $query->bySource($filters['source']);
        }

        if (!empty($filters['country'])) {
            $query->where('country', $filters['country']);
        }

        if (!empty($filters['language'])) {
            $query->where('language', $filters['language']);
        }

        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (!empty($filters['featured'])) {
            $query->featured();
        }

        return $query->paginate($perPage);
    }

    /**
     * Find article by ID
     */
    public function find(int $id): ?Article
    {
        return Article::find($id);
    }

    /**
     * Find article by slug
     */
    public function findBySlug(string $slug): ?Article
    {
        return Article::where('slug', $slug)->first();
    }

    /**
     * Find article by URL
     */
    public function findByUrl(string $url): ?Article
    {
        return Article::where('url', $url)->first();
    }

    /**
     * Create new article
     */
    public function create(array $data): Article
    {
        return Article::create($data);
    }

    /**
     * Update article
     */
    public function update(Article $article, array $data): bool
    {
        return $article->update($data);
    }

    /**
     * Delete article
     */
    public function delete(Article $article): bool
    {
        return $article->delete();
    }

    /**
     * Get active articles
     */
    public function getActive(): Collection
    {
        return Article::active()->orderBy('published_at', 'desc')->get();
    }

    /**
     * Get featured articles
     */
    public function getFeatured(int $limit = 10): Collection
    {
        return Article::active()
            ->featured()
            ->orderBy('published_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get latest articles
     */
    public function getLatest(int $limit = 20): Collection
    {
        return Article::active()
            ->orderBy('published_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get trending articles
     */
    public function getTrending(int $limit = 20): Collection
    {
        return Article::active()
            ->recent(7)
            ->popular()
            ->limit($limit)
            ->get();
    }

    /**
     * Get articles by category
     */
    public function byCategory(string $category, int $limit = null): Collection
    {
        $query = Article::active()
            ->byCategory($category)
            ->orderBy('published_at', 'desc');

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Get articles by source
     */
    public function bySource(string $source, int $limit = null): Collection
    {
        $query = Article::active()
            ->bySource($source)
            ->orderBy('published_at', 'desc');

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Search articles
     */
    public function search(string $term): Collection
    {
        return Article::active()
            ->search($term)
            ->orderBy('published_at', 'desc')
            ->get();
    }

    /**
     * Get related articles
     */
    public function getRelated(Article $article, int $limit = 5): Collection
    {
        return Article::active()
            ->where('id', '!=', $article->id)
            ->where(function ($query) use ($article) {
                $query->where('category', $article->category)
                      ->orWhere('source', $article->source);
            })
            ->orderBy('published_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Feature article
     */
    public function feature(Article $article): bool
    {
        return $article->feature();
    }

    /**
     * Unfeature article
     */
    public function unfeature(Article $article): bool
    {
        return $article->unfeature();
    }

    /**
     * Activate article
     */
    public function activate(Article $article): bool
    {
        return $article->activate();
    }

    /**
     * Deactivate article
     */
    public function deactivate(Article $article): bool
    {
        return $article->deactivate();
    }
}
<?php

namespace App\Repositories;

use App\Models\Article;
use App\Repositories\Contracts\ArticleRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;

class ArticleRepository implements ArticleRepositoryInterface
{
    /**
     * Get paginated articles with advanced filtering
     */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $query = Article::active()
            ->with(['sourceModel', 'categoryModel'])
            ->orderBy('published_at', 'desc');

        // Apply filters
        $this->applyFilters($query, $filters);

        return $query->paginate($perPage);
    }

    /**
     * Apply filters to query
     */
    private function applyFilters($query, array $filters): void
    {
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

        if (!empty($filters['date_from'])) {
            $query->where('published_at', '>=', Carbon::parse($filters['date_from']));
        }

        if (!empty($filters['date_to'])) {
            $query->where('published_at', '<=', Carbon::parse($filters['date_to'])->endOfDay());
        }

        if (!empty($filters['sort_by'])) {
            $direction = $filters['sort_order'] ?? 'desc';
            $query->orderBy($filters['sort_by'], $direction);
        }
    }

    /**
     * Get latest articles with optional filters
     */
    public function getLatest(int $limit = 20, array $filters = []): Collection
    {
        $query = Article::active()
            ->with(['sourceModel', 'categoryModel'])
            ->orderBy('published_at', 'desc');

        $this->applyFilters($query, $filters);

        return $query->limit($limit)->get();
    }

    /**
     * Get trending articles based on views and recency
     */
    public function getTrending(int $limit = 20, int $days = 7): Collection
    {
        return Article::active()
            ->with(['sourceModel', 'categoryModel'])
            ->where('published_at', '>=', Carbon::now()->subDays($days))
            ->orderByRaw('(view_count * (1 / GREATEST(DATEDIFF(NOW(), published_at), 1))) DESC')
            ->limit($limit)
            ->get();
    }

    /**
     * Get related articles using improved matching
     */
    public function getRelated(Article $article, int $limit = 5): Collection
    {
        return Article::active()
            ->with(['sourceModel', 'categoryModel'])
            ->where('id', '!=', $article->id)
            ->where(function ($query) use ($article) {
                $query->where('category', $article->category)
                      ->orWhere('source', $article->source);
            })
            ->orderByRaw('
                CASE 
                    WHEN category = ? AND source = ? THEN 1
                    WHEN category = ? THEN 2  
                    WHEN source = ? THEN 3
                    ELSE 4
                END, published_at DESC
            ', [$article->category, $article->source, $article->category, $article->source])
            ->limit($limit)
            ->get();
    }

    // ... other repository methods remain the same
    
    public function find(int $id): ?Article
    {
        return Article::with(['sourceModel', 'categoryModel'])->find($id);
    }

    public function findBySlug(string $slug): ?Article
    {
        return Article::with(['sourceModel', 'categoryModel'])
            ->where('slug', $slug)
            ->first();
    }

    public function findByUrl(string $url): ?Article
    {
        return Article::where('url', $url)->first();
    }

    public function create(array $data): Article
    {
        return Article::create($data);
    }

    public function update(Article $article, array $data): bool
    {
        return $article->update($data);
    }

    public function delete(Article $article): bool
    {
        return $article->delete();
    }

    public function getFeatured(int $limit = 10): Collection
    {
        return Article::active()
            ->with(['sourceModel', 'categoryModel'])
            ->featured()
            ->orderBy('published_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function byCategory(string $category, ?int $limit = null): Collection
    {
        $query = Article::active()
            ->with(['sourceModel', 'categoryModel'])
            ->byCategory($category)
            ->orderBy('published_at', 'desc');

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    public function bySource(string $source, ?int $limit = null): Collection
    {
        $query = Article::active()
            ->with(['sourceModel', 'categoryModel'])
            ->bySource($source)
            ->orderBy('published_at', 'desc');

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    public function search(string $term): Collection
    {
        return Article::active()
            ->with(['sourceModel', 'categoryModel'])
            ->search($term)
            ->orderBy('published_at', 'desc')
            ->get();
    }

    public function feature(Article $article): bool
    {
        return $article->update(['is_featured' => true]);
    }

    public function unfeature(Article $article): bool
    {
        return $article->update(['is_featured' => false]);
    }

    public function activate(Article $article): bool
    {
        return $article->update(['is_active' => true]);
    }

    public function deactivate(Article $article): bool
    {
        return $article->update(['is_active' => false]);
    }

    /**
     * Get all articles
     */
    public function all(): Collection
    {
        return Article::with(['sourceModel', 'categoryModel'])->get();
    }

    /**
     * Get all active articles
     */
    public function getActive(): Collection
    {
        return Article::active()
            ->with(['sourceModel', 'categoryModel'])
            ->get();
    }
}

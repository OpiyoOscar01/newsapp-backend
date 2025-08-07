<?php

namespace App\Services;

use App\Models\Category;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Category Service
 * 
 * Handles business logic for categories
 */
class CategoryService
{
    /**
     * Create a new service instance.
     */
    public function __construct(
        private CategoryRepositoryInterface $categoryRepository
    ) {}

    /**
     * Get all categories
     */
    public function getAll(): Collection
    {
        return Cache::remember('categories.all', 3600, function () {
            return $this->categoryRepository->all();
        });
    }

    /**
     * Get paginated categories
     */
    public function getPaginated(int $perPage = 15): LengthAwarePaginator
    {
        return $this->categoryRepository->paginate($perPage);
    }

    /**
     * Find category by ID
     */
    public function findById(int $id): ?Category
    {
        return $this->categoryRepository->find($id);
    }

    /**
     * Find category by slug
     */
    public function findBySlug(string $slug): ?Category
    {
        return Cache::remember("category.{$slug}", 3600, function () use ($slug) {
            return $this->categoryRepository->findBySlug($slug);
        });
    }

    /**
     * Create new category
     */
    public function create(array $data): Category
    {
        // Generate slug if not provided
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $category = $this->categoryRepository->create($data);

        // Clear cache
        $this->clearCache();

        return $category;
    }

    /**
     * Update category
     */
    public function update(Category $category, array $data): Category
    {
        // Update slug if name changed and slug not provided
        if (isset($data['name']) && !isset($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $this->categoryRepository->update($category, $data);

        // Clear cache
        $this->clearCache();

        return $category->fresh();
    }

    /**
     * Delete category
     */
    public function delete(Category $category): bool
    {
        $result = $this->categoryRepository->delete($category);

        if ($result) {
            // Clear cache
            $this->clearCache();
        }

        return $result;
    }

    /**
     * Get active categories
     */
    public function getActive(): Collection
    {
        return Cache::remember('categories.active', 3600, function () {
            return $this->categoryRepository->getActive();
        });
    }

    /**
     * Get categories with articles
     */
    public function getWithArticles(): Collection
    {
        return Cache::remember('categories.with_articles', 1800, function () {
            return $this->categoryRepository->withArticles();
        });
    }

    /**
     * Search categories
     */
    public function search(string $term): Collection
    {
        return $this->categoryRepository->search($term);
    }

    /**
     * Activate category
     */
    public function activate(Category $category): Category
    {
        $this->categoryRepository->activate($category);
        $this->clearCache();

        return $category->fresh();
    }

    /**
     * Deactivate category
     */
    public function deactivate(Category $category): Category
    {
        $this->categoryRepository->deactivate($category);
        $this->clearCache();

        return $category->fresh();
    }

    /**
     * Get category statistics
     */
    public function getStats(Category $category): array
    {
        return Cache::remember("category.{$category->slug}.stats", 1800, function () use ($category) {
            $totalArticles = $category->articles()->count();
            $activeArticles = $category->articles()->where('is_active', true)->count();
            $featuredArticles = $category->articles()->where('is_featured', true)->count();
            $recentArticles = $category->articles()
                ->where('published_at', '>=', now()->subDays(7))
                ->count();

            $totalViews = $category->articles()->sum('view_count');

            return [
                'total_articles' => $totalArticles,
                'active_articles' => $activeArticles,
                'featured_articles' => $featuredArticles,
                'recent_articles' => $recentArticles,
                'total_views' => $totalViews,
                'avg_views_per_article' => $totalArticles > 0 ? round($totalViews / $totalArticles, 2) : 0,
            ];
        });
    }

    /**
     * Clear cache
     */
    private function clearCache(): void
    {
        Cache::forget('categories.all');
        Cache::forget('categories.active');
        Cache::forget('categories.with_articles');
    }
}
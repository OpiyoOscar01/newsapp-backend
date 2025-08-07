<?php

namespace App\Repositories;

use App\Models\Category;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class CategoryRepository implements CategoryRepositoryInterface
{
    /**
     * Get all categories
     */
    public function all(): Collection
    {
        return Category::orderBy('name')->get();
    }

    /**
     * Get paginated categories
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Category::orderBy('name')->paginate($perPage);
    }

    /**
     * Find category by ID
     */
    public function find(int $id): ?Category
    {
        return Category::find($id);
    }

    /**
     * Find category by slug
     */
    public function findBySlug(string $slug): ?Category
    {
        return Category::where('slug', $slug)->first();
    }

    /**
     * Create new category
     */
    public function create(array $data): Category
    {
        return Category::create($data);
    }

    /**
     * Update category
     */
    public function update(Category $category, array $data): bool
    {
        return $category->update($data);
    }

    /**
     * Delete category
     */
    public function delete(Category $category): bool
    {
        return $category->delete();
    }

    /**
     * Get active categories
     */
    public function getActive(): Collection
    {
        return Category::active()->orderBy('name')->get();
    }

    /**
     * Get categories with articles
     */
    public function withArticles(): Collection
    {
        return Category::withArticles()->orderBy('name')->get();
    }

    /**
     * Search categories
     */
    public function search(string $term): Collection
    {
        return Category::where('name', 'like', "%{$term}%")
            ->orWhere('description', 'like', "%{$term}%")
            ->orderBy('name')
            ->get();
    }

    /**
     * Activate category
     */
    public function activate(Category $category): bool
    {
        return $category->activate();
    }

    /**
     * Deactivate category
     */
    public function deactivate(Category $category): bool
    {
        return $category->deactivate();
    }
}
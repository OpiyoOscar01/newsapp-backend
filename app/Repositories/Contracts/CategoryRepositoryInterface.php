<?php

namespace App\Repositories\Contracts;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface CategoryRepositoryInterface
{
    /**
     * Get all categories
     */
    public function all(): Collection;

    /**
     * Get paginated categories
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator;

    /**
     * Find category by ID
     */
    public function find(int $id): ?Category;

    /**
     * Find category by slug
     */
    public function findBySlug(string $slug): ?Category;

    /**
     * Create new category
     */
    public function create(array $data): Category;

    /**
     * Update category
     */
    public function update(Category $category, array $data): bool;

    /**
     * Delete category
     */
    public function delete(Category $category): bool;

    /**
     * Get active categories
     */
    public function getActive(): Collection;

    /**
     * Get categories with articles
     */
    public function withArticles(): Collection;

    /**
     * Search categories
     */
    public function search(string $term): Collection;

    /**
     * Activate category
     */
    public function activate(Category $category): bool;

    /**
     * Deactivate category
     */
    public function deactivate(Category $category): bool;
}
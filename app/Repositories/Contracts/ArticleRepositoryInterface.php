<?php

namespace App\Repositories\Contracts;

use App\Models\Article;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface ArticleRepositoryInterface
{
    /**
     * Get all articles
     */
    public function all(): Collection;

    /**
     * Get paginated articles
     */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator;

    /**
     * Find article by ID
     */
    public function find(int $id): ?Article;

    /**
     * Find article by slug
     */
    public function findBySlug(string $slug): ?Article;

    /**
     * Find article by URL
     */
    public function findByUrl(string $url): ?Article;

    /**
     * Create new article
     */
    public function create(array $data): Article;

    /**
     * Update article
     */
    public function update(Article $article, array $data): bool;

    /**
     * Delete article
     */
    public function delete(Article $article): bool;

    /**
     * Get active articles
     */
    public function getActive(): Collection;

    /**
     * Get featured articles
     */
    public function getFeatured(int $limit = 10): Collection;

    /**
     * Get latest articles
     */
    public function getLatest(int $limit = 20): Collection;

    /**
     * Get trending articles
     */
    public function getTrending(int $limit = 20): Collection;

    /**
     * Get articles by category
     */
    public function byCategory(string $category, ?int $limit = null): Collection;

    /**
     * Get articles by source
     */
    public function bySource(string $source, ?int $limit = null): Collection;

    /**
     * Search articles
     */
    public function search(string $term): Collection;

    /**
     * Get related articles
     */
    public function getRelated(Article $article, int $limit = 5): Collection;

    /**
     * Feature article
     */
    public function feature(Article $article): bool;

    /**
     * Unfeature article
     */
    public function unfeature(Article $article): bool;

    /**
     * Activate article
     */
    public function activate(Article $article): bool;

    /**
     * Deactivate article
     */
    public function deactivate(Article $article): bool;
}
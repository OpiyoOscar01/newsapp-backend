<?php

namespace App\Repositories\Contracts;

use App\Models\Source;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface SourceRepositoryInterface
{
    /**
     * Get all sources
     */
    public function all(): Collection;

    /**
     * Get paginated sources
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator;

    /**
     * Find source by ID
     */
    public function find(int $id): ?Source;

    /**
     * Find source by MediaStack ID
     */
    public function findByMediastackId(string $mediastackId): ?Source;

    /**
     * Create new source
     */
    public function create(array $data): Source;

    /**
     * Update source
     */
    public function update(Source $source, array $data): bool;

    /**
     * Delete source
     */
    public function delete(Source $source): bool;

    /**
     * Get active sources
     */
    public function getActive(): Collection;

    /**
     * Filter by country
     */
    public function byCountry(string $country): Collection;

    /**
     * Filter by language
     */
    public function byLanguage(string $language): Collection;

    /**
     * Filter by category
     */
    public function byCategory(string $category): Collection;

    /**
     * Activate source
     */
    public function activate(Source $source): bool;

    /**
     * Deactivate source
     */
    public function deactivate(Source $source): bool;
}
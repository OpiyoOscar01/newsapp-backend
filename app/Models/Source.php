<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

/**
 * Source Model
 * 
 * Represents news sources from MediaStack API
 * 
 * @property int $id
 * @property string $mediastack_id MediaStack source ID (e.g., cnn, bbc)
 * @property string $name Display name of the news source
 * @property string|null $url Official website URL of the source
 * @property string|null $category Primary category of this source
 * @property string|null $country 2-letter country code (ISO 3166-1 alpha-2)
 * @property string|null $language 2-letter language code (ISO 639-1)
 * @property bool $is_active Whether this source is currently being tracked
 * @property \Illuminate\Support\Carbon|null $last_fetched_at Last time we fetched news from this source
 * @property array|null $fetch_settings JSON settings for fetching from this source
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class Source extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'mediastack_id',
        'name',
        'url',
        'category',
        'country',
        'language',
        'is_active',
        'last_fetched_at',
        'fetch_settings',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'last_fetched_at' => 'datetime',
        'fetch_settings' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get all articles from this source
     */
    public function articles(): HasMany
    {
        return $this->hasMany(Article::class, 'source', 'mediastack_id');
    }

    /**
     * Get the category this source belongs to
     */
    public function categoryModel(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category', 'slug');
    }

    /**
     * Scope to get only active sources
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by country
     */
    public function scopeByCountry(Builder $query, string $country): Builder
    {
        return $query->where('country', $country);
    }

    /**
     * Scope to filter by language
     */
    public function scopeByLanguage(Builder $query, string $language): Builder
    {
        return $query->where('language', $language);
    }

    /**
     * Scope to filter by category
     */
    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'mediastack_id';
    }

    /**
     * Get articles count for this source
     */
    public function getArticlesCountAttribute(): int
    {
        return $this->articles()->where('is_active', true)->count();
    }

    /**
     * Get recent articles count (last 24 hours)
     */
    public function getRecentArticlesCountAttribute(): int
    {
        return $this->articles()
            ->where('is_active', true)
            ->where('published_at', '>=', now()->subDay())
            ->count();
    }

    /**
     * Activate the source
     */
    public function activate(): bool
    {
        return $this->update(['is_active' => true]);
    }

    /**
     * Deactivate the source
     */
    public function deactivate(): bool
    {
        return $this->update(['is_active' => false]);
    }

    /**
     * Update last fetched timestamp
     */
    public function updateLastFetched(): bool
    {
        return $this->update(['last_fetched_at' => now()]);
    }
}
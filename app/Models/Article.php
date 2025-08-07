<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Article Model
 * 
 * Main entity storing news articles fetched from MediaStack API
 * 
 * @property int $id
 * @property string $title Article headline/title from MediaStack API
 * @property string|null $description Article description/summary from MediaStack API
 * @property string|null $content Full article content (may be limited by MediaStack)
 * @property string|null $author Article author name from MediaStack API
 * @property string $url Original article URL - used as unique identifier
 * @property string|null $source Source name from MediaStack API (e.g., CNN, BBC)
 * @property string|null $image_url Featured image URL from MediaStack API
 * @property string|null $category Article category from MediaStack API
 * @property string|null $language Article language (2-letter ISO code)
 * @property string|null $country Source country (2-letter ISO code)
 * @property \Illuminate\Support\Carbon $published_at Original publication timestamp from MediaStack API
 * @property bool $is_active Whether article is visible to users
 * @property bool $is_featured Whether article should be featured
 * @property int $view_count Number of times article has been viewed
 * @property float|null $sentiment_score Sentiment analysis score (-1 to 1)
 * @property array|null $tags Additional tags for the article (JSON array)
 * @property array|null $keywords Extracted keywords from the article (JSON array)
 * @property string|null $slug SEO-friendly URL slug
 * @property string|null $meta_description SEO meta description
 * @property string|null $cached_image_path Local cached version of the image
 * @property string $processing_status Status of article processing (image caching, keyword extraction, etc.)
 * @property \Illuminate\Support\Carbon|null $last_processed_at Last time article was processed
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class Article extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'description',
        'content',
        'author',
        'url',
        'source',
        'image_url',
        'category',
        'language',
        'country',
        'published_at',
        'is_active',
        'is_featured',
        'view_count',
        'sentiment_score',
        'tags',
        'keywords',
        'slug',
        'meta_description',
        'cached_image_path',
        'processing_status',
        'last_processed_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'published_at' => 'datetime',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'view_count' => 'integer',
        'sentiment_score' => 'float',
        'tags' => 'array',
        'keywords' => 'array',
        'last_processed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'cached_image_path',
        'processing_status',
        'last_processed_at',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($article) {
            if (empty($article->slug)) {
                $article->slug = Str::slug($article->title);
            }
            if (empty($article->meta_description)) {
                $article->meta_description = Str::limit(strip_tags($article->description), 160);
            }
        });
    }

    /**
     * Get the source model this article belongs to
     */
    public function sourceModel(): BelongsTo
    {
        return $this->belongsTo(Source::class, 'source', 'mediastack_id');
    }

    /**
     * Get the category model this article belongs to
     */
    public function categoryModel(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category', 'slug');
    }

    /**
     * Get all interactions for this article
     */
    public function interactions(): HasMany
    {
        return $this->hasMany(ArticleInteraction::class);
    }

    /**
     * Get all keywords for this article
     */
    public function articleKeywords(): HasMany
    {
        return $this->hasMany(ArticleKeyword::class);
    }

    /**
     * Scope to get only active articles
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get only featured articles
     */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope to filter by category
     */
    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    /**
     * Scope to filter by source
     */
    public function scopeBySource(Builder $query, string $source): Builder
    {
        return $query->where('source', $source);
    }

    /**
     * Scope to get recent articles
     */
    public function scopeRecent(Builder $query, int $days = 7): Builder
    {
        return $query->where('published_at', '>=', now()->subDays($days));
    }

    /**
     * Scope to get popular articles
     */
    public function scopePopular(Builder $query): Builder
    {
        return $query->orderBy('view_count', 'desc');
    }

    /**
     * Scope to search articles
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->whereRaw('MATCH(title, description) AGAINST(? IN NATURAL LANGUAGE MODE)', [$term]);
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Get the reading time estimate in minutes
     */
    public function getReadingTimeAttribute(): int
    {
        $wordCount = str_word_count(strip_tags($this->content ?? $this->description ?? ''));
        return max(1, ceil($wordCount / 200)); // Average reading speed: 200 words per minute
    }

    /**
     * Get the excerpt of the article
     */
    public function getExcerptAttribute(): string
    {
        return Str::limit(strip_tags($this->description ?? $this->content ?? ''), 150);
    }

    /**
     * Feature the article
     */
    public function feature(): bool
    {
        return $this->update(['is_featured' => true]);
    }

    /**
     * Unfeature the article
     */
    public function unfeature(): bool
    {
        return $this->update(['is_featured' => false]);
    }

    /**
     * Activate the article
     */
    public function activate(): bool
    {
        return $this->update(['is_active' => true]);
    }

    /**
     * Deactivate the article
     */
    public function deactivate(): bool
    {
        return $this->update(['is_active' => false]);
    }

    /**
     * Increment view count
     */
    public function incrementViewCount(): bool
    {
        return $this->increment('view_count');
    }

    /**
     * Mark as processed
     */
    public function markAsProcessed(): bool
    {
        return $this->update([
            'processing_status' => 'processed',
            'last_processed_at' => now(),
        ]);
    }

    /**
     * Mark as processing failed
     */
    public function markAsProcessingFailed(): bool
    {
        return $this->update([
            'processing_status' => 'failed',
            'last_processed_at' => now(),
        ]);
    }
}